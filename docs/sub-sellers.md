# Sub-sellers

`$suqo->subSellers` — `Suqo\Resource\SubSellers`. Sub-seller accounts owned by
yours, newest first. Deactivated ones are left out.

Two operations: [`list`](#list) and [`autoPaging`](#autopaging).

> **Not yet exposed.** The API can also register a sub-seller
> (`POST /api/v1/sub-sellers/registration/`) and resubmit its KYC
> (`PATCH /api/v1/sub-sellers/{usr_id}/kyc-resubmit/`), and lets a parent act as
> a sub-seller by sending its `id` in a `Suqo-Account` header. The first two
> take `multipart/form-data` with file uploads and the third is a per-request
> header; the transport sends JSON with a fixed header set today, so these wait
> on a transport change rather than being bolted on here.

## The record

`Suqo\Model\SubSeller` — `id`, `email`, `legalBusinessName`, `createdAt`
(`?string`), `kyc` (`?SubSellerKyc`), plus `toArray()`.

`id` is a prefixed public id (`usr_…`). `Suqo\Model\SubSellerKyc` — `status`,
`rejectionReason`, `resubmittedAt`, `verifiedAt` (`?string`):

| `status` | Meaning |
| --- | --- |
| `KYC_VERIFICATION_PENDING` | Under review. |
| `KYC_VERIFIED` | Approved. |
| `KYC_RE_VERIFICATION` | Rejected; `rejectionReason` (plain text) says why. |

`status` stays a string so a value added server-side needs no SDK release.
`rejectionReason` is `null` unless the status is `KYC_RE_VERIFICATION`.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Page
```

`GET /api/v1/sub-sellers/` (openapi `sub-sellers_list`) — 20 per page by default
and 100 at most.

**Returns** `Page<SubSeller>`. **Throws** `AuthenticationError` on 401,
`KycRequiredError` / `PermissionDeniedError` on 403 — your own KYC must be
verified.

## `autoPaging`

```php
public function autoPaging(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Generator
```

**Returns** `Generator<int, SubSeller>`.

```php
foreach ($suqo->subSellers->autoPaging() as $sub) {
    echo $sub->id, ' ', $sub->email, ' ', $sub->kyc?->status ?? '-', PHP_EOL;

    if ($sub->kyc?->rejectionReason !== null) {
        echo '  rejected: ', $sub->kyc->rejectionReason, PHP_EOL;
    }
}
```
