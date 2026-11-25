<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `byop.updateFee` (openapi: `byop_fees_partial_update`).
 *
 * `processingFee` is always sent: a percent as a decimal string (`'1.75'`,
 * 0 to 100, two decimals), or `null` to clear it — which is not the same as
 * leaving it out, so there is no "unset" here.
 */
final class ByopFeeParams
{
    /**
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly ?string $processingFee,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return ['processing_fee' => $this->processingFee] + $this->extra;
    }
}
