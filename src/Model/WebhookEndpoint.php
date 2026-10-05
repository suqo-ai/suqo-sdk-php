<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalWebhook` on the read side — one registered endpoint.
 *
 * §3 renames it: the bare name `Webhook` is already taken by
 * {@see \Suqo\Webhook}, the static, networkless verifier for an inbound
 * delivery, which is unrelated to managing a subscription to an event.
 *
 * `event` follows §9.3's tolerant read: a recognised value is a
 * {@see WebhookEvent} case, an unrecognised one the raw wire string.
 *
 * Returned by every {@see \Suqo\Resource\Webhooks} operation but `delete`,
 * `secret` and `testDelivery`.
 */
final class WebhookEndpoint extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $id,
        public readonly WebhookEvent|string|null $event,
        public readonly ?string $endpointUrl,
        public readonly ?bool $isActive,
        public readonly ?string $createdAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'id'),
            WebhookEvent::parse($wire['event'] ?? null),
            Wire::nstr($wire, 'endpoint_url'),
            Wire::nbool($wire, 'is_active'),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
