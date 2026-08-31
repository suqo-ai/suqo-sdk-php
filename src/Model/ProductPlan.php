<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalProductPlanRead`. An embedded shape, so the `Read` suffix is
 * dropped the way §3 drops it from `ClientRead`.
 */
final class ProductPlan extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $planId,
        public readonly ?string $planName,
        public readonly ?string $description,
        public readonly ?string $billingPeriods,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'plan_id'),
            Wire::nstr($wire, 'plan_name'),
            Wire::nstr($wire, 'description'),
            Wire::nstr($wire, 'billing_periods'),
            $wire,
        );
    }
}
