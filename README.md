# SUQO PHP SDK

Official PHP SDK for the [SUQO](https://suqo.ai) API.

This page is the quick start. For a per-method reference — every parameter, return
type and thrown exception — see **[docs/](https://suqo.ai/docs/sdk/php)**. For
code you can run against your own sandbox, see **[examples/](examples/)**.

Requires PHP 8.1+ with `ext-curl`, `ext-json` and `ext-hash`.

```bash
composer require suqo/sdk-php
```

## Quick start

```php
use Suqo\SuqoClient;

$suqo = new SuqoClient();                 // api key from $SUQO_API_KEY
```

The environment is inferred from the key prefix — `su_test_key_` is sandbox,
`su_key_` is live. Passing `environment:` is a _check_, never an override: it can
only agree with the prefix or raise `SuqoConfigError`.

_Full reference: [docs/client.md](docs/client.md)._

```php
$suqo = new SuqoClient(
    apiKey: 'su_test_key_…',
    environment: 'sandbox',   // optional; must agree with the prefix
    timeout: 30.0,            // seconds
    maxRetries: 2,
    logLevel: 'warn',         // or $SUQO_LOG
);
```

## Products

_Full reference: [docs/products.md](docs/products.md). Runnable:
[examples/list_products.php](examples/list_products.php)._

```php
$page = $suqo->products->list(page: 1, pageSize: 50);

echo $page->count;                        // total across all pages

foreach ($page->results as $product) {
    echo $product->productId, ' ', $product->name, PHP_EOL;
    echo '  vat ', $product->vat?->vatPercentage ?? 'n/a',
         ' subscribers ', $product->totalSubscribers, PHP_EOL;

    foreach ($product->plan as $plan) {
        echo '  plan ', $plan->planId, ' ', $plan->planName, PHP_EOL;

        foreach ($plan->billingPeriods as $period) {
            // pbpId is what subscriptions->create() needs.
            echo '    ', $period->pbpId, ' ', $period->label,
                 ' ', $period->price, ' ', $period->currency, PHP_EOL;
        }
    }
}
```

Price is not on the product record — it lives on the plan billing point
(`$plan->billingPeriods[…]->price`), and surfaces again as
`$subscription->product->price` once a subscription exists. `vat` is an object
(`ProductVat`) whose members are null when VAT is switched off, and
`productImage` is a `list<ProductImage>`.

Auto-paging is lazy — a page is fetched only when you exhaust the previous one:

```php
foreach ($suqo->products->autoPaging() as $product) {
    echo $product->name, PHP_EOL;
}
```

## Subscriptions

_Full reference: [docs/subscriptions.md](docs/subscriptions.md). Runnable:
[examples/create_subscription.php](examples/create_subscription.php)._

```php
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerInput;
use Suqo\Params\CustomerShipping;
use Suqo\Params\UpdateBillingCycleParams;

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

// Send the buyer here to pay. The return is not proof of payment — the real
// outcome arrives on the checkout.succeeded / checkout.failed webhooks.
echo $created->checkoutUrl, PHP_EOL;
echo $created->subscriptionId, ' ', $created->status?->value, PHP_EOL;
```

The 201 body is its own schema, not an echo of the request: `subscriptionId`,
`pbpId`, `status`, `checkoutUrl`, `nextBillingCycle`, `createdAt`. Neither
`return_url` nor `client` comes back. Anything the server adds beyond that is
still reachable through `$created->toArray()` without an SDK upgrade.

The SDK says `customer`; the wire says `client`. The rename is applied in both
directions at the serialisation boundary, and raw error bodies always keep the wire
name.

```php
$page = $suqo->subscriptions->list();

echo $page->totalSubscriptions, ' / ', $page->activeSubscriptions, PHP_EOL;

foreach ($page->results as $subscription) {
    echo $subscription->subscriptionId, ' ', $subscription->customer?->email, PHP_EOL;
    echo '  ', $subscription->product?->pbpId, ' ', $subscription->product?->price,
         ' ', $subscription->product?->currency, PHP_EOL;
    echo '  next billing ', $subscription->nextBillingCycle, PHP_EOL;
    echo '  billed to ', $subscription->customer?->billing?->businessName, PHP_EOL;
}

$subscription = $suqo->subscriptions->read('3fa85f64-5717-4562-b3fc-2c963f66afa6');

// Cancellation is scheduled for the end of the current period, and resume
// undoes it until then. Both are safe to repeat.
$suqo->subscriptions->cancel('3fa85f64-5717-4562-b3fc-2c963f66afa6');
$suqo->subscriptions->resume('3fa85f64-5717-4562-b3fc-2c963f66afa6');

// Collect the next payment: a fresh checkout session on the same customer and
// billing period.
$session = $suqo->subscriptions->renew('3fa85f64-5717-4562-b3fc-2c963f66afa6');
echo $session->checkoutUrl, PHP_EOL;

$suqo->subscriptions->updateBillingCycle(new UpdateBillingCycleParams(
    subscriptionId: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
    nextBillingCycle: '2026-09-03',        // a date, kept as a string
));
```

Nothing on a subscription says whether it is recurring. Match
`$subscription->product?->pbpId` against the catalogue and read that billing
period's `intervalType`: `one_time` cannot be cancelled, resumed or rescheduled,
and is left out of the list counters.

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

## Money is a string

Every monetary and decimal value — `price`, `vatPercentage`, `totalSubscribers`
— is a `string`, end to end, and is never parsed into a float inside the SDK.
`vat_percentage` arrives as a JSON _number_ and is still surfaced as a string,
without ever being cast through a float type. Dates are strings for the same
reason, and they carry microseconds and a `+05:45` offset rather than `Z`.

Parse at your own boundary if you need arithmetic:

```php
$total = bcmul($subscription->product->price, '2', 2);
```

## Errors

_Full reference: [docs/errors.md](docs/errors.md)._

Every SDK error derives from `Suqo\Exception\SuqoError` and carries `status`,
`requestId`, `rawBody`, `fieldErrors` and `retryAfter`.

```php
use Suqo\Exception\KycRequiredError;
use Suqo\Exception\RateLimitError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;

try {
    $suqo->subscriptions->create($params);
} catch (ValidationError $e) {
    foreach ($e->fieldErrors as $field => $messages) {
        echo $field, ': ', implode(', ', $messages), PHP_EOL;
    }
} catch (KycRequiredError $e) {
    echo $e->kycStatus;                 // wire `status_code`
} catch (RateLimitError $e) {
    echo $e->retryAfter;                // seconds, from Retry-After
} catch (SuqoError $e) {
    error_log("suqo {$e->status} req={$e->requestId}: {$e->getMessage()}");
}
```

| Type                    | Raised on                                                                |
| ----------------------- | ------------------------------------------------------------------------ |
| `AuthenticationError`   | 401                                                                      |
| `KycRequiredError`      | 403 with a KYC-shaped body                                               |
| `PermissionDeniedError` | 403 without one — a plain authorization failure                          |
| `ValidationError`       | 400                                                                      |
| `NotFoundError`         | 404                                                                      |
| `RateLimitError`        | 429                                                                      |
| `ServerError`           | ≥ 500, and any status not mapped above                                   |
| `NetworkError`          | transport failure, timeout, or a refused URL (status 0)                  |
| `CancelledError`        | caller cancelled (status 0)                                              |
| `NotImplementedError`   | a resource exposed ahead of its operations (status 0) — currently unused |
| `SuqoError`             | the base type every one of the above derives from                        |

`SuqoConfigError extends SuqoError` and is raised during construction, before any
request exists, so it carries no status, request id or body. A single
`catch (SuqoError)` is therefore total across the SDK.

## Retries

_Full reference: [docs/http.md#retrypolicy](docs/http.md#retrypolicy)._

`GET` requests are retried up to twice — three attempts total — on a network
failure, a timeout, a 429 or a 5xx. Writes are not retried, pending idempotency
keys. Backoff is full jitter over 500 ms → 1 s → … capped at 8 s, and a
server-supplied `Retry-After` wins over the computed delay (capped at 60 s).
Absolute-URL page fetches take the same policy as page 1.

## Cancellation

_Full reference: [docs/http.md#cancellation](docs/http.md#cancellation)._

```php
use Suqo\Cancellation;

$token = new Cancellation();

pcntl_signal(SIGTERM, static fn () => $token->cancel());

foreach ($suqo->subscriptions->autoPaging(cancellation: $token) as $subscription) {
    handle($subscription);
}
```

A cancelled token raises `CancelledError`, which is never retried and stays
distinct from the `NetworkError` a timeout produces. It is honoured before an
attempt, mid-flight, during backoff, and between pages.

## Webhooks

_Full reference: [docs/webhooks.md](docs/webhooks.md). Runnable:
[examples/webhook_handler.php](examples/webhook_handler.php)._

Verification needs no client, no API key and no network — call it straight from a
serverless handler. It never throws; every failure path returns `false`.

```php
use Suqo\Webhook;

$verified = Webhook::verify(
    rawBody: file_get_contents('php://input'),   // the exact bytes received
    signature: $_SERVER['HTTP_X_SUQO_SIGNATURE'] ?? null,
    timestamp: $_SERVER['HTTP_X_SUQO_TIMESTAMP'] ?? null,
    secret: getenv('SUQO_WEBHOOK_SECRET'),
);

if (!$verified) {
    http_response_code(400);
    exit;
}
```

Pass the raw bytes. A body re-serialised from a parse will not verify — the
signature covers bytes, not structure. The comparison is constant-time; the
freshness window is 300 s back (configurable via `maxAge`) and a fixed 60 s
forward.

## Injecting an HTTP client

_Full reference: [docs/http.md#injecting-a-client](docs/http.md#injecting-a-client)._

```php
use Suqo\Http\CurlHttpClient;
use Suqo\Http\Psr18HttpClient;

$suqo = new SuqoClient(httpClient: new CurlHttpClient([CURLOPT_PROXY => '…']));

// Or bring your own PSR-18 client. Note that PSR-18 cannot express a per-request
// timeout or mid-flight cancellation; configure the timeout on your client.
$suqo = new SuqoClient(httpClient: new Psr18HttpClient($client, $requestFactory, $streamFactory));
```

Or implement `Suqo\Http\HttpClientInterface` yourself — one method, and the
transport handles everything else.

## Customers

_Full reference: [docs/customers.md](docs/customers.md). Runnable:
[examples/list_customers.php](examples/list_customers.php)._

A customer record is also created implicitly the first time someone subscribes,
through `subscriptions->create()`'s `customer` field; `create()` records one
without opening a subscription.

```php
use Suqo\Params\CustomerCreateParams;
use Suqo\Params\CustomerUpdateParams;

foreach ($suqo->customers->autoPaging() as $customer) {
    echo $customer->id, ' ', $customer->buyerPhone, ' ', $customer->fullName ?? '-', PHP_EOL;
}

$customer = $suqo->customers->read('cus_0390b1820');

// An upsert: an email or phone you already hold is corrected and answered 200
// rather than 201, which is what makes this safe to retry.
$customer = $suqo->customers->create(new CustomerCreateParams(
    email: 'ram@example.com',
    phone: '9810000001',          // Nepali mobile; +977 accepted and stripped
    fullName: 'Ram Bahadur',
));

// Only what you set is sent. null leaves a field alone; '' clears it.
$suqo->customers->update($customer->id, new CustomerUpdateParams(address: ''));
```

You write `phone` and `email`; you read back `buyerPhone` and `buyerEmail`. The
phone identifies the buyer and cannot be changed once set.

`id` is a prefixed public id (`cus_0390b1820`) — not an integer and not a UUID,
unlike the subscription ids elsewhere in the API. Every field except `id`,
`buyerPhone` and `createdAt` can be `null`.

## Checkout sessions

_Full reference: [docs/checkout-sessions.md](docs/checkout-sessions.md).
Runnable: [examples/checkout_session.php](examples/checkout_session.php)._

One payment for up to ten billing periods, or for lines you price yourself —
the route to a payment that is not a subscription.

```php
use Suqo\Params\CheckoutItem;
use Suqo\Params\CreateCheckoutSessionParams;

$session = $suqo->checkoutSessions->create(new CreateCheckoutSessionParams(
    items: [
        CheckoutItem::billingPeriod('pbp_3n9k2x'),
        CheckoutItem::inline(
            name: 'Setup fee',
            amount: '2500.00',                  // a decimal string, never a float
            intervalType: Suqo\IntervalType::OneTime,
            intervalCount: 0,                   // must be 0 for one_time
        ),
    ],
    returnUrl: 'https://merchant.example.com/orders/1234',
    customerId: null,        // omit to let the buyer identify by OTP at checkout
));

echo $session->checkoutUrl, PHP_EOL;            // send this to the buyer

$detail = $suqo->checkoutSessions->read($session->publicId);
```

An item is **either** a `pbp_id` **or** an inline line, never both — the two
named constructors make the mixture unrepresentable. Prices are snapshotted when
the session opens.

Reading a session back works only while it is open: once paid or expired it
answers 404, carrying its own `return_url` in the error body.

This is the one rate-limited endpoint — 20 sessions a minute per account by
default — and, being a write, it is not retried for you.

## Managing webhooks

_Full reference: [docs/webhooks.md#managing-webhooks](docs/webhooks.md#managing-webhooks).
Runnable: [examples/manage_webhooks.php](examples/manage_webhooks.php)._

`$suqo->webhooks` registers and edits the endpoints SUQO delivers to. It is a
different thing from `Webhook::verify()` above, which checks a delivery that has
already arrived.

```php
use Suqo\Model\WebhookEvent;
use Suqo\Params\WebhookParams;
use Suqo\Params\WebhookUpdateParams;

$webhook = $suqo->webhooks->create(new WebhookParams(
    event: WebhookEvent::CheckoutSucceeded,
    endpointUrl: 'https://merchant.example.com/hooks/suqo',
));

$suqo->webhooks->testDelivery($webhook->id);                       // 202, async
$suqo->webhooks->update($webhook->id, new WebhookUpdateParams(isActive: false));
$suqo->webhooks->delete($webhook->id);

// The secret every delivery is signed with — minted on first read.
$secret = $suqo->webhooks->secret()->signingSecret;
```

One webhook per event per account. The endpoint must use https and resolve only
to public addresses, checked both when you register it and again at delivery
time. `list()` is the API's one unpaginated collection: a bare array.

## Examples

Seven runnable scripts in [examples/](examples/). Each takes the key from
`$SUQO_API_KEY`, so none of them needs editing:

| Script                                                      | What it shows                                                                                                    |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| [list_products.php](examples/list_products.php)             | Walks the catalogue and prints each plan's billing periods. **Run this first** — it is where `pbpId` comes from. |
| [create_subscription.php](examples/create_subscription.php) | Creates a subscription from a `pbpId` and prints the `checkoutUrl` to send the buyer to.                         |
| [list_customers.php](examples/list_customers.php)           | Auto-pages the customer records, then reads one back by its public id.                                           |
| [webhook_handler.php](examples/webhook_handler.php)         | A complete endpoint: verify the signature against the raw bytes, then acknowledge.                               |
| [checkout_session.php](examples/checkout_session.php)       | Opens a checkout session from a `pbpId` plus an inline line, prints the pay URL, reads it back.                   |
| [manage_subscription.php](examples/manage_subscription.php) | Reads one subscription, tells recurring from one-time, cancels, resumes, and renews it.                          |
| [manage_webhooks.php](examples/manage_webhooks.php)         | Reads the signing secret, registers an endpoint, sends a test delivery, pauses it, deletes it.                   |

```bash
SUQO_API_KEY=su_test_key_… php examples/list_products.php
SUQO_API_KEY=su_test_key_… php examples/create_subscription.php pbp_3n9k2x
SUQO_API_KEY=su_test_key_… php examples/checkout_session.php pbp_3n9k2x
SUQO_API_KEY=su_test_key_… php examples/manage_webhooks.php https://example.com/hooks/suqo
```

## Playground

A local web app that drives the products, subscriptions, customers and webhook
flows, with the API key typed into its homepage — no file in this project needs
editing to switch keys or environments.

```bash
composer playground        # http://127.0.0.1:8000
```

See [examples/playground/README.md](examples/playground/README.md).

## Development

```bash
composer install
composer test        # PHPUnit: the §13 conformance suite
composer stan        # PHPStan, level max
composer lint        # PHP-CS-Fixer, dry run
composer invariants  # the §5.1 I1/I4/I6 grep gate
composer ci          # all of the above
```

## Licence

MIT.
