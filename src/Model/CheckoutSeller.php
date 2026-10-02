<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `CheckoutSeller` — the seller shown on a checkout session, nested
 * under {@see CheckoutSessionDetail::$sellerDetails}.
 */
final class CheckoutSeller extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $phone,
        public readonly ?string $businessLogo,
        public readonly ?string $businessName,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'id'),
            Wire::nstr($wire, 'phone'),
            Wire::nstr($wire, 'business_logo'),
            Wire::nstr($wire, 'business_name'),
            $wire,
        );
    }
}
