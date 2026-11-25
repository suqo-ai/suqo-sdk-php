<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Suqo\Http\Transport;

/**
 * §5 — a resource calls the transport and performs the §3 renames. I2: it never
 * constructs an HTTP call itself. I3: it never maps status codes.
 */
abstract class AbstractResource
{
    public function __construct(
        protected readonly Transport $transport,
    ) {
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    protected static function pageQuery(?int $page, ?int $pageSize): array
    {
        return ['page' => $page, 'page_size' => $pageSize];
    }

    /**
     * The records of an unpaginated collection — a bare JSON array. Anything
     * that is not a list of objects reads as none, so an empty account and an
     * unexpected body both decode to `[]`.
     *
     * @return list<array<string, mixed>>
     */
    protected static function bareList(mixed $body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $out = [];

        foreach ($body as $record) {
            if (is_array($record) && ($record === [] || !array_is_list($record))) {
                /** @var array<string, mixed> $record */
                $out[] = $record;
            }
        }

        return $out;
    }
}
