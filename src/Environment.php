<?php

declare(strict_types=1);

namespace Suqo;

/**
 * §4.1 — environment. Never an override; only ever a check against the key
 * prefix (§4.2).
 */
enum Environment: string
{
    case Live = 'live';
    case Sandbox = 'sandbox';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Live => Constants::LIVE_URL,
            self::Sandbox => Constants::SANDBOX_URL,
        };
    }
}
