<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalCustomer`. §3 reserves the bare name `Customer` for exactly
 * this — the full-record type of the Customers resource — and §9.4 un-stubs it in
 * the change that adds those endpoints to openapi.yaml.
 *
 * The Customers *operations* still raise NotImplementedError: §10.3 is normative
 * for behaviour, and implementing them would add public surface this
 * specification does not describe (§14). See {@see \Suqo\Resource\Customers}.
 *
 * `buyer_phone` and `buyer_email` keep their wire stems: §3's `client` →
 * `customer` rename covers the field named `client`, and N7 makes that table the
 * complete set.
 */
final class Customer extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?int $id,
        public readonly ?string $buyerPhone,
        public readonly ?string $buyerEmail,
        public readonly ?string $fullName,
        public readonly ?string $createdAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::count($wire, 'id'),
            Wire::nstr($wire, 'buyer_phone'),
            Wire::nstr($wire, 'buyer_email'),
            Wire::nstr($wire, 'full_name'),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
