<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §3 — the surface name for the `client` object embedded in a subscription
 * record. The wire says `client`; the surface says `customer`, because `client`
 * collides with the SDK client object.
 *
 * `Customer` (bare) is the full record of the Customers resource
 * ({@see Customer}); this is the embedded read shape, mirroring the
 * {@see SubscriptionProduct} pattern.
 */
final class SubscriptionCustomer extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $phone,
        public readonly ?string $fullName,
        public readonly ?string $email,
        public readonly ?string $address,
        public readonly ?SubscriptionCustomerBilling $billing,
        public readonly ?SubscriptionCustomerShipping $shipping,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $billing = Wire::object($wire, 'billing');
        $shipping = Wire::object($wire, 'shipping');

        return new self(
            Wire::nstr($wire, 'phone'),
            Wire::nstr($wire, 'full_name'),
            Wire::nstr($wire, 'email'),
            Wire::nstr($wire, 'address'),
            $billing === null ? null : SubscriptionCustomerBilling::fromWire($billing),
            $shipping === null ? null : SubscriptionCustomerShipping::fromWire($shipping),
            $wire,
        );
    }
}
