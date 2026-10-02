<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Model\WebhookEvent;
use Suqo\Params\WebhookParams;
use Suqo\Params\WebhookUpdateParams;
use Suqo\Resource\Webhooks;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Webhooks management.
 */
final class WebhooksTest extends TransportTestCase
{
    /**
     * The only collection on the API that answers with a bare JSON array rather
     * than the `{count, next, previous, results}` envelope.
     */
    public function testListDecodesABareArray(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::record(), self::record('whk_second', 'checkout.failed')]);

        $webhooks = (new Webhooks($this->transport($client)))->list();

        self::assertCount(2, $webhooks);
        self::assertSame('whk_a1b2c3d4e', $webhooks[0]->id);
        self::assertSame(WebhookEvent::CheckoutSucceeded, $webhooks[0]->event);
        self::assertSame(WebhookEvent::CheckoutFailed, $webhooks[1]->event);
        self::assertSame('https://partner.example.com/hooks/suqo', $webhooks[0]->endpointUrl);
        self::assertTrue($webhooks[0]->isActive);
        self::assertStringContainsString('/api/v1/webhooks/', $client->lastRequest()->url);
    }

    /** An empty account, and a body that is not a list at all, both read as none. */
    public function testListToleratesAnEmptyOrUnexpectedBody(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [])->pushJson(200, null);
        $webhooks = new Webhooks($this->transport($client));

        self::assertSame([], $webhooks->list());
        self::assertSame([], $webhooks->list());
    }

    /** §9.3 — an event this SDK does not name surfaces as the raw string. */
    public function testAnUnknownEventDecodesToTheRawString(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::record('whk_x', 'invoice.settled')]);

        self::assertSame('invoice.settled', (new Webhooks($this->transport($client)))->list()[0]->event);
    }

    public function testCreateSendsTheEventAndEndpoint(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::record());

        $webhook = (new Webhooks($this->transport($client)))->create(
            new WebhookParams(
                event: WebhookEvent::CheckoutSucceeded,
                endpointUrl: 'https://partner.example.com/hooks/suqo',
            ),
        );

        self::assertSame('whk_a1b2c3d4e', $webhook->id);
        self::assertSame('POST', $client->lastRequest()->method);
        self::assertSame(
            [
                'event' => 'checkout.succeeded',
                'endpoint_url' => 'https://partner.example.com/hooks/suqo',
            ],
            self::decodeBody($client),
        );
    }

    /** A raw event string is accepted so a new event needs no SDK release. */
    public function testCreateAcceptsARawEventString(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::record('whk_x', 'invoice.settled'));

        (new Webhooks($this->transport($client)))->create(
            new WebhookParams(event: 'invoice.settled', endpointUrl: 'https://partner.example.com/hooks/suqo'),
        );

        self::assertSame(
            [
                'event' => 'invoice.settled',
                'endpoint_url' => 'https://partner.example.com/hooks/suqo',
            ],
            self::decodeBody($client),
        );
    }

    public function testReadFetchesOneWebhook(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new Webhooks($this->transport($client)))->read('whk_a1b2c3d4e');

        self::assertSame('GET', $client->lastRequest()->method);
        self::assertStringContainsString('/api/v1/webhooks/whk_a1b2c3d4e/', $client->lastRequest()->url);
    }

    public function testReplacePutsTheWholeRecord(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new Webhooks($this->transport($client)))->replace(
            'whk_a1b2c3d4e',
            new WebhookParams(
                event: WebhookEvent::SubscriptionStatusChanged,
                endpointUrl: 'https://partner.example.com/hooks/v2',
                isActive: true,
            ),
        );

        self::assertSame('PUT', $client->lastRequest()->method);
        self::assertSame(
            [
                'event' => 'subscription.status_changed',
                'endpoint_url' => 'https://partner.example.com/hooks/v2',
                'is_active' => true,
            ],
            self::decodeBody($client),
        );
    }

    /** Pausing deliveries is a body of exactly one field. */
    public function testUpdateSendsOnlyWhatWasSet(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new Webhooks($this->transport($client)))->update(
            'whk_a1b2c3d4e',
            new WebhookUpdateParams(isActive: false),
        );

        self::assertSame('PATCH', $client->lastRequest()->method);
        self::assertSame(['is_active' => false], self::decodeBody($client));
    }

    /** The 204 carries no body, and the SDK must not trip over that. */
    public function testDeleteToleratesAnEmpty204(): void
    {
        $client = (new MockHttpClient())->pushJson(204, null);

        (new Webhooks($this->transport($client)))->delete('whk_a1b2c3d4e');

        self::assertSame('DELETE', $client->lastRequest()->method);
        self::assertStringContainsString('/api/v1/webhooks/whk_a1b2c3d4e/', $client->lastRequest()->url);
        self::assertNull($client->lastRequest()->body);
    }

    public function testSecretReadsTheSigningSecret(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['signing_secret' => 'whsec_3n9k2xQ7']);

        $secret = (new Webhooks($this->transport($client)))->secret();

        self::assertSame('whsec_3n9k2xQ7', $secret->signingSecret);
        self::assertStringContainsString('/api/v1/webhooks/secret/', $client->lastRequest()->url);
    }

    /** The acknowledgement is keyed `detail`, not `message`. */
    public function testTestDeliveryReadsTheDetailAcknowledgement(): void
    {
        $client = (new MockHttpClient())->pushJson(202, ['detail' => 'Test webhook enqueued.']);

        $result = (new Webhooks($this->transport($client)))->testDelivery('whk_a1b2c3d4e');

        self::assertSame('Test webhook enqueued.', $result->detail);
        self::assertSame('POST', $client->lastRequest()->method);
        self::assertNull($client->lastRequest()->body);
        self::assertStringContainsString('/api/v1/webhooks/whk_a1b2c3d4e/test-delivery/', $client->lastRequest()->url);
    }

    public function testTheWebhooksResourceIsWiredOntoTheClient(): void
    {
        $suqo = new \Suqo\SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient());

        self::assertInstanceOf(Webhooks::class, $suqo->webhooks);
    }

    /** @return array<string, mixed> */
    private static function record(string $id = 'whk_a1b2c3d4e', string $event = 'checkout.succeeded'): array
    {
        return [
            'id' => $id,
            'event' => $event,
            'endpoint_url' => 'https://partner.example.com/hooks/suqo',
            'is_active' => true,
            'created_at' => '2026-07-03T10:15:00Z',
        ];
    }

    /** The body actually sent, decoded. Mirrors how the other suites read it. */
    private static function decodeBody(MockHttpClient $client): mixed
    {
        return json_decode((string) $client->lastRequest()->body, true);
    }
}
