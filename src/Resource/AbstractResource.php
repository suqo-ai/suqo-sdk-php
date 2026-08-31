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
}
