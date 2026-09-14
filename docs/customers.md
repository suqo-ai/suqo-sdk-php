# Customers

`$suqo->customers` — `Suqo\Resource\Customers`. Read-only: a customer record is
created implicitly the first time someone subscribes, through
[`subscriptions->create()`](subscriptions.md#create)'s `customer` field.

openapi also declares `customers_create` and `customers_partial_update`. Neither
is exposed: §14 forbids public surface the specification does not describe, and
no §10 row covers them.

## The record

`Suqo\Model\Customer` — `id`, `buyerPhone`, `buyerEmail`, `fullName`, `address`,
`createdAt` (all `?string`), plus `toArray()`.

`id` is a **prefixed public id** (`cus_0390b1820`) — not an integer, and not a
UUID like the subscription ids elsewhere in the API. openapi names the path
parameter `public_id` for that reason.

Every field except `id`, `buyerPhone` and `createdAt` can be `null` in practice;
a record with only a phone number on file is normal. `createdAt` carries
microseconds and a `+05:45` offset rather than `Z`, and is kept as an opaque
string like every other timestamp.

`buyerPhone` and `buyerEmail` keep their wire stems: the `client` → `customer`
rename covers the field named `client`, and N7 makes that table the complete set.
This record is a different shape from the `customer` embedded on a subscription
(`SubscriptionCustomer`, which has `phone`/`fullName`/`email` with no prefix plus
nested `billing`/`shipping`). They both describe a buyer; they are not the same
type, and the SDK never conflates them.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Page
```

`GET /api/v1/customers/` (openapi `customers_list`).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | `?int` | `null` | Omitted from the query when null. |
| `pageSize` | `?int` | `null` | Sent as `page_size`. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Page<Customer>` — `count`, `next`, `previous`, `results`.

**Throws** `AuthenticationError` on 401, `PermissionDeniedError` on 403,
`SuqoError` otherwise. Retried automatically like any other `GET`.

> openapi declares this response as a bare array. The live API returns the
> ordinary `{count, next, previous, results}` envelope, so the declared schema is
> a generation artifact and the SDK decodes the envelope. Verified against a real
> response 2026-09-11.

```php
$page = $suqo->customers->list(pageSize: 50);

echo $page->count, PHP_EOL;

foreach ($page->results as $customer) {
    echo $customer->id, ' ', $customer->buyerPhone, PHP_EOL;
    echo '  ', $customer->fullName ?? '(no name)', PHP_EOL;
    echo '  ', $customer->address ?? '(no address)', PHP_EOL;
}
```

## `autoPaging`

```php
public function autoPaging(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Generator
```

**Returns** `Generator<int, Customer>` — lazy, a page at a time, following the
server's `next` link until it is null. Nothing is accumulated.

Stops after `Pagination::MAX_PAGES` (10 000) with a `SuqoError` if the server never
stops advancing — a `next` link that repeats a page would otherwise iterate forever.
See [errors.md](errors.md#auto-paging-gave-up--base-suqoerror).

```php
foreach ($suqo->customers->autoPaging(cancellation: $token) as $customer) {
    handle($customer);
}
```

## `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): Customer
```

`GET /api/v1/customers/{public_id}/` (openapi `customers_read`).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The public id (`cus_…`) from a list or read. Escaped into the path. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Customer`.

**Throws** `NotFoundError` on 404 — including for an id belonging to another
environment, which looks identical. `AuthenticationError` on 401,
`PermissionDeniedError` on 403.

```php
$customer = $suqo->customers->read('cus_0390b1820');

echo $customer->buyerEmail ?? '-', PHP_EOL;
```

The operation is named `read`, not `retrieve`: N4 takes the name from the
declared operationId (`customers_read`) minus the resource noun.
