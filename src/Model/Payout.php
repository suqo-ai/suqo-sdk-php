<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * One payout of your due transactions to your bank account.
 *
 * `payoutId` is the public id (`PYT-…`) that `payouts->read()` takes. `status`
 * is `PENDING` until SUQO has paid it out, then `COMPLETED`; it stays a string
 * so a status added server-side needs no SDK release.
 *
 * Every amount is a decimal string (§9.1), at up to three decimals:
 * `netAmount` is `amount` less `providerCharge`, `suqoPlatformFee` and
 * `smsCharge`. `paidDate` and `receiptUrl` are `null` until it is paid.
 */
final class Payout extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $payoutId,
        public readonly ?string $status,
        public readonly ?string $amount,
        public readonly ?string $providerCharge,
        public readonly ?string $suqoPlatformFee,
        public readonly ?string $smsCharge,
        public readonly ?string $netAmount,
        public readonly ?string $requestedDate,
        public readonly ?string $paidDate,
        public readonly ?string $receiptUrl,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'payout_id'),
            Wire::nstr($wire, 'status'),
            Wire::decimal($wire, 'amount'),
            Wire::decimal($wire, 'provider_charge'),
            Wire::decimal($wire, 'suqo_platform_fee'),
            Wire::decimal($wire, 'sms_charge'),
            Wire::decimal($wire, 'net_amount'),
            Wire::nstr($wire, 'requested_date'),
            Wire::nstr($wire, 'paid_date'),
            Wire::nstr($wire, 'receipt_url'),
            $wire,
        );
    }
}
