<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * §10.2 — parameters for `subscriptions.updateBillingCycle`. openapi:
 * `ExternalUpdateBillingCycle`; both fields are required.
 *
 * `nextBillingCycle` is a date (`YYYY-MM-DD`) and stays a string: the SDK does no
 * date parsing, for the same reason §9.1 keeps decimals as strings.
 */
final class UpdateBillingCycleParams
{
    /**
     * @param array<string, mixed> $extra Merged into the body under wire names, verbatim (N1).
     */
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $nextBillingCycle,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'next_billing_cycle' => $this->nextBillingCycle,
        ] + $this->extra;
    }
}
