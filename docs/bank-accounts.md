# Bank accounts

`$suqo->bankAccounts` — `Suqo\Resource\BankAccounts`. The account
[payouts](payouts.md) are paid to — one per seller.

An account starts **unverified**. [`sendOtp`](#sendotp) emails a 6-digit code to
the seller (not to the caller), and [`update`](#update) with that code verifies
it. A payout needs a verified account.

Six operations: [`list`](#list), [`create`](#create), [`read`](#read),
[`update`](#update), [`delete`](#delete) and [`sendOtp`](#sendotp).

## The record

`Suqo\Model\BankAccount` — `id` (`?int`), `bankName`, `accountName`, `branch`,
`accountNumberMasked`, `verifiedAt`, `createdAt`, `updatedAt` (`?string`), plus
`toArray()`.

`id` is an **integer**, unlike the prefixed public ids elsewhere in the API.
`accountNumberMasked` carries the full number while the account is unverified,
so it can be checked before verifying, and is masked (`****4521`) once
`verifiedAt` is set.

## `list`

```php
public function list(?Cancellation $cancellation = null): array
```

`GET /api/v1/bank-accounts/` (openapi `bank-accounts_list`). **Not paginated**:
a bare JSON array, so there is no `Page` and nothing to auto-page.

**Returns** `list<BankAccount>` — `[]` when there are none.

## `create`

```php
public function create(BankAccountParams $params, ?Cancellation $cancellation = null): BankAccount
```

`POST /api/v1/bank-accounts/` (openapi `bank-accounts_create`).

| Parameter | Type | Notes |
| --- | --- | --- |
| `params` | `BankAccountParams` | `bankName`, `accountName`, `accountNumber` (7–21 characters), `branch` — all required. |

**Returns** the new, unverified `BankAccount` (201).

**Throws** `ValidationError` on 400 — invalid fields, or
`code: bank_account_limit_reached` when the account already has one.

```php
use Suqo\Params\BankAccountParams;

$account = $suqo->bankAccounts->create(new BankAccountParams(
    bankName: 'Nabil Bank',
    accountName: 'Himalayan Tea Pvt. Ltd.',
    accountNumber: '0010012345214521',
    branch: 'Kathmandu',
));
```

## `read`

```php
public function read(int $id, ?Cancellation $cancellation = null): BankAccount
```

`GET /api/v1/bank-accounts/{id}/` (openapi `bank-accounts_read`). **Throws**
`NotFoundError` on 404.

## `update`

```php
public function update(
    int $id,
    BankAccountUpdateParams $params,
    ?Cancellation $cancellation = null,
): BankAccount
```

`PATCH /api/v1/bank-accounts/{id}/` (openapi `bank-accounts_partial_update`) —
verify the account with the code from [`sendOtp`](#sendotp), optionally changing
its details in the same call.

| Parameter | Type | Notes |
| --- | --- | --- |
| `params` | `BankAccountUpdateParams` | `code` (`int`) required; `bankName`, `accountName`, `accountNumber`, `branch` optional — only what you set is sent. |

Every edit needs a fresh code and leaves the account verified. The seller is
emailed a confirmation.

**Throws** `ValidationError` on 400 — a wrong or expired code (`fieldErrors['code']`,
and nothing is changed), invalid fields, or `code: bank_account_has_active_payout`
while a payout to this account is pending. `RateLimitError` on 429: code
attempts are throttled.

```php
use Suqo\Params\BankAccountUpdateParams;

$suqo->bankAccounts->sendOtp($account->id);
// …the seller reads the emailed code…
$verified = $suqo->bankAccounts->update($account->id, new BankAccountUpdateParams(code: 123456));
```

## `delete`

```php
public function delete(int $id, ?Cancellation $cancellation = null): void
```

`DELETE /api/v1/bank-accounts/{id}/` (openapi `bank-accounts_delete`). Answers
204. **Throws** `ValidationError` with `code: bank_account_has_active_payout`
while a payout to it is pending.

## `sendOtp`

```php
public function sendOtp(int $id, ?Cancellation $cancellation = null): DetailResponse
```

`POST /api/v1/bank-accounts/{id}/send-otp/` (openapi `bank-accounts_send_otp`)
— email a 6-digit code, valid for 10 minutes, to the seller's verified company
or login email. Sending again replaces the previous code. Takes no body.

**Returns** `DetailResponse` — `detail` is `Verification code sent.`

**Throws** `ValidationError` with `code: no_verified_email` when the seller has
no email to send to; `RateLimitError` on 429.
