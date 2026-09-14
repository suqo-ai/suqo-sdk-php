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

    /** @var string Template; interpolate via {@see self::customerRead()}. */
    private const CUSTOMER_READ = '/api/v1/customers/{id}';

    /** @var string Template; interpolate via {@see self::subscriptionCancel()}. */
    private const SUBSCRIPTION_CANCEL = '/api/v1/subscriptions/{id}/cancel';

    public static function customerRead(string $id): string
    {
        return str_replace('{id}', rawurlencode($id), self::CUSTOMER_READ);
    }

    public static function subscriptionCancel(string $id): string
    {
        return str_replace('{id}', rawurlencode($id), self::SUBSCRIPTION_CANCEL);
    }

    private function __construct()
    {
    }
}
