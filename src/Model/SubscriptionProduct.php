<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: the `product` object embedded in a subscription record. A different,
 * narrower shape from {@see Product} — it carries the plan billing point
 * (`pbp_id`) and price that the product list does not.
 */
final class SubscriptionProduct extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $productId,
        public readonly ?string $name,
        public readonly ?string $planName,
        public readonly ?string $pbpId,
        public readonly ?string $label,
        public readonly ?string $price,
        public readonly ?string $currency,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'product_id'),
            Wire::nstr($wire, 'name'),
            Wire::nstr($wire, 'plan_name'),
            Wire::nstr($wire, 'pbp_id'),
            Wire::nstr($wire, 'label'),
            Wire::decimal($wire, 'price'),
            Wire::nstr($wire, 'currency'),
            $wire,
        );
    }
}
