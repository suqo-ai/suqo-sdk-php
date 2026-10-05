<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\Customer;
use Suqo\Model\Page;
use Suqo\Pagination;
use Suqo\Params\CustomerCreateParams;
use Suqo\Params\CustomerUpdateParams;

/**
 * §10.3 — the customers resource.
 *
 * A customer record is also created implicitly the first time someone
 * subscribes, through `subscriptions->create()`'s `customer` field;
 * {@see self::create()} records one without opening a subscription.
 *
 * The operation names come from the declared operationIds minus the resource
 * noun (N4): `customers_list` → `list`, `customers_read` → `read`,
 * `customers_create` → `create`, `customers_partial_update` → `update`, plus
 * the auto-paging counterpart §10.1 and §10.2 give every list.
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

    /**
     * §10.3 — POST customers. Records a customer without opening a
     * subscription.
     *
     * The call is an upsert, which is what makes it safe to retry: an email or
     * phone your account already holds corrects that customer and answers 200
     * rather than 201. The SDK decodes both into the same {@see Customer}; read
     * `created_at` or compare `id` if you need to tell the two apart.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(
        CustomerCreateParams $params,
        ?Cancellation $cancellation = null,
    ): Customer {
        $response = $this->transport->request(
            'POST',
            Endpoints::CUSTOMERS,
            $params->toWire(),
            [],
            $cancellation,
        );

        return Customer::fromWire($response->object());
    }

    /**
     * §10.3 — PATCH one customer. Corrects your account's own copy of their
     * name, email or address.
     *
     * Only the fields set on the params object are sent. Passing `''` clears a
     * field, which is why {@see CustomerUpdateParams} keeps null and `''`
     * distinct. The phone identifies the buyer and cannot be changed.
     *
     * @param string $id The public id (`cus_…`), as for {@see self::read()}.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function update(
        string $id,
        CustomerUpdateParams $params,
        ?Cancellation $cancellation = null,
    ): Customer {
        $response = $this->transport->request(
            'PATCH',
            Endpoints::customerRead($id),
            $params->toWire(),
            [],
            $cancellation,
        );

        return Customer::fromWire($response->object());
    }
}
