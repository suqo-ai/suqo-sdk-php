<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalCustomer`. §3 reserves the bare name `Customer` for exactly
 * this — the full-record type of the Customers resource — and §9.4 un-stubs it in
 * the change that adds those endpoints to openapi.yaml.
 *
 * Returned by {@see \Suqo\Resource\Customers::list()} and
 * {@see \Suqo\Resource\Customers::read()}.
 *
 * `buyer_phone` and `buyer_email` keep their wire stems: §3's `client` →
 * `customer` rename covers the field named `client`, and N7 makes that table the
 * complete set.
 *
 * `id` is a prefixed public id (`cus_0390b1820`), not an integer and not a UUID
 * — confirmed against a live response 2026-09-11. openapi names the path
 * parameter `public_id` for the same reason.
 */
final class Customer extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $buyerPhone,
        public readonly ?string $buyerEmail,
        public readonly ?string $fullName,
        public readonly ?string $address,
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
            Wire::nstr($wire, 'buyer_phone'),
            Wire::nstr($wire, 'buyer_email'),
            Wire::nstr($wire, 'full_name'),
            Wire::nstr($wire, 'address'),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
