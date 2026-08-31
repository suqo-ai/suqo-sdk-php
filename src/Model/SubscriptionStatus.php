<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §9.3 — SubscriptionStatus, verbatim on the wire.
 *
 * PHP has native enums, so the closed set is rendered as one. §9.3 also requires
 * a tolerant read, which a backed enum cannot express on its own: fields typed
 * `SubscriptionStatus|string` therefore hold a case for a recognised value and
 * the raw wire string for anything else, so a server-side addition surfaces
 * rather than failing deserialisation.
 */
enum SubscriptionStatus: string
{
    case PendingCheckout = 'pending_checkout';
    case Active = 'active';
    case Due = 'due';
    case Cancelled = 'cancelled';
    case PendingCancellation = 'pending_cancellation';
    case Inactive = 'inactive';

    /**
     * A recognised value as a case; an unrecognised one as the raw string; an
     * absent or non-string value as null.
     */
    public static function parse(mixed $value): self|string|null
    {
        if (!is_string($value)) {
            return null;
        }

        return self::tryFrom($value) ?? $value;
    }
}
