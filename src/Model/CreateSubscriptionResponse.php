<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §10.2 — the response to `subscriptions.create`.
 *
 * openapi's 201 body is its own schema, not an echo of the request: it carries
 * the new subscription's id, its status, and the `checkoutUrl` the buyer must be
 * sent to in order to pay. Neither `return_url` nor `client` comes back.
 *
 * `status` is `pending_checkout` on every documented response, but is decoded
 * through the §9.3 tolerant rule like any other status rather than being pinned
 * to that one case.
 *
 * The return is not proof of payment. Redirect the buyer to `checkoutUrl` and
 * learn the real outcome from the `checkout.succeeded` / `checkout.failed`
 * webhooks.
 */
final class CreateSubscriptionResponse extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $subscriptionId,
        public readonly ?string $pbpId,
        public readonly SubscriptionStatus|string|null $status,
        public readonly ?string $checkoutUrl,
        public readonly ?string $nextBillingCycle,
        public readonly ?string $createdAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'subscription_id'),
            Wire::nstr($wire, 'pbp_id'),
            SubscriptionStatus::parse($wire['status'] ?? null),
            Wire::nstr($wire, 'checkout_url'),
            Wire::nstr($wire, 'next_billing_cycle'),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
