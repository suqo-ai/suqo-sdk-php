<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * A sub-seller's KYC state, nested on {@see SubSeller}.
 *
 * `status` is `KYC_VERIFICATION_PENDING` while under review, `KYC_VERIFIED` once
 * approved and `KYC_RE_VERIFICATION` when rejected — kept as a string so a new
 * value needs no SDK release. `rejectionReason` is plain text, set only while
 * the status is `KYC_RE_VERIFICATION`.
 */
final class SubSellerKyc extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $status,
        public readonly ?string $rejectionReason,
        public readonly ?string $resubmittedAt,
        public readonly ?string $verifiedAt,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'status'),
            Wire::nstr($wire, 'rejection_reason'),
            Wire::nstr($wire, 'resubmitted_at'),
            Wire::nstr($wire, 'verified_at'),
            $wire,
        );
    }
}
