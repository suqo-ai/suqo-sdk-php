<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — the caller cancelled; status 0.
 *
 * §7.2: never retried, under any circumstance. Must stay distinguishable from
 * a timeout, which raises {@see NetworkError}.
 */
final class CancelledError extends SuqoError
{
}
