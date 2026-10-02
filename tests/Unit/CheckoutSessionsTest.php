<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Exception\NotFoundError;
use Suqo\Exception\RateLimitError;
use Suqo\IntervalType;
use Suqo\Params\CheckoutItem;
use Suqo\Params\CreateCheckoutSessionParams;
use Suqo\Resource\CheckoutSessions;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — CheckoutSessions.
 */
final class CheckoutSessionsTest extends TransportTestCase
{
    public function testCreatePostsABillingPeriodItem(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::createdBody());

        $session = (new CheckoutSessions($this->transport($client)))->create(
            new CreateCheckoutSessionParams(
                items: [CheckoutItem::billingPeriod('pbp_3n9k2x')],
                returnUrl: 'https://partner.example.com/orders/1234',
            ),
        );

        self::assertSame('cks_3n9k2xQ7m', $session->publicId);
        self::assertSame('https://app.suqo.ai/checkout/cks_3n9k2xQ7m', $session->checkoutUrl);
        self::assertSame('2026-07-03T10:45:00Z', $session->expiresAt);

        $request = $client->lastRequest();

        self::assertSame('POST', $request->method);
        self::assertStringContainsString('/api/v1/checkout-sessions/', $request->url);
        self::assertSame(
            [
                'items' => [['pbp_id' => 'pbp_3n9k2x']],
                'return_url' => 'https://partner.example.com/orders/1234',
            ],
            self::decodeBody($client),
        );
    }

    /**
     * The two item shapes are mutually exclusive on the wire: a `pbp_id` item
     * carries nothing else, and an inline item carries no `pbp_id`.
     */
    public function testCreatePostsAnInlineItem(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::createdBody());

        (new CheckoutSessions($this->transport($client)))->create(
            new CreateCheckoutSessionParams(
                items: [CheckoutItem::inline(
                    name: 'Setup fee',
                    amount: '2500.00',
                    intervalType: IntervalType::OneTime,
                    intervalCount: 0,
                    discountAmount: '500.00',
                )],
                returnUrl: 'https://partner.example.com/orders/1234',
                customerId: 'cus_2a108b4eb',
            ),
        );

        self::assertSame(
            [
                'items' => [[
                    'name' => 'Setup fee',
                    'amount' => '2500.00',
                    'interval_type' => 'one_time',
                    'interval_count' => 0,
                    'discount_amount' => '500.00',
                ]],
                'return_url' => 'https://partner.example.com/orders/1234',
                'customer_id' => 'cus_2a108b4eb',
            ],
            self::decodeBody($client),
        );
    }

    /** Omitting the customer is how the buyer identifies themselves by OTP. */
    public function testCustomerIdIsOmittedWhenNull(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::createdBody());

        (new CheckoutSessions($this->transport($client)))->create(
            new CreateCheckoutSessionParams(
                items: [CheckoutItem::billingPeriod('pbp_3n9k2x')],
                returnUrl: 'https://partner.example.com/orders/1234',
            ),
        );

        self::assertSame(
            [
                'items' => [['pbp_id' => 'pbp_3n9k2x']],
                'return_url' => 'https://partner.example.com/orders/1234',
            ],
            self::decodeBody($client),
        );
    }

    /** A discount is optional; an absent one is not sent as null. */
    public function testInlineDiscountIsOmittedWhenNull(): void
    {
        $item = CheckoutItem::inline(
            name: 'Setup fee',
            amount: '2500.00',
            intervalType: 'month',
            intervalCount: 1,
        );

        self::assertArrayNotHasKey('discount_amount', $item->toWire());
        self::assertSame('month', $item->toWire()['interval_type']);
    }

    public function testReadDecodesTheSessionDetail(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'public_id' => 'cks_3n9k2xQ7m',
            'line_items' => [['name' => 'Pro Plan', 'amount' => '999.00']],
            'customer_id' => 'cus_2a108b4eb',
            'seller_details' => [
                'id' => 'sel_1',
                'phone' => '9841000100',
                'business_logo' => 'https://cdn.suqo.ai/logo.png',
                'business_name' => 'ABC Pvt Ltd.',
            ],
            'return_url' => 'https://partner.example.com/orders/1234',
            'expires_at' => '2026-07-03T10:45:00Z',
            'completed_at' => null,
            'is_expired' => 'false',
        ]);

        $detail = (new CheckoutSessions($this->transport($client)))->read('cks_3n9k2xQ7m');

        self::assertSame('cks_3n9k2xQ7m', $detail->publicId);
        self::assertSame('cus_2a108b4eb', $detail->customerId);
        self::assertSame('ABC Pvt Ltd.', $detail->sellerDetails?->businessName);
        self::assertCount(1, $detail->lineItems);
        self::assertNull($detail->completedAt);
        self::assertStringContainsString('/api/v1/checkout-sessions/cks_3n9k2xQ7m/', $client->lastRequest()->url);
    }

    /**
     * openapi types `is_expired` as a string, and a live body may yet spell it
     * as a JSON boolean. Both read as a bool; anything else reads as absent.
     */
    public function testIsExpiredAcceptsBothSpellings(): void
    {
        foreach ([['true', true], [true, true], ['false', false], [false, false], ['maybe', null]] as [$wire, $expected]) {
            $client = (new MockHttpClient())->pushJson(200, ['is_expired' => $wire]);

            $detail = (new CheckoutSessions($this->transport($client)))->read('cks_1');

            self::assertSame($expected, $detail->isExpired);
        }
    }

    /**
     * A paid or expired session answers 404 and carries its own `return_url`
     * beside the message, so the caller can still send the buyer onwards.
     */
    public function testAnExpiredSessionRaisesNotFoundCarryingTheReturnUrl(): void
    {
        $client = (new MockHttpClient())->pushJson(404, [
            'detail' => 'This checkout link is no longer valid. Please start a new checkout to continue.',
            'return_url' => 'https://partner.example.com/orders/1234',
        ]);

        try {
            (new CheckoutSessions($this->transport($client)))->read('cks_3n9k2xQ7m');
            self::fail('Expected a NotFoundError.');
        } catch (NotFoundError $error) {
            // The `detail` arm of the mapper wants a single-key body, so this
            // two-key one classifies as field-shaped — but the message is still
            // the detail sentence, and the whole body survives on rawBody under
            // its wire names.
            self::assertStringContainsString('no longer valid', $error->getMessage());
            self::assertSame([
                'detail' => 'This checkout link is no longer valid. Please start a new checkout to continue.',
                'return_url' => 'https://partner.example.com/orders/1234',
            ], $error->rawBody);
            // fieldErrors is attached only on a 400.
            self::assertSame([], $error->fieldErrors);
        }
    }

    /**
     * Create is the one rate-limited endpoint. It is a write, and writes are
     * never retried (I6), so the 429 surfaces on the first attempt.
     */
    public function testTheRateLimitSurfacesWithoutRetrying(): void
    {
        $client = (new MockHttpClient())->pushJson(
            429,
            ['detail' => 'Request was throttled. Expected available in 41 seconds.'],
            ['Retry-After' => '41'],
        );

        try {
            (new CheckoutSessions($this->transport($client)))->create(
                new CreateCheckoutSessionParams(
                    items: [CheckoutItem::billingPeriod('pbp_3n9k2x')],
                    returnUrl: 'https://partner.example.com/orders/1234',
                ),
            );
            self::fail('Expected a RateLimitError.');
        } catch (RateLimitError $error) {
            self::assertSame(41.0, $error->retryAfter);
            self::assertSame(1, $client->attempts());
        }
    }

    public function testTheCheckoutSessionsResourceIsWiredOntoTheClient(): void
    {
        $suqo = new \Suqo\SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient());

        self::assertInstanceOf(CheckoutSessions::class, $suqo->checkoutSessions);
    }

    /** @return array<string, mixed> */
    private static function createdBody(): array
    {
        return [
            'public_id' => 'cks_3n9k2xQ7m',
            'expires_at' => '2026-07-03T10:45:00Z',
            'checkout_url' => 'https://app.suqo.ai/checkout/cks_3n9k2xQ7m',
        ];
    }

    /** The body actually sent, decoded. Mirrors how the other suites read it. */
    private static function decodeBody(MockHttpClient $client): mixed
    {
        return json_decode((string) $client->lastRequest()->body, true);
    }
}
