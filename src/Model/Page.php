<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * §11.1 — the page shape.
 *
 * @template T
 */
class Page extends Model
{
    /**
     * @param list<T> $results
     */
    public function __construct(
        public readonly int $count,
        public readonly ?string $next,
        public readonly ?string $previous,
        public readonly array $results,
        array $wire = [],
    ) {
        parent::__construct($wire);
    }

    /**
     * @template R
     *
     * @param  array<string, mixed>                  $wire
     * @param  callable(array<string, mixed>): R     $factory
     * @return self<R>
     */
    public static function fromWire(array $wire, callable $factory): self
    {
        return new self(
            Wire::count($wire, 'count') ?? 0,
            Wire::nstr($wire, 'next'),
            Wire::nstr($wire, 'previous'),
            array_map($factory, Wire::objectList($wire, 'results')),
            $wire,
        );
    }
}
