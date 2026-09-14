<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `OfferMini`. A discount attached to a billing period.
 *
 * `discountAmount` is an integer on the wire, not a decimal, so it is not run
 * through the §9.1 decimal rule.
 */
final class Offer extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $id,
        public readonly ?int $discountAmount,
        public readonly ?string $startsAt,
        public readonly ?string $validUntil,
        public readonly ?bool $isActive,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'id'),
            Wire::nint($wire, 'discount_amount'),
            Wire::nstr($wire, 'starts_at'),
            Wire::nstr($wire, 'valid_until'),
            Wire::nbool($wire, 'is_active'),
            $wire,
        );
    }
}
