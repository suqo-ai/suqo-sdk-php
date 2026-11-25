<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * One bank account payouts are paid to.
 *
 * `id` is an integer — unlike the prefixed public ids elsewhere in the API.
 * `accountNumberMasked` carries the full number while the account is
 * unverified, so it can be checked before verifying, and is masked
 * (`****4521`) once `verifiedAt` is set. The account number itself is never
 * returned under its own name.
 */
final class BankAccount extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?int $id,
        public readonly ?string $bankName,
        public readonly ?string $accountName,
        public readonly ?string $branch,
        public readonly ?string $accountNumberMasked,
        public readonly ?string $verifiedAt,
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
            Wire::count($wire, 'id'),
            Wire::nstr($wire, 'bank_name'),
            Wire::nstr($wire, 'account_name'),
            Wire::nstr($wire, 'branch'),
            Wire::nstr($wire, 'account_number_masked'),
            Wire::nstr($wire, 'verified_at'),
            Wire::nstr($wire, 'created_at'),
            Wire::nstr($wire, 'updated_at'),
            $wire,
        );
    }
}
