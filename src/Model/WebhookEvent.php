<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: the `event` enum of `ExternalWebhook`. One webhook per event per
 * account.
 *
 * §9.3's tolerant read applies: a field typed `WebhookEvent|string` holds a case
 * for a recognised value and the raw wire string for anything else, so an event
 * added server-side surfaces rather than failing deserialisation.
 */
enum WebhookEvent: string
{
    case CheckoutSucceeded = 'checkout.succeeded';
    case CheckoutFailed = 'checkout.failed';
    case SubscriptionStatusChanged = 'subscription.status_changed';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyDeleted = 'api_key.deleted';
    case ApiKeyExpiringSoon = 'api_key.expiring_soon';
    case ApiKeyExpired = 'api_key.expired';

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
