<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: a record in the `/subscriptions/` list response. §10.2 names the
 * surface type `Subscription`.
 *
 * §3: the wire field `client` is exposed as `customer` on read. The rename is
 * applied here, at the serialisation boundary, and nowhere else — the raw payload
 * from {@see Model::toArray()} still says `client` (N8).
 */
final class Subscription extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $subscriptionId,
        public readonly SubscriptionStatus|string|null $status,
        public readonly ?bool $isActive,
        public readonly ?SubscriptionCustomer $customer,
        public readonly ?SubscriptionProduct $product,
        public readonly ?string $currentPeriodStart,
        public readonly ?string $currentPeriodEnd,
        public readonly ?string $nextBillingCycle,
        public readonly ?string $createdAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $client = Wire::object($wire, 'client');
        $product = Wire::object($wire, 'product');

        return new self(
            Wire::nstr($wire, 'subscription_id'),
            SubscriptionStatus::parse($wire['status'] ?? null),
            Wire::nbool($wire, 'is_active'),
            $client === null ? null : SubscriptionCustomer::fromWire($client),
            $product === null ? null : SubscriptionProduct::fromWire($product),
            Wire::nstr($wire, 'current_period_start'),
            Wire::nstr($wire, 'current_period_end'),
            Wire::nstr($wire, 'next_billing_cycle'),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
