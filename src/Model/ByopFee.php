<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * The processing fee `byop->updateFee()` saved — a percent as a decimal string,
 * or `null` once cleared.
 */
final class ByopFee extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $processingFee,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(Wire::decimal($wire, 'processing_fee'), $wire);
    }
}
