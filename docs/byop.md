# BYOP

`$suqo->byop` — `Suqo\Resource\Byop`. **Bring your own payment**: on a BYOP
account a payment method collects into your own gateway account rather than
SUQO's.

Switching an account to BYOP is done by SUQO, not through this API; until it
is, the two writes answer 403 with `Your own payment account isn't enabled.`

Three operations: [`paymentMethods`](#paymentmethods),
[`updateCredentials`](#updatecredentials) and [`updateFee`](#updatefee).

## `paymentMethods`

```php
public function paymentMethods(?Cancellation $cancellation = null): array
```

`GET /api/v1/byop/payment-methods/` (openapi `byop_payment-methods_list`) —
every payment method on your account, hidden ones included. **Not paginated**: a
bare JSON array. Read-only; show, hide and reorder stay on the dashboard.

**Returns** `list<PaymentMethod>`. `Suqo\Model\PaymentMethod`:

| Property | Type | Notes |
| --- | --- | --- |
| `paymentTypeId` | `?string` | A UUID — the id the two writes take. |
| `paymentType` | `?string` | e.g. `esewa`. |
| `alias`, `logo` | `?string` | |
| `companyId` | `?string` | Arrives as a number; kept as its string spelling. |
| `order` | `?int` | |
| `isActive` | `?bool` | `false` for a hidden method. |
| `fee` | `?string` | Percent, decimal string. Your own `processing_fee` on BYOP (`0.00` when unset); `null` for Khalti/NPS (they report a fee per payment) and for cash. |
| `isConfigured` | `?bool` | True once credentials have been saved. |
| `isVerified` | `?bool` | The background check on them: `true` accepted, `false` refused, `null` not run yet. |

## `updateCredentials`

```php
public function updateCredentials(
    string $paymentTypeId,
    ByopCredentialsParams $params,
    ?Cancellation $cancellation = null,
): DetailResponse
```

`PATCH /api/v1/byop/credentials/{payment_method_id}/` (openapi
`byop_credentials_partial_update`) — save your gateway credentials for one
payment method.

`ByopCredentialsParams` takes the gateway's fields as an array of wire names,
sent verbatim — they differ per gateway, and the list is in the API reference.
Send the **full set** every time: it replaces what is stored, and unknown keys
are refused. Text and secret values are capped at 500 characters; a `file`
field is a base64 string under 2.5 MB; a `checkbox` field must be `true`.

**Returns** `DetailResponse` — `Credentials saved successfully.` Credentials are
never returned. Each save is checked against the gateway in the background:
read `isVerified` on [`paymentMethods`](#paymentmethods). Replacing saved
credentials emails your company address.

**Throws** `ValidationError` on 400 — a field failed validation (keyed by its
dotted name, never echoing the value), `code: byop_payment_pending` while a
payment on this gateway is still processing, or `payment_method` when the method
cannot be BYOP. `PermissionDeniedError` on 403 when the account is not on BYOP.
`NotFoundError`, `RateLimitError`.

```php
use Suqo\Params\ByopCredentialsParams;

$suqo->byop->updateCredentials($method->paymentTypeId, new ByopCredentialsParams([
    'product_code' => 'EPAYTEST',
    'merchant_secret' => getenv('ESEWA_SECRET'),
]));
```

## `updateFee`

```php
public function updateFee(
    string $paymentTypeId,
    ByopFeeParams $params,
    ?Cancellation $cancellation = null,
): ByopFee
```

`PATCH /api/v1/byop/fees/{payment_method_id}/` (openapi
`byop_fees_partial_update`) — the percent your own gateway takes from each
payment on this method. It is used as the gateway fee when a BYOP payment
settles, `0` when unset.

`ByopFeeParams(processingFee: '1.75')` — a decimal string, 0 to 100 with two
decimals. `null` **clears** the fee and is sent explicitly, so it is not the
same as leaving the field out.

**Returns** `Suqo\Model\ByopFee` — `processingFee` (`?string`).

**Throws** `ValidationError` on 400 — out of range, or refused for Khalti and
NPS, which report their own fee on each payment. `PermissionDeniedError` on 403
when the account is not on BYOP.

```php
use Suqo\Params\ByopFeeParams;

echo $suqo->byop->updateFee($method->paymentTypeId, new ByopFeeParams('1.75'))->processingFee;
```
