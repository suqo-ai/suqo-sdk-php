<?php

declare(strict_types=1);

namespace Suqo\Params;

use Suqo\IntervalType;

/**
 * The target of `subscriptions.changePreview` and `subscriptions.change`
 * (openapi: `subscriptions_change_preview`, `subscriptions_change`).
 *
 * Like {@see CheckoutItem}, the API takes two mutually exclusive shapes — a
 * `pbp_id` alone, or the four inline fields together — and rejects any inline
 * field sent beside a `pbp_id`. The two named constructors make the mixture
 * unrepresentable.
 */
final class PlanChangeParams
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        private readonly array $wire,
    ) {
    }

    /**
     * Move onto one of your own prices, by the `pbp_id` from `products->list()`.
     *
     * @param array<string, mixed> $extra Merged into the body under wire names, verbatim (N1).
     */
    public static function billingPeriod(string $pbpId, array $extra = []): self
    {
        return new self(['pbp_id' => $pbpId] + $extra);
    }

    /**
     * Move onto a price you set here, with no billing period behind it. This
     * detaches the subscription from its product. VAT is added to `amount` from
     * your account's VAT settings.
     *
     * @param string               $amount        A decimal string (§9.1); never a float. Minimum 50.
     * @param int                  $intervalCount 1-1095 for `day`, 1-156 for `week`, 1-36 for
     *                                            `month`, 1-3 for `year`.
     * @param array<string, mixed> $extra         Merged into the body under wire names, verbatim (N1).
     */
    public static function inline(
        string $name,
        string $amount,
        IntervalType|string $intervalType,
        int $intervalCount,
        array $extra = [],
    ): self {
        return new self([
            'name' => $name,
            'amount' => $amount,
            'interval_type' => $intervalType instanceof IntervalType ? $intervalType->value : $intervalType,
            'interval_count' => $intervalCount,
        ] + $extra);
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return $this->wire;
    }
}
