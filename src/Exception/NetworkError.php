<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — transport failure or timeout; status 0.
 *
 * §6.5: a timeout surfaces here rather than as its own type, which makes it
 * retryable under §7.2. That is deliberate.
 */
final class NetworkError extends SuqoError
{
}
