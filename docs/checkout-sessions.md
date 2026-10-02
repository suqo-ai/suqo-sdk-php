# Checkout sessions

`$suqo->checkoutSessions` — `Suqo\Resource\CheckoutSessions`. Two methods:
[`create`](#create) opens a session, [`read`](#read) reads an open one back.

A checkout session collects **one payment** for up to ten of your own billing
periods, or for lines you price yourself. It is the route to a payment that is
not a subscription. A subscription's first payment comes from
[`subscriptions->create()`](subscriptions.md#create), and its next from
[`subscriptions->renew()`](subscriptions.md#renew) — both of which hand back a
checkout URL of their own.

## `create`

```php
public function create(
    CreateCheckoutSessionParams $params,
    ?Cancellation $cancellation = null,
): CheckoutSession
```

`POST /api/v1/checkout-sessions/` (openapi `checkout-sessions_create`).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `params` | `CreateCheckoutSessionParams` | — | `items` (1–10) and `returnUrl` required; `customerId` optional. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\CheckoutSession` — `publicId` (`cks_…`), `expiresAt`,
`checkoutUrl`. Send `checkoutUrl` to the buyer.

**Throws** `ValidationError` on 400 (an item, the customer or the return URL was
rejected), `RateLimitError` on 429, `AuthenticationError` on 401,
`KycRequiredError` / `PermissionDeniedError` on 403.

```php
use Suqo\Params\CheckoutItem;
use Suqo\Params\CreateCheckoutSessionParams;

$session = $suqo->checkoutSessions->create(new CreateCheckoutSessionParams(
    items: [CheckoutItem::billingPeriod('pbp_3n9k2x')],
    returnUrl: 'https://partner.example.com/orders/1234',
));

echo $session->checkoutUrl, PHP_EOL;   // send this to the buyer
echo $session->expiresAt, PHP_EOL;
```

### The two item shapes

An item is **either** an existing billing period **or** an inline,
partner-priced line — never a mixture. The API rejects any inline field sent
beside a `pbp_id`, because the plan behind a billing period sets the price, the
cadence and the discount. `CheckoutItem` has one named constructor per shape, so
the invalid combination cannot be expressed:

```php
CheckoutItem::billingPeriod('pbp_3n9k2x');

CheckoutItem::inline(
    name: 'Setup fee',
    amount: '2500.00',                  // a decimal string, never a float
    intervalType: Suqo\IntervalType::OneTime,
    intervalCount: 0,                   // must be 0 for one_time
    discountAmount: '500.00',           // optional
);
```

`intervalCount` is bounded per unit: 1–1095 for `day`, 1–156 for `week`, 1–36
for `month`, 1–3 for `year`, and exactly `0` for `one_time`. `intervalType`
accepts a `Suqo\IntervalType` case or a raw string.

Each item's price is **snapshotted when the session opens**, so a plan edited
afterwards cannot change what the buyer already saw. If two items resolve to the
same product — through different `pbp_id`s, say — only the last one is kept.

### The customer is optional

| `customerId` | What happens |
| --- | --- |
| omitted or `null` | The buyer identifies themselves by OTP at checkout. |
| `cus_…` | Must already exist on your account; see [customers](customers.md#create). |

The SDK sends `customer_id` only when you set it.

### This is the one rate-limited endpoint

20 sessions a minute per account by default. Over that, the API answers 429 and
the SDK raises `RateLimitError` with `retryAfter` set from the response header:

```php
use Suqo\Exception\RateLimitError;

try {
    $session = $suqo->checkoutSessions->create($params);
} catch (RateLimitError $e) {
    sleep((int) ceil($e->retryAfter ?? 1.0));
}
```

**It is not retried for you.** Creating a session is a write, and writes are
never retried while there is no idempotency key — a blind resend would open a
second session. Back off and resend yourself, deliberately.

## `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): CheckoutSessionDetail
```

`GET /api/v1/checkout-sessions/{public_id}/` (openapi `checkout-sessions_read`).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The `public_id` (`cks_…`) from `create`. Escaped into the path. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\CheckoutSessionDetail`:

| Property | Type | Notes |
| --- | --- | --- |
| `publicId` | `?string` | `cks_…` |
| `lineItems` | `array` | Kept exactly as it arrived — openapi describes no members. |
| `customerId` | `?string` | Null when the buyer identifies by OTP. |
| `sellerDetails` | `?CheckoutSeller` | `id`, `phone`, `businessLogo`, `businessName`. |
| `returnUrl` | `?string` | Echoed back verbatim. |
| `expiresAt` | `?string` | |
| `completedAt` | `?string` | Null while the session is open. |
| `isExpired` | `?bool` | openapi types it as a string; both spellings decode. |

**Throws** `NotFoundError` on 404 — see below.

**Only an open session is served.** Once it is paid, or once `expiresAt` has
passed, this answers 404 exactly as an unknown id does, so a `NotFoundError`
here is usually the ordinary end of a session's life rather than a mistake.

That 404 carries the session's own `return_url` beside the message whenever the
session existed, which is enough to send the buyer onwards:

```php
use Suqo\Exception\NotFoundError;

try {
    $detail = $suqo->checkoutSessions->read($publicId);
} catch (NotFoundError $e) {
    $returnUrl = is_array($e->rawBody) ? ($e->rawBody['return_url'] ?? '') : '';
    // $e->getMessage() is the API's own sentence: "This checkout link is no
    // longer valid. Please start a new checkout to continue."
}
```

`rawBody` keeps wire names, so it is `return_url` there and not `returnUrl`.
`fieldErrors` stays empty: it is populated only for a 400.
