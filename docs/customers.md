# Customers

`$suqo->customers` — `Suqo\Resource\Customers`. **Every method throws.** The
resource exists so that the shape of the client is stable across the version that
implements it: a call site written today keeps compiling when the operations land.

```php
use Suqo\Exception\NotImplementedError;

try {
    $suqo->customers->list();
} catch (NotImplementedError $e) {
    echo $e->getMessage();     // customers API not yet available in this SDK version
    echo $e->status;           // 0 — no request was made
}
```

`NotImplementedError extends SuqoError`, so it is caught by a `catch (SuqoError)`
ladder like any other SDK failure. `status` is `0` and `requestId` is `''`, because
nothing was sent.

The record type is already real: `Suqo\Model\Customer` exists and is not a stub —
`id` (`?int`), `buyerPhone`, `buyerEmail`, `fullName`, `createdAt` (all `?string`),
plus `toArray()`. Only the operations are missing.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): never
```

Reserved for `GET /api/v1/customers/` (openapi `customers_list`). Will return
`Page<Customer>`.

**Throws** `NotImplementedError` immediately. No parameter is validated, no request
is made.

## `autoPaging`

```php
public function autoPaging(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Generator
```

Reserved for the lazy counterpart every list resource gets. Will return
`Generator<int, Customer>`.

**Throws** `NotImplementedError` — and unusually for a generator method, it throws
on *call*, not on first iteration, because the throw precedes any `yield`.

## `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): never
```

Reserved for `GET /api/v1/customers/{id}/` (openapi `customers_read`). Will return a
single `Customer`.

**Throws** `NotImplementedError`.

## Why it is not implemented

The operations are specified in openapi, so the method set above is not a guess — it
is those two operationIds minus the resource noun, plus the auto-paging counterpart.
Shipping them is a specification revision rather than a binding decision: the
specification is normative for behaviour and forbids public surface it does not
itself describe. Recorded in [BINDING.md](../BINDING.md#needs-a-specification-revision),
along with the other operations in the same position (`subscriptions_read`,
`subscriptions_resume`).
