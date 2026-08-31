<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Model\Subscription;
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

    public function testTheCreateResponseAlsoAppliesTheReadRename(): void
    {
        $client = (new MockHttpClient())->pushJson(201, [
            'pbp_id' => 'pbp_3n9k2x',
            'return_url' => 'https://merchant.example.com/thanks',
            'client' => [
                'phone' => '9841000100',
                'full_name' => 'Ram Shrestha',
                'email' => 'ram@client.com',
            ],
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
        self::assertSame('https://merchant.example.com/thanks', $created->returnUrl);
        self::assertSame('ram@client.com', $created->customer?->email);
        self::assertArrayHasKey('client', $created->toArray());
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
