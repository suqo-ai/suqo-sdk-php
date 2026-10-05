# Subscriptions

`$suqo->subscriptions` — `Suqo\Resource\Subscriptions`. Eight methods: list a
page, iterate every page, read one, create, cancel, resume, renew, and move the
next billing date.

**The SDK says `customer`; the wire says `client`.** The rename is applied in both
directions at the serialisation boundary. A raw error body, and anything read back
through `toArray()`, always keeps the wire name.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): SubscriptionPage
```

`GET /api/v1/subscriptions/` — one page of subscriptions, plus four account-level
counters.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | `?int` | `null` | 1-based. Omitted from the query string when null. |
| `pageSize` | `?int` | `null` | Sent as `page_size`. |
| `cancellation` | `?Cancellation` | `null` | See [http.md#cancellation](http.md#cancellation). |

**Returns** `Suqo\Model\SubscriptionPage` — `count`, `next`, `previous`, `results`,
plus `totalSubscriptions`, `activeSubscriptions`, `dueSubscriptions` and
`inactiveSubscriptions` (each `?int`).

**Throws** `SuqoError` and subclasses. A network failure, a 429 or a 5xx is retried
up to `maxRetries` times because this is a `GET`; a 401 or a 404 is not.

```php
use Suqo\SuqoClient;

$suqo = new SuqoClient();

$page = $suqo->subscriptions->list();

echo $page->totalSubscriptions, ' / ', $page->activeSubscriptions, PHP_EOL;

