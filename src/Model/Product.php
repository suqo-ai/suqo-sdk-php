<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalProduct`. §10.1 names the surface type `Product`.
 *
 * `total_subscribers` is a string on the wire and stays one here (§9.1). `vat`
 * is an object ({@see ProductVat}) and `product_image` a list of objects
 * ({@see ProductImage}), both of which the API sends even when empty. `type` is
 * an open string rather than an enum: §9.3 mandates a closed enumeration for
 * SubscriptionStatus only, and §14 forbids surface this specification does not
 * describe.
 */
final class Product extends Model
{
    /**
     * @param list<ProductImage>   $productImage
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
        public readonly ?ProductVat $vat,
        public readonly array $productImage,
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
            self::vatFromWire($wire),
            array_map(
                static fn (array $record): ProductImage => ProductImage::fromWire($record),
                Wire::objectList($wire, 'product_image'),
            ),
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

    /**
     * `vat` is absent on a product that has never had VAT configured, and an
     * object with null members on one that has it switched off. Both decode to
     * null and a present object respectively; neither is an error.
     *
     * @param array<string, mixed> $wire
     */
    private static function vatFromWire(array $wire): ?ProductVat
    {
        $vat = Wire::object($wire, 'vat');

        return $vat === null ? null : ProductVat::fromWire($vat);
    }
}
