<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\Customer;
use Suqo\Model\Page;
use Suqo\Pagination;

/**
 * §10.3 — the customers resource.
 *
 * Read-only: a customer record is created implicitly the first time someone
 * subscribes, through `subscriptions->create()`'s `customer` field. openapi also
 * declares `customers_create` and `customers_partial_update`; those are not
 * exposed here, because §14 still forbids surface the specification does not
 * describe and no §10.3 row covers them.
 *
 * The operation names come from the declared operationIds minus the resource
 * noun (N4): `customers_list` → `list`, `customers_read` → `read`, plus the
 * auto-paging counterpart §10.1 and §10.2 give every list.
 */
final class Customers extends AbstractResource
{
    /**
     * §10.3 — GET customers.
     *
     * @return Page<Customer>
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
            Endpoints::CUSTOMERS,
            null,
            self::pageQuery($page, $pageSize),
            $cancellation,
        );

        return Page::fromWire(
            $response->object(),
            static fn (array $record): Customer => Customer::fromWire($record),
        );
    }

    /**
     * §10.3 — a lazy sequence of customers across every page.
     *
     * @return Generator<int, Customer>
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
            $this->transport->url(Endpoints::CUSTOMERS, self::pageQuery($page, $pageSize)),
            static fn (array $record): Customer => Customer::fromWire($record),
            $cancellation,
        );
    }

    /**
     * §10.3 — GET one customer.
     *
     * @param string $id The public id (`cus_…`) from the `id` field of a list or
     *                   read. Not an integer and not a UUID; openapi names the
     *                   path parameter `public_id` for that reason.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(string $id, ?Cancellation $cancellation = null): Customer
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::customerRead($id),
            null,
            [],
            $cancellation,
        );

        return Customer::fromWire($response->object());
    }
}
