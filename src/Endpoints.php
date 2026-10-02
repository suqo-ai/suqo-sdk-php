<?php

declare(strict_types=1);

namespace Suqo;

/**
 * §6.1 / I4 — the single endpoint table. No URL path literal may appear
 * anywhere else in the SDK. Trailing slashes are appended by the URL builder
 * (§6.2), never stored here.
 */
final class Endpoints
{
    public const PRODUCTS = '/api/v1/products';

    public const CUSTOMERS = '/api/v1/customers';

    public const SUBSCRIPTIONS = '/api/v1/subscriptions';

    public const SUBSCRIPTION_BILLING_CYCLE = '/api/v1/subscriptions/update-billing-cycle';

    /**
     * Renew is the one route under the singular noun. That is the API's
     * spelling, not a typo here.
     */
    public const SUBSCRIPTION_RENEW = '/api/v1/subscription/renew';

    public const CHECKOUT_SESSIONS = '/api/v1/checkout-sessions';

    public const WEBHOOKS = '/api/v1/webhooks';

    public const WEBHOOK_SECRET = '/api/v1/webhooks/secret';

    /** @var string Template; interpolate via {@see self::customerRead()}. */
    private const CUSTOMER_READ = '/api/v1/customers/{id}';

    /** @var string Template; interpolate via {@see self::subscriptionRead()}. */
    private const SUBSCRIPTION_READ = '/api/v1/subscriptions/{id}';

    /** @var string Template; interpolate via {@see self::subscriptionCancel()}. */
    private const SUBSCRIPTION_CANCEL = '/api/v1/subscriptions/{id}/cancel';

    /** @var string Template; interpolate via {@see self::subscriptionResume()}. */
    private const SUBSCRIPTION_RESUME = '/api/v1/subscriptions/{id}/resume';

    /** @var string Template; interpolate via {@see self::checkoutSessionRead()}. */
    private const CHECKOUT_SESSION_READ = '/api/v1/checkout-sessions/{id}';

    /** @var string Template; interpolate via {@see self::webhookRead()}. */
    private const WEBHOOK_READ = '/api/v1/webhooks/{id}';

    /** @var string Template; interpolate via {@see self::webhookTestDelivery()}. */
    private const WEBHOOK_TEST_DELIVERY = '/api/v1/webhooks/{id}/test-delivery';

    public static function customerRead(string $id): string
    {
        return self::interpolate(self::CUSTOMER_READ, $id);
    }

    public static function subscriptionRead(string $id): string
    {
        return self::interpolate(self::SUBSCRIPTION_READ, $id);
    }

    public static function subscriptionCancel(string $id): string
    {
        return self::interpolate(self::SUBSCRIPTION_CANCEL, $id);
    }

    public static function subscriptionResume(string $id): string
    {
        return self::interpolate(self::SUBSCRIPTION_RESUME, $id);
    }

    public static function checkoutSessionRead(string $id): string
    {
        return self::interpolate(self::CHECKOUT_SESSION_READ, $id);
    }

    public static function webhookRead(string $id): string
    {
        return self::interpolate(self::WEBHOOK_READ, $id);
    }

    public static function webhookTestDelivery(string $id): string
    {
        return self::interpolate(self::WEBHOOK_TEST_DELIVERY, $id);
    }

    private static function interpolate(string $template, string $id): string
    {
        return str_replace('{id}', rawurlencode($id), $template);
    }

    private function __construct()
    {
    }
}
