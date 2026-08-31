<?php

declare(strict_types=1);

namespace Suqo\Http;

use Closure;
use Suqo\Cancellation;
use Suqo\Constants;
use Suqo\Exception\CancelledError;
use Suqo\Exception\SuqoError;
use Suqo\Logging\Logger;

/**
 * §7 — the retry policy. Wraps the transport and adds no HTTP knowledge of its
 * own (§5).
 */
final class RetryPolicy
{
    private readonly Closure $sleeper;

    /**
     * @param Closure(int, Cancellation): void|null $sleeper Injection point for
     *        tests only; never a public option. Must honour cancellation.
     */
    public function __construct(
        private readonly int $maxRetries,
        private readonly Logger $logger,
        ?Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? Closure::fromCallable([self::class, 'interruptibleSleep']);
    }

    /**
     * §7.4 — the retry loop.
     *
     * @template T
     *
     * @param  callable(): T $attempt
     * @return T
     *
     * @throws SuqoError
     */
    public function execute(string $method, Cancellation $cancellation, callable $attempt): mixed
    {
        $lastError = null;

        for ($n = 0; $n <= $this->maxRetries; $n++) {
            if ($cancellation->isCancelled()) {
                throw new CancelledError('Request cancelled by caller.');
            }

            try {
                return $attempt();
            } catch (CancelledError $e) {
                throw $e;
            } catch (SuqoError $e) {
                if (!self::isEligible($method, $e)) {
                    throw $e;
                }

                $lastError = $e;
            }

            if ($n === $this->maxRetries) {
                break;
            }

            $delay = self::computeDelayMs($n, $lastError);
            $this->logger->warn('retry', ['attempt' => $n + 1, 'delay' => $delay]);
            ($this->sleeper)($delay, $cancellation);
        }

        // Unreachable with maxRetries >= 0 unless every attempt failed.
        throw $lastError ?? new SuqoError('Request failed with no attempt recorded.');
    }

    /**
     * §7.2 — an attempt is retried only when both conditions hold. CancelledError
     * is excluded unconditionally.
     */
    public static function isEligible(string $method, SuqoError $error): bool
    {
        if ($error instanceof CancelledError) {
            return false;
        }

        // I6: one decision, in one place. When idempotency keys ship, the
        // constant behind Constants::writesRetryable() flips and writes join the
        // eligible set. Nothing here changes.
        $eligibleMethods = Constants::writesRetryable()
            ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
            : ['GET'];

        if (!in_array(strtoupper($method), $eligibleMethods, true)) {
            return false;
        }

        $status = $error->status;

        return $status === 0 || $status === 429 || $status >= 500;
    }

    /**
     * §7.3 — compute_delay, in milliseconds. `$attempt` is 0-based.
     *
     * A server-supplied Retry-After takes precedence over computed backoff and is
     * not jittered. Otherwise the delay is full jitter: a uniform draw across
     * [0, cap), not the cap itself.
     */
    public static function computeDelayMs(int $attempt, SuqoError $error): int
    {
        if ($error->retryAfter !== null) {
            return (int) min((int) round($error->retryAfter * 1000), Constants::RETRY_AFTER_CAP_MS);
        }

        $cap = (int) min(
            Constants::MAX_DELAY_MS,
            Constants::BASE_DELAY_MS * (Constants::FACTOR ** $attempt),
        );

        if ($cap <= 0) {
            return 0;
        }

        return random_int(0, $cap - 1);
    }

    /**
     * §7.4 — the sleep MUST be interruptible: a cancellation arriving mid-backoff
     * raises CancelledError rather than completing the wait.
     */
    private static function interruptibleSleep(int $delayMs, Cancellation $cancellation): void
    {
        $sliceUs = 5_000;
        $remainingUs = $delayMs * 1000;

        while ($remainingUs > 0) {
            if ($cancellation->isCancelled()) {
                throw new CancelledError('Request cancelled by caller.');
            }

            $step = min($sliceUs, $remainingUs);
            usleep($step);
            $remainingUs -= $step;
        }

        if ($cancellation->isCancelled()) {
            throw new CancelledError('Request cancelled by caller.');
        }
    }
}
