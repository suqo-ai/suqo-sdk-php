<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\Page;
use Suqo\Model\SubSeller;
use Suqo\Pagination;

/**
 * The sub-sellers resource — openapi's `sub-sellers_list`.
 *
 * Sub-seller accounts owned by yours, newest first. Deactivated ones are left
 * out.
 */
final class SubSellers extends AbstractResource
{
    /**
     * GET sub-sellers.
     *
     * @return Page<SubSeller>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Page {
        $response = $this->transport->request(
            'GET',
            Endpoints::SUB_SELLERS,
            null,
            self::pageQuery($page, $pageSize),
            $cancellation,
        );

        return Page::fromWire(
            $response->object(),
            static fn (array $record): SubSeller => SubSeller::fromWire($record),
        );
    }

    /**
     * A lazy sequence of sub-sellers across every page.
     *
     * @return Generator<int, SubSeller>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function autoPaging(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Generator {
        return Pagination::autoPage(
            $this->transport,
            $this->transport->url(Endpoints::SUB_SELLERS, self::pageQuery($page, $pageSize)),
            static fn (array $record): SubSeller => SubSeller::fromWire($record),
            $cancellation,
        );
    }
}
