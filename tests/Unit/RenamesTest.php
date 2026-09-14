<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Model\Subscription;
use Suqo\Model\SubscriptionStatus;
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerInput;
use Suqo\Params\CustomerShipping;
use Suqo\Resource\Subscriptions;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Renames, M1..M3.
 */
final class RenamesTest extends TransportTestCase
{
    public function testM1CreateEmitsClientNotCustomer(): void
    {
        $client = (new MockHttpClient())->pushJson(201, ['pbp_id' => 'pbp_1']);
        $subscriptions = new Subscriptions($this->transport($client));

        $subscriptions->create(new CreateSubscriptionParams(
            pbpId: 'pbp_1',
            customer: new CustomerInput(
                phone: '9841000100',
                fullName: 'Ram Shrestha',
                email: 'ram@client.com',
            ),
        ));

        $body = json_decode((string) $client->lastRequest()->body, true);

        self::assertIsArray($body);
        self::assertArrayHasKey('client', $body);
        self::assertArrayNotHasKey('customer', $body);
        self::assertSame(
            ['phone' => '9841000100', 'full_name' => 'Ram Shrestha', 'email' => 'ram@client.com'],
            $body['client'],
        );
    }

    public function testCreateSerialisesTheNestedWriteShapesVerbatim(): void
    {
        $client = (new MockHttpClient())->pushJson(201, ['pbp_id' => 'pbp_1']);

        (new Subscriptions($this->transport($client)))->create(new CreateSubscriptionParams(
            pbpId: 'pbp_1',
            customer: new CustomerInput(
                phone: '9841000100',
                fullName: 'Ram Shrestha',
                email: 'ram@client.com',
                address: 'Kathmandu, Nepal',
                billing: new CustomerBilling(
                    billingBusinessName: 'ABC Pvt Ltd.',
                    billingEmail: 'ram@client.com',
                    billingAddress: 'Kathmandu, Nepal',
                    billingPanVat: '9841000100',
                ),
                shipping: new CustomerShipping(
                    phone: '9841000100',
                    fullName: 'Ram Shrestha',
                    email: 'ram@client.com',
                    address: 'Kathmandu, Nepal',
                ),
            ),
            returnUrl: 'https://merchant.example.com/thanks',
        ));

        self::assertSame(
            [
                'pbp_id' => 'pbp_1',
                'client' => [
                    'phone' => '9841000100',
                    'full_name' => 'Ram Shrestha',
                    'email' => 'ram@client.com',
                    'address' => 'Kathmandu, Nepal',
                    // The billing_ prefix survives nesting under `billing` (N1).
                    'billing' => [
                        'billing_business_name' => 'ABC Pvt Ltd.',
                        'billing_email' => 'ram@client.com',
                        'billing_address' => 'Kathmandu, Nepal',
                        'billing_pan_vat' => '9841000100',
                    ],
                    'shipping' => [
                        'phone' => '9841000100',
                        'full_name' => 'Ram Shrestha',
                        'email' => 'ram@client.com',
                        'address' => 'Kathmandu, Nepal',
                    ],
                ],
                'return_url' => 'https://merchant.example.com/thanks',
            ],
            json_decode((string) $client->lastRequest()->body, true),
        );
    }

