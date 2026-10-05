<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Exception\ValidationError;
use Suqo\Model\SubscriptionStatus;
use Suqo\Resource\Subscriptions;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — the subscription operations that address a single record: read, resume
 * and renew. List, create, cancel and updateBillingCycle are covered by
 * SuqoClientTest and RenamesTest.
 */
final class SubscriptionsTest extends TransportTestCase
{
    public function testReadFetchesOneSubscription(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [
            'subscription_id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'status' => 'active',
            'is_active' => true,
            'client' => ['phone' => '9841000100', 'full_name' => 'Ram Shrestha', 'email' => 'ram@client.com'],
            'product' => ['pbp_id' => 'pbp_3n9k2x', 'price' => '999.00', 'currency' => 'NPR'],
            'current_period_start' => '2026-07-03T00:00:00Z',
            'current_period_end' => '2026-08-03T00:00:00Z',
            'next_billing_cycle' => '2026-08-03T00:00:00Z',
            'created_at' => '2026-07-03T10:15:00Z',
        ]);

        $subscription = (new Subscriptions($this->transport($client)))->read('3fa85f64-5717-4562-b3fc-2c963f66afa6');

        self::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', $subscription->subscriptionId);
        self::assertSame(SubscriptionStatus::Active, $subscription->status);
        // §3 — the wire `client` block reads back as `customer`.
        self::assertSame('ram@client.com', $subscription->customer?->email);
        self::assertSame('pbp_3n9k2x', $subscription->product?->pbpId);
        self::assertSame('GET', $client->lastRequest()->method);
        self::assertStringContainsString(
            '/api/v1/subscriptions/3fa85f64-5717-4562-b3fc-2c963f66afa6/',
            $client->lastRequest()->url,
        );
    }

    /** openapi declares no request body, so none is sent (§6.3). */
    public function testResumePostsToTheResumeEndpointWithNoBody(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['message' => 'Subscription resumed.']);

        $result = (new Subscriptions($this->transport($client)))->resume('sub_1');

        self::assertSame('Subscription resumed.', $result->message);
        self::assertSame('POST', $client->lastRequest()->method);
        self::assertNull($client->lastRequest()->body);
        self::assertArrayNotHasKey('Content-Type', $client->lastRequest()->headers);
        self::assertStringContainsString('/api/v1/subscriptions/sub_1/resume/', $client->lastRequest()->url);
    }

    /**
     * An illegal state transition answers 400 with a bare list of strings. The
     * message must survive rather than being dropped for want of a field key.
     */
    public function testResumeSurfacesABareListMessage(): void
    {
        $client = (new MockHttpClient())->pushJson(400, ['Cannot resume subscription while it is cancelled.']);

        try {
            (new Subscriptions($this->transport($client)))->resume('sub_1');
            self::fail('Expected a ValidationError.');
        } catch (ValidationError $error) {
            self::assertSame('Cannot resume subscription while it is cancelled.', $error->getMessage());
            self::assertSame([], $error->fieldErrors);
        }
    }

    /**
     * Renew names the subscription in the body and lives under the singular
     * `/subscription/` noun, which is the API's spelling.
     */
    public function testRenewPostsTheSubscriptionIdAndReturnsACheckoutSession(): void
    {
        $client = (new MockHttpClient())->pushJson(201, [
            'public_id' => 'cks_3n9k2xQ7m',
            'expires_at' => '2026-07-03T10:45:00Z',
            'checkout_url' => 'https://app.suqo.ai/checkout/cks_3n9k2xQ7m',
        ]);

        $session = (new Subscriptions($this->transport($client)))
            ->renew('3fa85f64-5717-4562-b3fc-2c963f66afa6');

        self::assertSame('cks_3n9k2xQ7m', $session->publicId);
        self::assertSame('https://app.suqo.ai/checkout/cks_3n9k2xQ7m', $session->checkoutUrl);
        self::assertSame('POST', $client->lastRequest()->method);
        self::assertStringContainsString('/api/v1/subscription/renew/', $client->lastRequest()->url);

        self::assertSame(
            ['subscription_id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6'],
            json_decode((string) $client->lastRequest()->body, true),
        );
    }

    /** A cancelled subscription cannot be renewed, reported as a bare list. */
    public function testRenewSurfacesABareListMessage(): void
    {
        $client = (new MockHttpClient())->pushJson(400, ['This subscription was cancelled and cannot be renewed.']);

        try {
            (new Subscriptions($this->transport($client)))->renew('sub_1');
            self::fail('Expected a ValidationError.');
        } catch (ValidationError $error) {
            self::assertSame('This subscription was cancelled and cannot be renewed.', $error->getMessage());
        }
    }

    /** The id travels in the path, so it is escaped rather than trusted. */
    public function testReadEscapesThePathSegment(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['subscription_id' => 'sub_1']);

        (new Subscriptions($this->transport($client)))->read('sub_1/../customers');

        self::assertStringNotContainsString('/../', $client->lastRequest()->url);
    }
}
