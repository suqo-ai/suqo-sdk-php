<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * A sub-seller account owned by yours.
 *
 * `id` is a prefixed public id (`usr_…`). `legalBusinessName` comes from its
 * KYC and can be `null`.
 */
final class SubSeller extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $email,
        public readonly ?string $legalBusinessName,
        public readonly ?SubSellerKyc $kyc,
        public readonly ?string $createdAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $kyc = Wire::object($wire, 'kyc');

        return new self(
            Wire::nstr($wire, 'id'),
            Wire::nstr($wire, 'email'),
            Wire::nstr($wire, 'legal_business_name'),
            $kyc === null ? null : SubSellerKyc::fromWire($kyc),
            Wire::nstr($wire, 'created_at'),
            $wire,
        );
    }
}
