<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `ExternalPlanBillingCycleRead`. An embedded shape, so the `Read`
 * suffix is dropped the way §3 drops it from `ClientRead`.
 *
 * This is where `pbpId` lives, and `pbpId` is the required input to
 * {@see \Suqo\Resource\Subscriptions::create()} — so this type is the link
 * between browsing a catalogue and subscribing to it.
 */
final class BillingPeriod extends Model
{
    /**
     * @param list<Offer>          $offers
     * @param array<string, mixed> $wire
     */
    private function __construct(
        public readonly ?string $pbpId,
        public readonly ?string $intervalType,
        public readonly ?int $intervalCount,
        public readonly ?string $label,
        public readonly ?string $price,
        public readonly ?string $currency,
        public readonly ?bool $isCurrent,
        public readonly ?bool $isLimited,
        public readonly ?bool $isArchived,
        public readonly array $offers,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'pbp_id'),
            Wire::nstr($wire, 'interval_type'),
            Wire::nint($wire, 'interval_count'),
            Wire::nstr($wire, 'label'),
            Wire::decimal($wire, 'price'),
            Wire::nstr($wire, 'currency'),
            Wire::nbool($wire, 'is_current'),
            Wire::nbool($wire, 'is_limited'),
            Wire::nbool($wire, 'is_archived'),
            array_map(
                static fn (array $record): Offer => Offer::fromWire($record),
                Wire::objectList($wire, 'offers'),
            ),
            $wire,
        );
    }
}