    public function testM2ListExposesCustomerAndNotClient(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [self::record('sub_1', 'ram@client.com')],
        ]);

        $page = (new Subscriptions($this->transport($client)))->list();

        self::assertCount(1, $page->results);
        $subscription = $page->results[0];
        $customer = $subscription->customer;

        self::assertNotNull($customer);
        self::assertSame('ram@client.com', $customer->email);
        self::assertSame('ABC Pvt Ltd.', $customer->billing?->businessName);
        self::assertSame('9841000100', $customer->shipping?->phone);
        self::assertFalse(
            property_exists($subscription, 'client'),
            'The surface must not expose the wire name.',
        );
    }

    public function testM3AutoPagedRecordsFromPageTwoGetTheSameRename(): void
    {
        $next = 'https://test-be.suqo.ai/api/v1/subscriptions/?page=2';

        $client = (new MockHttpClient())
            ->pushJson(200, [
                'count' => 2,
                'next' => $next,
                'previous' => null,
                'results' => [self::record('sub_1', 'ram@client.com')],
            ])
            ->pushJson(200, [
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [self::record('sub_2', 'grace@client.com')],
            ]);

        $emails = [];
        foreach ((new Subscriptions($this->transport($client)))->autoPaging() as $subscription) {
            self::assertInstanceOf(Subscription::class, $subscription);
            $emails[] = $subscription->customer?->email;
        }

        self::assertSame(['ram@client.com', 'grace@client.com'], $emails);
        self::assertSame(2, $client->attempts());
    }

    public function testRawPayloadStillCarriesTheWireName(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [self::record('sub_1', 'ram@client.com')],
        ]);

        $page = (new Subscriptions($this->transport($client)))->list();
        $raw = $page->results[0]->toArray();

        self::assertArrayHasKey('client', $raw);
        self::assertArrayNotHasKey('customer', $raw);
    }

    /**
     * The 201 body is its own schema, not an echo of the request: it carries the
     * new subscription's id, status and checkout URL, and neither `return_url`
     * nor `client` comes back. The write-direction rename still applies to the
     * *request*, which is what this asserts on the recorded payload.
     */
    public function testTheCreateResponseCarriesTheCheckoutUrl(): void
    {
        $client = (new MockHttpClient())->pushJson(201, [
            'created_at' => '2026-07-03T10:15:00Z',
            'pbp_id' => 'pbp_3n9k2x',
            'subscription_id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'status' => 'pending_checkout',
            'checkout_url' => 'https://app.suqo.ai/pay/3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'next_billing_cycle' => '2026-08-03T00:00:00Z',
        ]);

        $created = (new Subscriptions($this->transport($client)))->create(new CreateSubscriptionParams(
            pbpId: 'pbp_3n9k2x',
            customer: new CustomerInput(
                phone: '9841000100',
                fullName: 'Ram Shrestha',
                email: 'ram@client.com',
            ),
        ));

        self::assertSame('pbp_3n9k2x', $created->pbpId);
        self::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', $created->subscriptionId);
        self::assertSame(SubscriptionStatus::PendingCheckout, $created->status);
        self::assertSame(
            'https://app.suqo.ai/pay/3fa85f64-5717-4562-b3fc-2c963f66afa6',
            $created->checkoutUrl,
        );
        self::assertSame('2026-08-03T00:00:00Z', $created->nextBillingCycle);
        self::assertSame('2026-07-03T10:15:00Z', $created->createdAt);

        // The request still carries the write-direction rename.
        $sent = json_decode($client->lastRequest()->body ?? '', true);
        self::assertIsArray($sent);
        self::assertArrayHasKey('client', $sent);
        self::assertArrayNotHasKey('customer', $sent);
    }

    /**
     * The `results` item shape from openapi's `/subscriptions/` 200 example.
     *
     * @return array<string, mixed>
     */
    private static function record(string $id, string $email): array
    {
        return [
            'subscription_id' => $id,
            'status' => 'active',
            'is_active' => true,
            'client' => [
                'phone' => '9841000100',
                'full_name' => 'Ram Shrestha',
                'email' => $email,
                'address' => 'Kathmandu, Nepal',
                'billing' => [
                    'business_name' => 'ABC Pvt Ltd.',
                    'email' => $email,
                    'address' => 'Kathmandu, Nepal',
                    'pan_vat' => '9841000100',
                ],
                'shipping' => [
                    'phone' => '9841000100',
                    'full_name' => 'Ram Shrestha',
                    'email' => $email,
                    'address' => 'Kathmandu, Nepal',
                ],
            ],
            'product' => [
                'product_id' => '205293a1-84f4-426e-8f8c-5ddb239e5d2f',
                'name' => 'Pro Plan Bundle',
                'plan_name' => 'Basic',
                'pbp_id' => 'pbp_3n9k2x',
                'label' => 'Monthly',
                'price' => '999.00',
                'currency' => 'NPR',
            ],
            'current_period_start' => '2026-07-03T00:00:00Z',
            'current_period_end' => '2026-08-03T00:00:00Z',
            'next_billing_cycle' => '2026-08-03T00:00:00Z',
            'created_at' => '2026-07-03T10:15:00Z',
        ];
    }
}
