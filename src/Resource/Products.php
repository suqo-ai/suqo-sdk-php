<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\Page;
use Suqo\Model\Product;
use Suqo\Pagination;

/**
 * §10.1 — the products resource.
 */
final class Products extends AbstractResource
{
    /**
     * §10.1 — GET products.
     *
     * @return Page<Product>
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
            Endpoints::PRODUCTS,
            null,
            self::pageQuery($page, $pageSize),
            $cancellation,
        );

        return Page::fromWire(
            $response->object(),
            static fn (array $record): Product => Product::fromWire($record),
        );
    }

    /**
     * §10.1 — a lazy sequence of products across every page.
     *
     * @return Generator<int, Product>
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
            $this->transport->url(Endpoints::PRODUCTS, self::pageQuery($page, $pageSize)),
            static fn (array $record): Product => Product::fromWire($record),
            $cancellation,
        );
    }
}
