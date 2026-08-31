<?php

declare(strict_types=1);

namespace Suqo\Exception;

use InvalidArgumentException;

/**
 * §8.1 — raised during construction, before any request exists. It therefore
 * carries no status, request id or body.
 *
 * §8.1 permits this type to sit outside the {@see SuqoError} hierarchy where
 * the host language makes that natural; in PHP a construction-time argument
 * fault is idiomatically an \InvalidArgumentException, so it does.
 */
final class SuqoConfigError extends InvalidArgumentException
{
}