foreach ($page->results as $subscription) {
    echo $subscription->subscriptionId, ' ', $subscription->customer?->email, PHP_EOL;
    echo '  ', $subscription->product?->pbpId, ' ', $subscription->product?->price,
         ' ', $subscription->product?->currency, PHP_EOL;
    echo '  next billing ', $subscription->nextBillingCycle, PHP_EOL;
    echo '  billed to ', $subscription->customer?->billing?->businessName, PHP_EOL;
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

`GET /api/v1/subscriptions/`, then each `next` link. A lazy sequence of
subscriptions across every page; a page is fetched only once the previous one is

Stops after `Pagination::MAX_PAGES` (10 000) with a `SuqoError` if the server never
stops advancing — a `next` link that repeats a page would otherwise iterate forever.
See [errors.md](errors.md#auto-paging-gave-up--base-suqoerror).
exhausted.

**Returns** `Generator<int, Subscription>`. The `customer` ⇄ `client` rename applies
to records yielded lazily exactly as it does to page 1.

**Throws** the same set as `list()`, raised from the `foreach` rather than the call.

Note that the four counters live on `SubscriptionPage` and are therefore *not*
reachable through `autoPaging()`. Call `list()` when you want them.

```php
foreach ($suqo->subscriptions->autoPaging(pageSize: 100) as $subscription) {
    handle($subscription);
}
```

## `create`

```php
public function create(
    CreateSubscriptionParams $params,
    ?Cancellation $cancellation = null,
): CreateSubscriptionResponse
```

`POST /api/v1/subscriptions/` — start a subscription.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `params` | `CreateSubscriptionParams` | — | Required. Serialised by `toWire()`; the surface `customer` field is emitted as `client`. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\CreateSubscriptionResponse` — `subscriptionId`, `pbpId`,
`status`, `checkoutUrl`, `nextBillingCycle`, `createdAt`.

The 201 body is its own schema, not an echo of the request: neither `return_url`
nor `client` comes back. `status` is `pending_checkout` on every documented
response but is decoded through the §9.3 tolerant rule like any other status.

**Throws** `ValidationError` on 400 (with `fieldErrors` populated),
`KycRequiredError` on a 403 with a KYC-shaped body, `PermissionDeniedError` on a
403 without one, `AuthenticationError` on 401, `SuqoError` otherwise. **Not retried** — writes stay unretried pending idempotency
keys, so a `NetworkError` here means the request may or may not have landed.

```php
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerInput;
use Suqo\Params\CustomerShipping;

$created = $suqo->subscriptions->create(new CreateSubscriptionParams(
    pbpId: 'pbp_3n9k2x',
    customer: new CustomerInput(
        phone: '9841000100',
        fullName: 'Ram Shrestha',
        email: 'ram@client.com',
        address: 'Kathmandu, Nepal',
        billing: new CustomerBilling(
            billingBusinessName: 'ABC Pvt Ltd.',
            billingEmail: 'ram@client.com',
            billingAddress: 'Kathmandu, Nepal',
            billingPanVat: '9841000100',
        ),
        shipping: new CustomerShipping(
            phone: '9841000100',
            fullName: 'Ram Shrestha',
            email: 'ram@client.com',
        ),
    ),
    returnUrl: 'https://merchant.example.com/thanks',
));

echo $created->checkoutUrl, PHP_EOL;     // send the buyer here to pay
echo $created->subscriptionId, ' ', $created->status?->value, PHP_EOL;
```

`checkoutUrl` is the point of the call: redirect the buyer there to collect
payment. **The return is not proof of payment** — the real outcome arrives on the
`checkout.succeeded` / `checkout.failed` webhooks.

Anything the server adds beyond the declared schema stays reachable without an
SDK upgrade:

```php
$extra = $created->toArray()['some_new_field'] ?? null;
```

Field-by-field parameter reference:
[models.md#createsubscriptionparams](models.md#createsubscriptionparams).

## `cancel`

```php
public function cancel(string $id, ?Cancellation $cancellation = null): MessageResponse
```

`POST /api/v1/subscriptions/{id}/cancel/` — cancel a subscription. The id is
`rawurlencode`d into the path.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The subscription id. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\MessageResponse` — a single non-null `message` string.

**Throws** `NotFoundError` on 404, `SuqoError` and subclasses otherwise. Not
retried.

openapi declares no request body for this operation, so none is sent and no
`Content-Type` header is set.

```php
$result = $suqo->subscriptions->cancel('3fa85f64-5717-4562-b3fc-2c963f66afa6');

echo $result->message, PHP_EOL;
```

Cancellation is scheduled, not immediate: the subscription stays usable until
the end of the current billing period, and [`resume`](#resume) undoes it until
then. A subscription already cancelled or pending cancellation is returned
unchanged with a 200, so a retry is safe. A one-time purchase has nothing to
cancel and answers 400.

## `updateBillingCycle`

```php
public function updateBillingCycle(
    UpdateBillingCycleParams $params,
    ?Cancellation $cancellation = null,
): MessageResponse
```

`POST /api/v1/subscriptions/update-billing-cycle/` — move the next billing date. The
subscription id travels in the body, not the path.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `params` | `UpdateBillingCycleParams` | — | `subscriptionId` and `nextBillingCycle`, both required. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\MessageResponse`.

**Throws** `ValidationError` on 400, `NotFoundError` on 404, `SuqoError` and
subclasses otherwise. Not retried.

```php
use Suqo\Params\UpdateBillingCycleParams;

$suqo->subscriptions->updateBillingCycle(new UpdateBillingCycleParams(
    subscriptionId: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
    nextBillingCycle: '2026-09-03',        // a date, kept as a string
));
```

`nextBillingCycle` is `YYYY-MM-DD` and stays a string — the SDK does no date
parsing, for the same reason it does no decimal parsing.

## `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): Subscription
```

`GET /api/v1/subscriptions/{id}/` (openapi `subscriptions_read`).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The subscription UUID. Escaped into the path. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Subscription` — the same record shape the list yields, documented
under [The record](#the-record).

**Throws** `NotFoundError` on 404, including for an id belonging to another
environment. Retried automatically like any other `GET`.

```php
$subscription = $suqo->subscriptions->read('3fa85f64-5717-4562-b3fc-2c963f66afa6');

$status = $subscription->status;   // a case, or the raw string, or null
echo $status instanceof SubscriptionStatus ? $status->value : ($status ?? '-'), PHP_EOL;
echo $subscription->customer?->email ?? '-', PHP_EOL;
```

**Nothing on the record says whether it is recurring.** Match
`$subscription->product?->pbpId` against [`products->list()`](products.md#list)
and read that billing period's `intervalType`: `one_time` means the subscription
cannot be cancelled, resumed or rescheduled, and is left out of the counters on
the list response.

## `resume`

```php
public function resume(string $id, ?Cancellation $cancellation = null): MessageResponse
```

`POST /api/v1/subscriptions/{id}/resume/` (openapi `subscriptions_resume`) —
undo a scheduled cancellation and return the subscription to active.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | `string` | — | The subscription id. Escaped into the path. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `MessageResponse`.

**Throws** `NotFoundError` on 404; `ValidationError` on 400 when the
subscription is a one-time purchase, when its product or billing period is no
longer available, or when its status has no resume transition — *already
cancelled* is the common one. Not retried.

An already-active subscription is returned unchanged, so a retry is safe.

openapi declares no request body, so none is sent and no `Content-Type` header
is set.

```php
$suqo->subscriptions->resume('3fa85f64-5717-4562-b3fc-2c963f66afa6');
```

> The 400 here is a **bare list** of messages rather than a field-keyed object —
> there is no request body, so there is no field to key the message on. The SDK
> surfaces the first message as `getMessage()`; `fieldErrors` stays empty. See
> [errors.md](errors.md#validationerror--400).

## `renew`

```php
public function renew(string $subscriptionId, ?Cancellation $cancellation = null): CheckoutSession
```

`POST /api/v1/subscription/renew/` (openapi `subscription_renew_create`) — open
a checkout session for the **next** payment on an existing subscription, reusing
its customer and billing period rather than resubmitting them.

Note the singular noun in the path. That is the API's spelling, and the
subscription is named in the body rather than the path — which is why this takes
a plain string and not a params object.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `subscriptionId` | `string` | — | Sent as `subscription_id` in the body. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `Suqo\Model\CheckoutSession` — `publicId`, `expiresAt`,
`checkoutUrl`. Send the `checkoutUrl` to the buyer to collect payment.

**Throws** `NotFoundError` on 404; `ValidationError` on 400 when the
subscription is cancelled or archived, or is a paid one-time purchase — again a
bare list of messages. Not retried.

```php
$session = $suqo->subscriptions->renew('3fa85f64-5717-4562-b3fc-2c963f66afa6');

echo $session->checkoutUrl, PHP_EOL;
```

See [checkout-sessions.md](checkout-sessions.md) for the session's own
operations, including reading one back while it is open.

## The record

`Suqo\Model\Subscription`:

| Property | Type | Wire key |
| --- | --- | --- |
| `subscriptionId` | `?string` | `subscription_id` |
| `status` | `SubscriptionStatus\|string\|null` | `status` — see below |
| `isActive` | `?bool` | `is_active` |
| `customer` | `?SubscriptionCustomer` | **`client`** |
| `product` | `?SubscriptionProduct` | `product` |
| `currentPeriodStart` | `?string` | `current_period_start` |
| `currentPeriodEnd` | `?string` | `current_period_end` |
| `nextBillingCycle` | `?string` | `next_billing_cycle` |
| `createdAt` | `?string` | `created_at` |

`SubscriptionCustomer`: `phone`, `fullName`, `email`, `address`, plus
`billing` (`?SubscriptionCustomerBilling`: `businessName`, `email`, `address`,
`panVat`) and `shipping` (`?SubscriptionCustomerShipping`: `phone`, `fullName`,
`email`, `address`).

The read shape drops the `billing_` prefix the *write* shape carries: you send
`billingBusinessName` and read back `businessName`. See
[models.md](models.md#the-two-billing-shapes).

`SubscriptionProduct`: `productId`, `name`, `planName`, `pbpId`, `label`, `price`
(a decimal string), `currency`.

### Subscription status

Known values arrive as a `SubscriptionStatus` case; a value the server adds later
arrives as the raw string rather than failing the read.

```php
use Suqo\Model\SubscriptionStatus;

$status = $subscription->status;

if ($status instanceof SubscriptionStatus) {
    match ($status) {
        SubscriptionStatus::Active => activate(),
        SubscriptionStatus::Due    => chase(),
        default                    => null,
    };
} else {
    log("unrecognised status: {$status}");
}
```

Cases: `PendingCheckout` (`pending_checkout`), `Active`, `Due`, `Cancelled`,
`PendingCancellation` (`pending_cancellation`), `Inactive`.

There is no `retrieve()` for a single subscription — `subscriptions_read` exists in
openapi but is not exposed. Filter a `list()` page, or read the record you already
hold.
