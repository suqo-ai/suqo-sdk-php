<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `bankAccounts.create` (openapi: `bank-accounts_create`).
 *
 * Every field is required. The account starts unverified: call
 * `bankAccounts->sendOtp()`, then `bankAccounts->update()` with the emailed
 * code, before requesting a payout.
 */
final class BankAccountParams
{
    /**
     * @param string               $accountNumber 7 to 21 characters.
     * @param array<string, mixed> $extra         Merged into the request body under wire
     *                                            names, verbatim (N1).
     */
    public function __construct(
        public readonly string $bankName,
        public readonly string $accountName,
        public readonly string $accountNumber,
        public readonly string $branch,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return [
            'bank_name' => $this->bankName,
            'account_name' => $this->accountName,
            'account_number' => $this->accountNumber,
            'branch' => $this->branch,
        ] + $this->extra;
    }
}
