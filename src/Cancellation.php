<?php

declare(strict_types=1);

namespace Suqo;

/**
 * B3 — the binding's cancellation primitive.
 *
 * PHP has no ambient request context, so cancellation is an explicit token the
 * caller creates, passes to an operation, and cancels from elsewhere (a signal
 * handler, a fiber, a shutdown hook). Per I7 the token is forwarded to the
 * transport and never swallowed.
 *
 * The token is a one-way latch: once cancelled it stays cancelled.
 */
class Cancellation
{
    private bool $cancelled = false;

    /** A token that is never cancelled. */
    public static function none(): self
    {
        return new self();
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * @phpstan-impure The latch is set from elsewhere, so two calls can disagree.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
