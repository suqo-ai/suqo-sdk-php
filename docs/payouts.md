# Payouts

`$suqo->payouts` — `Suqo\Resource\Payouts`. A payout moves every transaction
currently due to your verified bank account, less gateway fees, the platform fee
and unbilled SMS usage. The bank account itself is managed through
[`bankAccounts`](bank-accounts.md).

Four operations: [`list`](#list), [`autoPaging`](#autopaging),
[`create`](#create) and [`read`](#read).

## The record

`Suqo\Model\Payout` — `payoutId`, `status`, `amount`, `providerCharge`,
`suqoPlatformFee`, `smsCharge`, `netAmount`, `requestedDate`, `paidDate`,
`receiptUrl` (all `?string`), plus `toArray()`.

`payoutId` is a public id (`PYT-MF4G9W-CTEP55`). `status` is `PENDING` until
SUQO has paid the payout out, then `COMPLETED`; it stays a plain string so a
status added server-side needs no SDK release.

Every amount is a decimal string, never a float, at up to **three** decimals
(`"11835.505"`): `netAmount` is `amount` less `providerCharge`,
`suqoPlatformFee` and `smsCharge`. `paidDate` and `receiptUrl` are `null` until
the payout is paid.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Page
```

`GET /api/v1/payouts/` (openapi `payouts_list`) — newest first, 20 per page by
default and 100 at most.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | `?int` | `null` | Omitted from the query when null. |
| `pageSize` | `?int` | `null` | Sent as `page_size`. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Page<Payout>`.

**Throws** `AuthenticationError` on 401, `KycRequiredError` /
`PermissionDeniedError` on 403. Retried automatically like any other `GET`.

## `autoPaging`

```php
public function autoPaging(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Generator
```

**Returns** `Generator<int, Payout>` — lazy, a page at a time, following the
server's `next` link until it is null.

```php
foreach ($suqo->payouts->autoPaging() as $payout) {
    echo $payout->payoutId, ' ', $payout->status, ' ', $payout->netAmount, PHP_EOL;
}
```

## `create`

```php
public function create(?Cancellation $cancellation = null): Payout
```

`POST /api/v1/payouts/` (openapi `payouts_create`) — request a payout of
everything due. Takes no body, so none is sent and no `Content-Type` header is
set.

**Returns** the created `Payout` (201).

**Throws** `ValidationError` on 400 with `detail` and a `code` naming the
reason — read it as `$e->fieldErrors['code'][0]`:

| `code` | Meaning |
| --- | --- |
| `pending_payout_request_exists` | A payout is already pending. |
| `bank_account_not_found` | No bank account on file. |
| `bank_account_not_verified` | The account has not been verified with its OTP. |
| `no_transactions_due` | Nothing to pay out. |
| `payout_below_minimum` | The net amount is under the minimum payout. |

Only one payout may be pending at a time, so a repeat after a success answers
400 rather than paying twice. Being a write, it is not retried for you.

```php
use Suqo\Exception\ValidationError;

try {
    $payout = $suqo->payouts->create();
    echo $payout->payoutId, ' ', $payout->netAmount, PHP_EOL;
} catch (ValidationError $e) {
    echo $e->fieldErrors['code'][0] ?? $e->getMessage(), PHP_EOL;
}
```

## `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): Payout
```

`GET /api/v1/payouts/{public_id}/` (openapi `payouts_read`) — to track whether
a payout is still `PENDING` or `COMPLETED`.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The `payoutId` (`PYT-…`). Escaped into the path. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Payout`. **Throws** `NotFoundError` on 404.

```php
$payout = $suqo->payouts->read('PYT-MF4G9W-CTEP55');

echo $payout->status, ' ', $payout->receiptUrl ?? '(no receipt yet)', PHP_EOL;
```
