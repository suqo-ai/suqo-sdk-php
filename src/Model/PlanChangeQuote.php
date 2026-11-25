<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * What moving a subscription to another price costs — returned by both
 * `subscriptions->changePreview()` and `subscriptions->change()`.
 *
 * `type` is `upgrade`, `downgrade` or `lateral`; `basis` is `last_payment` or
 * `current_price` (no payment record was found, so today's price was used).
 * Both stay strings so a value added server-side needs no SDK release.
 *
 * The daily rates are decimal strings (§9.1). `amountDue` is whole NPR with VAT
 * inside, non-zero only on an upgrade. `newExpiryDate` is a date (`Y-m-d`).
 *
 * `checkoutSessionId` and `checkoutUrl` are set only by `change()` on an
 * upgrade — the plan switches once the buyer pays at that URL. They are `null`
 * on every preview and whenever the change applied immediately.
 */
final class PlanChangeQuote extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $type,
        public readonly ?string $basis,
        public readonly ?string $currentDailyRate,
        public readonly ?string $newDailyRate,
        public readonly ?int $remainingDays,
        public readonly ?int $amountDue,
        public readonly ?int $daysAdded,
        public readonly ?string $newExpiryDate,
        public readonly ?string $checkoutSessionId,
        public readonly ?string $checkoutUrl,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'type'),
            Wire::nstr($wire, 'basis'),
            Wire::decimal($wire, 'current_daily_rate'),
            Wire::decimal($wire, 'new_daily_rate'),
            Wire::count($wire, 'remaining_days'),
            Wire::count($wire, 'amount_due'),
            Wire::count($wire, 'days_added'),
            Wire::nstr($wire, 'new_expiry_date'),
            Wire::nstr($wire, 'checkout_session_id'),
            Wire::nstr($wire, 'checkout_url'),
            $wire,
        );
    }
}
