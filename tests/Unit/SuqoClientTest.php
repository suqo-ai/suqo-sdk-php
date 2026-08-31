<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Constants;
use Suqo\Environment;
use Suqo\Exception\SuqoConfigError;
use Suqo\LogLevel;
use Suqo\Model\SubscriptionStatus;
use Suqo\Params\UpdateBillingCycleParams;
use Suqo\Resource\Customers;
use Suqo\Resource\Products;
use Suqo\Resource\Subscriptions;
use Suqo\SuqoClient;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * Top-level wiring (§5) plus the §9.3 tolerant-read requirement.
 */
final class SuqoClientTest extends TransportTestCase
{
    public function testResourcesAreExposed(): void
    {
        $suqo = new SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient());

        self::assertInstanceOf(Products::class, $suqo->products);
        self::assertInstanceOf(Subscriptions::class, $suqo->subscriptions);
        self::assertInstanceOf(Customers::class, $suqo->customers);
    }

    public function testTheSandboxBaseUrlIsUsedEndToEnd(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['count' => 0, 'results' => []]);
        $suqo = new SuqoClient(apiKey: self::KEY, httpClient: $client, logLevel: LogLevel::Off);

        $suqo->products->list();

        self::assertSame(
            Constants::SANDBOX_URL . '/api/v1/products/',
            $client->lastRequest()->url,
        );
    }

    public function testALiveKeyUsesTheLiveBaseUrl(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['count' => 0, 'results' => []]);
        $suqo = new SuqoClient(apiKey: 'su_key_abc', httpClient: $client, logLevel: LogLevel::Off);

        $suqo->products->list();

        self::assertSame(Environment::Live, $suqo->config->environment);
        self::assertStringStartsWith(Constants::LIVE_URL, $client->lastRequest()->url);
    }

    public function testConstructionFailsOnAMalformedKey(): void
    {
        $this->expectException(SuqoConfigError::class);
        $this->expectExceptionMessage(Constants::MSG_MALFORMED_KEY);

        new SuqoClient(apiKey: 'nope', httpClient: new MockHttpClient());
    }

    public function testCancelPostsToTheCancelEndpoint(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['message' => 'Cancelled']);
        $suqo = new SuqoClient(apiKey: self::KEY, httpClient: $client, logLevel: LogLevel::Off);

        $result = $suqo->subscriptions->cancel('sub_1');

        self::assertSame('Cancelled', $result->message);
        self::assertSame(
            Constants::SANDBOX_URL . '/api/v1/subscriptions/sub_1/cancel/',
            $client->lastRequest()->url,
        );
        self::assertSame('POST', $client->lastRequest()->method);

        // openapi declares no request body for cancel, so none is sent (§6.3 T3).
        self::assertNull($client->lastRequest()->body);
        self::assertArrayNotHasKey('Content-Type', $client->lastRequest()->headers);
    }

    public function testUpdateBillingCyclePostsToItsOwnEndpoint(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['message' => 'Updated']);
        $suqo = new SuqoClient(apiKey: self::KEY, httpClient: $client, logLevel: LogLevel::Off);

        $result = $suqo->subscriptions->updateBillingCycle(
            new UpdateBillingCycleParams(subscriptionId: 'sub_1', nextBillingCycle: '2026-09-03'),
        );

        self::assertSame('Updated', $result->message);
        self::assertSame(
            Constants::SANDBOX_URL . '/api/v1/subscriptions/update-billing-cycle/',
            $client->lastRequest()->url,
        );
        self::assertSame(
            ['subscription_id' => 'sub_1', 'next_billing_cycle' => '2026-09-03'],
            json_decode((string) $client->lastRequest()->body, true),
        );
    }

    public function testSubscriptionPageSurfacesTheWireNamedAggregates(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 0,
            'next' => null,
            'previous' => null,
            'results' => [],
            'total_subscriptions' => 10,
            'active_subscriptions' => 7,
            'due_subscriptions' => 2,
            'inactive_subscriptions' => 1,
        ]);

        $page = (new SuqoClient(apiKey: self::KEY, httpClient: $client, logLevel: LogLevel::Off))
            ->subscriptions->list();

        self::assertSame(10, $page->totalSubscriptions);
        self::assertSame(7, $page->activeSubscriptions);
        self::assertSame(2, $page->dueSubscriptions);
        self::assertSame(1, $page->inactiveSubscriptions);
    }

    /**
     * §9.3 — an unrecognised status is preserved and surfaced, never rejected.
     */
    public function testUnknownSubscriptionStatusIsToleratedAndPreserved(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 2,
            'next' => null,
            'previous' => null,
            'results' => [
                ['subscription_id' => 'sub_1', 'status' => 'active'],
                ['subscription_id' => 'sub_2', 'status' => 'paused_by_merchant'],
            ],
        ]);

        $page = (new SuqoClient(apiKey: self::KEY, httpClient: $client, logLevel: LogLevel::Off))
            ->subscriptions->list();

        self::assertSame(SubscriptionStatus::Active, $page->results[0]->status);
        self::assertSame('paused_by_merchant', $page->results[1]->status);
    }

    public function testEveryDeclaredSubscriptionStatusRoundTrips(): void
    {
        $expected = [
            'pending_checkout',
            'active',
            'due',
            'cancelled',
            'pending_cancellation',
            'inactive',
        ];

        self::assertSame(
            $expected,
            array_map(static fn (SubscriptionStatus $c): string => $c->value, SubscriptionStatus::cases()),
        );
    }
}
