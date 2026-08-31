<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalProduct`. §10.1 names the surface type `Product`.
 *
 * `vat` and `total_subscribers` are strings on the wire and stay strings here
 * (§9.1). `type` is an open string rather than an enum: §9.3 mandates a closed
 * enumeration for SubscriptionStatus only, and §14 forbids surface this
 * specification does not describe.
 */
final class Product extends Model
{
    /**
     * @param list<ProductPlan>    $plan
     * @param array<string, mixed> $wire
     */
    private function __construct(
        public readonly ?string $productId,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?string $type,
        public readonly ?bool $isActive,
        public readonly ?string $termsAndConditions,
        public readonly ?string $featuresAndBenefits,
        public readonly ?string $vat,
        public readonly ?string $productImage,
        public readonly array $plan,
        public readonly ?string $totalSubscribers,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
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
            Wire::nstr($wire, 'description'),
            Wire::nstr($wire, 'type'),
            Wire::nbool($wire, 'is_active'),
            Wire::nstr($wire, 'terms_and_conditions'),
            Wire::nstr($wire, 'features_and_benefits'),
            Wire::decimal($wire, 'vat'),
            Wire::nstr($wire, 'product_image'),
            array_map(
                static fn (array $record): ProductPlan => ProductPlan::fromWire($record),
                Wire::objectList($wire, 'plan'),
            ),
            Wire::decimal($wire, 'total_subscribers'),
            Wire::nstr($wire, 'created_at'),
            Wire::nstr($wire, 'updated_at'),
            $wire,
        );
    }
}
