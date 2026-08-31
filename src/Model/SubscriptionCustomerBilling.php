<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: the `client.billing` object embedded in a subscription record.
 *
 * Distinct from {@see \Suqo\Params\CustomerBilling}: the read shape spells its
 * keys `business_name`, `email`, `address`, `pan_vat`, while the write shape
 * prefixes each with `billing_`. Wire names are never adjusted (N1), so the two
 * directions need two types.
 */
final class SubscriptionCustomerBilling extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $businessName,
        public readonly ?string $email,
        public readonly ?string $address,
        public readonly ?string $panVat,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'business_name'),
            Wire::nstr($wire, 'email'),
            Wire::nstr($wire, 'address'),
            Wire::nstr($wire, 'pan_vat'),
            $wire,
        );
    }
}
