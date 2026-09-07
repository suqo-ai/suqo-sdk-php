<?php

declare(strict_types=1);

namespace Suqo;

use Suqo\Exception\SuqoConfigError;
use Suqo\Http\CurlHttpClient;
use Suqo\Http\HttpClientInterface;

/**
 * §4 — resolved, validated configuration. Immutable once built.
 */
final class Config
{
    private function __construct(
        public readonly string $apiKey,
        public readonly Environment $environment,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
        public readonly LogLevel $logLevel,
        public readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * §4.2 — resolve_config. An explicitly supplied environment is a check,
     * never an override: it can only agree with the key prefix or fail.
     *
     * @param float|null $timeout Seconds.
     *
     * @throws SuqoConfigError
     */
    public static function resolve(
        ?string $apiKey = null,
        Environment|string|null $environment = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
        LogLevel|string|null $logLevel = null,
        ?HttpClientInterface $httpClient = null,
    ): self {
        $key = $apiKey ?? self::env(Constants::ENV_API_KEY);

        if ($key === null || $key === '') {
            throw new SuqoConfigError(Constants::MSG_MALFORMED_KEY);
        }

        // The two prefixes diverge at the 4th character, so either order works.
        if (str_starts_with($key, Constants::SANDBOX_KEY_PREFIX)) {
            $inferred = Environment::Sandbox;
        } elseif (str_starts_with($key, Constants::LIVE_KEY_PREFIX)) {
            $inferred = Environment::Live;
        } else {
            throw new SuqoConfigError(Constants::MSG_MALFORMED_KEY);
        }

        $given = self::coerceEnvironment($environment);

        if ($given !== null && $given !== $inferred) {
            throw new SuqoConfigError(Constants::msgEnvConflict($inferred, $given));
        }

        $resolvedTimeout = $timeout ?? Constants::DEFAULT_TIMEOUT;
        if ($resolvedTimeout <= 0.0) {
            throw new SuqoConfigError('Invalid timeout: must be greater than zero.');
        }

        $resolvedRetries = $maxRetries ?? Constants::MAX_RETRIES;
        if ($resolvedRetries < 0) {
            throw new SuqoConfigError('Invalid max retries: must be zero or greater.');
        }

        return new self(
            $key,
            $inferred,
            $inferred->baseUrl(),
            $resolvedTimeout,
            $resolvedRetries,
            self::coerceLogLevel($logLevel) ?? self::logLevelFromEnv() ?? LogLevel::Warn,
            $httpClient ?? new CurlHttpClient(),
        );
    }

    /** @throws SuqoConfigError */
    private static function coerceEnvironment(Environment|string|null $environment): ?Environment
    {
        if ($environment === null || $environment instanceof Environment) {
            return $environment;
        }

        $parsed = Environment::tryFrom($environment);

        if ($parsed === null) {
            throw new SuqoConfigError(sprintf(
                'Invalid environment "%s": expected "live" or "sandbox".',
                $environment,
            ));
        }

        return $parsed;
    }

    /** @throws SuqoConfigError */
    private static function coerceLogLevel(LogLevel|string|null $logLevel): ?LogLevel
    {
        if ($logLevel === null || $logLevel instanceof LogLevel) {
            return $logLevel;
        }

        $parsed = LogLevel::tryFrom($logLevel);

        if ($parsed === null) {
            throw new SuqoConfigError(sprintf(
                'Invalid log level "%s": expected one of debug, info, warn, error, off.',
                $logLevel,
            ));
        }

        return $parsed;
    }

    /**
     * An unrecognised SUQO_LOG value falls back to the default rather than
     * failing construction: the environment is not the caller's call site.
     */
    private static function logLevelFromEnv(): ?LogLevel
    {
        $raw = self::env(Constants::ENV_LOG);

        if ($raw === null || $raw === '') {
            return null;
        }

        return LogLevel::tryFrom(strtolower(trim($raw)));
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        if (is_string($value)) {
            return $value;
        }

        foreach ([$_ENV, $_SERVER] as $bag) {
            if (isset($bag[$name]) && is_string($bag[$name])) {
                return $bag[$name];
            }
        }

        return null;
    }
}
