<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §3 — the surface name for the `Message` schema, renamed because `message` is
 * present on every error.
 */
final class MessageResponse extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly string $message,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(Wire::str($wire, 'message'), $wire);
    }
}
