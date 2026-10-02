<?php

declare(strict_types=1);

namespace Suqo\Params;

use Suqo\IntervalType;

/**
 * One line of a checkout session.
 *
 * openapi gives the item two mutually exclusive shapes — `pbp_id` alone, or the
 * four inline fields together — and rejects any inline field sent beside a
 * `pbp_id`, because the plan behind a billing period sets the price, the cadence
 * and the discount. The two named constructors make the invalid combination
 * unrepresentable rather than a 400 you discover at runtime.
 *
 * A session takes at most ten items, and two items resolving to the same product
 * collapse: only the last one survives.
 */
final class CheckoutItem
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        private readonly array $wire,
    ) {
    }

    /**
     * An existing billing period, by the `pbp_id` from `products->list()`. Its
     * price is snapshotted when the session opens, so editing the plan afterwards
     * cannot change what the buyer already saw.
     *
     * @param array<string, mixed> $extra Merged into the item under wire names, verbatim (N1).
     */
    public static function billingPeriod(string $pbpId, array $extra = []): self
    {
        return new self(['pbp_id' => $pbpId] + $extra);
    }

    /**
     * A partner-priced line with no billing period behind it.
     *
     * @param string               $amount         A decimal string (§9.1); never a float.
     * @param int                  $intervalCount  1-1095 for `day`, 1-156 for `week`, 1-36 for
     *                                             `month`, 1-3 for `year`, and 0 — required —
     *                                             for `one_time`.
     * @param string|null          $discountAmount A decimal string, or null for no discount.
     * @param array<string, mixed> $extra          Merged into the item under wire names, verbatim (N1).
     */
    public static function inline(
        string $name,
        string $amount,
        IntervalType|string $intervalType,
        int $intervalCount,
        ?string $discountAmount = null,
        array $extra = [],
    ): self {
        $wire = [
            'name' => $name,
            'amount' => $amount,
            'interval_type' => $intervalType instanceof IntervalType ? $intervalType->value : $intervalType,
            'interval_count' => $intervalCount,
        ];

        if ($discountAmount !== null) {
            $wire['discount_amount'] = $discountAmount;
        }

        return new self($wire + $extra);
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return $this->wire;
    }
}
