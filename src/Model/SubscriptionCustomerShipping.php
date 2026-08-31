<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: the `client.shipping` object embedded in a subscription record.
 */
final class SubscriptionCustomerShipping extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $phone,
        public readonly ?string $fullName,
        public readonly ?string $email,
        public readonly ?string $address,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'phone'),
            Wire::nstr($wire, 'full_name'),
            Wire::nstr($wire, 'email'),
            Wire::nstr($wire, 'address'),
            $wire,
        );
    }
}
