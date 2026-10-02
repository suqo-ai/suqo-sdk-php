<?php

declare(strict_types=1);

namespace Suqo;

/**
 * openapi: the `interval_type` enum shared by a billing period and an inline
 * checkout item.
 *
 * `one_time` is a single purchase rather than a cadence: it has no next billing
 * date, it is excluded from the counters on `GET /subscriptions/`, and its
 * subscriptions can be neither cancelled, resumed nor rescheduled.
 *
 * Read models keep `interval_type` as a plain string — retyping a published
 * property is breaking — so this enum exists for the write side
 * ({@see \Suqo\Params\CheckoutItem::inline()}) and for callers who would rather
 * compare a case than a literal:
 *
 * ```php
 * IntervalType::parse($billingPeriod->intervalType) === IntervalType::OneTime
 * ```
 */
enum IntervalType: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case OneTime = 'one_time';

    /**
     * A recognised value as a case; an unrecognised one as the raw string; an
     * absent or non-string value as null (§9.3's tolerant read).
     */
    public static function parse(mixed $value): self|string|null
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value) ?? $value;
    }
}
