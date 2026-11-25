<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `bankAccounts.update` (openapi: `bank-accounts_partial_update`).
 *
 * `code` — the 6-digit code `bankAccounts->sendOtp()` emails — is required on
 * every call: it is what verifies the account, and every edit needs a fresh one.
 * The other fields are optional and only what you set is sent, so verifying
 * without changing anything is `new BankAccountUpdateParams(code: 123456)`.
 */
final class BankAccountUpdateParams
{
    /**
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly int $code,
        public readonly ?string $bankName = null,
        public readonly ?string $accountName = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $branch = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = ['code' => $this->code];

        if ($this->bankName !== null) {
            $wire['bank_name'] = $this->bankName;
        }

        if ($this->accountName !== null) {
            $wire['account_name'] = $this->accountName;
        }

        if ($this->accountNumber !== null) {
            $wire['account_number'] = $this->accountNumber;
        }

        if ($this->branch !== null) {
            $wire['branch'] = $this->branch;
        }

        return $wire + $this->extra;
    }
}
