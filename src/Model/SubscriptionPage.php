<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §11.1 — a page of subscriptions, extended with four wire-named aggregates.
 *
 * @extends Page<Subscription>
 */
final class SubscriptionPage extends Page
{
    /**
     * @param list<Subscription>   $results
     * @param array<string, mixed> $wire
     */
    public function __construct(
        int $count,
        ?string $next,
        ?string $previous,
        array $results,
        public readonly ?int $totalSubscriptions,
        public readonly ?int $activeSubscriptions,
        public readonly ?int $dueSubscriptions,
        public readonly ?int $inactiveSubscriptions,
        array $wire = [],
    ) {
        parent::__construct($count, $next, $previous, $results, $wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromSubscriptionsWire(array $wire): self
    {
        return new self(
            Wire::count($wire, 'count') ?? 0,
            Wire::nstr($wire, 'next'),
            Wire::nstr($wire, 'previous'),
            array_map(
                static fn (array $record): Subscription => Subscription::fromWire($record),
                Wire::objectList($wire, 'results'),
            ),
            Wire::count($wire, 'total_subscriptions'),
            Wire::count($wire, 'active_subscriptions'),
            Wire::count($wire, 'due_subscriptions'),
            Wire::count($wire, 'inactive_subscriptions'),
            $wire,
        );
    }
}
