<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — raised on 422: the request was well-formed, but the resource's
 * current state does not allow it (e.g. a subscription whose plan cannot be
 * changed right now). `getMessage()` is the body's `detail`. Not retried.
 */
final class UnprocessableError extends SuqoError
{
}
