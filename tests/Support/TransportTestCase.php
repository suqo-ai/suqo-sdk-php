<?php

declare(strict_types=1);

namespace Suqo\Tests\Support;

use Closure;
use PHPUnit\Framework\TestCase;
use Suqo\Config;
use Suqo\Http\RetryPolicy;
use Suqo\Http\Transport;
use Suqo\Http\UrlBuilder;
use Suqo\Logging\Logger;
use Suqo\LogLevel;

abstract class TransportTestCase extends TestCase
{
    protected const KEY = 'su_test_key_abc';

    /**
     * @param Closure(int, \Suqo\Cancellation): void|null $sleeper Defaults to a
     *        no-op that still honours cancellation, so retry tests do not sleep.
     * @param Closure(): string|null                      $requestIds
     */
    protected function transport(
        MockHttpClient $client,
        int $maxRetries = 2,
        ?Closure $sleeper = null,
        ?Closure $requestIds = null,
        float $timeout = 30.0,
    ): Transport {
        $config = Config::resolve(
            apiKey: self::KEY,
            timeout: $timeout,
            maxRetries: $maxRetries,
            logLevel: LogLevel::Off,
            httpClient: $client,
        );

        $logger = new Logger(LogLevel::Off);

        return new Transport(
            $config,
            new UrlBuilder($config->baseUrl),
            new RetryPolicy($maxRetries, $logger, $sleeper ?? self::noSleep()),
            $logger,
            $requestIds,
        );
    }

    /** @return Closure(int, \Suqo\Cancellation): void */
    protected static function noSleep(): Closure
    {
        return static function (int $delayMs, \Suqo\Cancellation $cancellation): void {
            if ($cancellation->isCancelled()) {
                throw new \Suqo\Exception\CancelledError('Request cancelled by caller.');
            }
        };
    }

    /**
     * @param  list<int> $sink Delays observed, in milliseconds.
     * @return Closure(int, \Suqo\Cancellation): void
     */
    protected static function recordingSleeper(array &$sink): Closure
    {
        return static function (int $delayMs, \Suqo\Cancellation $cancellation) use (&$sink): void {
            $sink[] = $delayMs;

            if ($cancellation->isCancelled()) {
                throw new \Suqo\Exception\CancelledError('Request cancelled by caller.');
            }
        };
    }
}
