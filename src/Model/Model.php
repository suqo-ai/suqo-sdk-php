<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * Base for every read model.
 *
 * Typed accessors cover the documented fields; {@see self::toArray()} exposes the
 * complete decoded payload with wire names intact, which is both the
 * forward-compatibility escape hatch and the read-side counterpart of N8 — a
 * record that arrived as `client` still says `client` here, even though the typed
 * accessor is `customer`.
 */
abstract class Model
{
    /** @param array<string, mixed> $wire */
    protected function __construct(
        private readonly array $wire,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->wire;
    }
}
