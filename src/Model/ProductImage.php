<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ProductImage`. One image on a product, with its display position.
 */
final class ProductImage extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $image,
        public readonly ?int $imageOrder,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'image'),
            Wire::nint($wire, 'image_order'),
            $wire,
        );
    }
}
