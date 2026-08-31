<?php

declare(strict_types=1);

namespace Suqo\Tests\Support;

use Suqo\Cancellation;

/**
 * A token that reports itself cancelled from the Nth observation onwards.
 *
 * PHP is single-threaded, so "cancelled mid-flight" cannot be produced by a
 * concurrent actor. Counting observations is the equivalent: the first checks see
 * a live token, later ones — made from inside the cURL progress callback, or from
 * inside the backoff sleep — see a cancelled one.
 */
final class LatchedCancellation extends Cancellation
{
    private int $observations = 0;

    public function __construct(
        private readonly int $cancelAfter,
    ) {
    }

    public function isCancelled(): bool
    {
        if (parent::isCancelled()) {
            return true;
        }

        return ++$this->observations > $this->cancelAfter;
    }
}
