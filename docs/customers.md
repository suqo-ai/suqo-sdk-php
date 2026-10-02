# Customers

`$suqo->customers` — `Suqo\Resource\Customers`. A customer record is also
created implicitly the first time someone subscribes, through
[`subscriptions->create()`](subscriptions.md#create)'s `customer` field;
[`create`](#create) records one without opening a subscription.

Four operations: [`list`](#list), [`autoPaging`](#autopaging), [`read`](#read),
[`create`](#create) and [`update`](#update).

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

## `create`

```php
public function create(
    CustomerCreateParams $params,
    ?Cancellation $cancellation = null,
): Customer
```

`POST /api/v1/customers/` (openapi `customers_create`).

Records a customer on your account without opening a subscription.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `params` | `CustomerCreateParams` | — | `email` required; `phone`, `fullName`, `address` optional. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Customer`.

**Throws** `ValidationError` on 400 — a missing email, a rejected phone, or
`{"email": ["You cannot add yourself as a customer."]}`. `AuthenticationError`
on 401, `KycRequiredError` / `PermissionDeniedError` on 403.

**The call is an upsert, and that is what makes it safe to retry.** An email or
phone your account already holds corrects that customer and answers **200**
instead of 201. The SDK decodes both into the same `Customer`; nothing in the
return value distinguishes them, so compare `id` or `createdAt` if you need to
know which happened.

`phone` must be a Nepali mobile number — ten digits starting 96, 97 or 98. A
`+977` country code, a leading zero, spaces and dashes are accepted and stripped
server-side. It identifies the buyer and cannot be changed afterwards.

The name and address are **your account's own copy** of the buyer's details, not
a shared profile: they are not visible to other sellers.

```php
use Suqo\Params\CustomerCreateParams;

$customer = $suqo->customers->create(new CustomerCreateParams(
    email: 'ram@example.com',
    phone: '9810000001',
    fullName: 'Ram Bahadur',
    address: 'Kathmandu, Nepal',
));

echo $customer->id, PHP_EOL;          // cus_2a108b4eb
```

What you send and what you read back are spelled differently: `phone` and
`email` go out under those names and come back as `buyerPhone` and `buyerEmail`.

## `update`

```php
public function update(
    string $id,
    CustomerUpdateParams $params,
    ?Cancellation $cancellation = null,
): Customer
```

`PATCH /api/v1/customers/{public_id}/` (openapi `customers_partial_update`).

Corrects your account's copy of a customer's name, email or address.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The public id (`cus_…`). Escaped into the path. |
| `params` | `CustomerUpdateParams` | — | Every field optional; only what you set is sent. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Customer`.

**Throws** `NotFoundError` on 404, `ValidationError` on 400 — including
`{"phone": ["Phone cannot be changed."]}` when `phone` is sent at anything but
its current value.

**`null` and `''` are different instructions.** Leaving a field `null` omits it
from the body; passing `''` sends it and clears it.

```php
use Suqo\Params\CustomerUpdateParams;

// Rename, leave everything else alone.
$suqo->customers->update('cus_2a108b4eb', new CustomerUpdateParams(
    fullName: 'Ram Bahadur',
));

// Clear the address.
$suqo->customers->update('cus_2a108b4eb', new CustomerUpdateParams(address: ''));
```

The phone identifies the buyer and cannot be changed, so `phone` is accepted
only at its current value. It exists on the params object so that a round trip
through `toArray()` can resend it unchanged; leave it null in ordinary use.
