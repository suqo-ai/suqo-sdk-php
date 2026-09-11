<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalProductVat`.
 *
 * `vatType` and `vatPercentage` are both nullable and are null in the common
 * case — a product with VAT switched off sends
 * `{"is_vat_active": false, "vat_type": null, "vat_percentage": null}`.
 *
 * `vatPercentage` arrives as a JSON number rather than a string, unlike every
 * other decimal in the API. {@see Wire::decimal()} renders it to a string
 * without ever casting through a float type, so §9.1 still holds.
 */
final class ProductVat extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?bool $isVatActive,
        public readonly ?string $vatType,
        public readonly ?string $vatPercentage,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nbool($wire, 'is_vat_active'),
            Wire::nstr($wire, 'vat_type'),
            Wire::decimal($wire, 'vat_percentage'),
            $wire,
        );
    }
}
