# SUQO PHP SDK

Official PHP SDK for the [SUQO](https://suqo.ai) API.

This page is the quick start. For a per-method reference — every parameter, return
type and thrown exception — see **[docs/](https://suqo.ai/docs/sdk/php)**. For
code you can run against your own sandbox, see **[examples/](examples/)**.

Requires PHP 8.1+ with `ext-curl`, `ext-json` and `ext-hash`.

```bash
composer require suqo/suqo-php
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

$suqo->subscriptions->cancel('3fa85f64-5717-4562-b3fc-2c963f66afa6');

$suqo->subscriptions->updateBillingCycle(new UpdateBillingCycleParams(
    subscriptionId: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
    nextBillingCycle: '2026-09-03',        // a date, kept as a string
));
```

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

Read-only — a customer record is created implicitly the first time someone
subscribes, through `subscriptions->create()`'s `customer` field.

```php
foreach ($suqo->customers->autoPaging() as $customer) {
    echo $customer->id, ' ', $customer->buyerPhone, ' ', $customer->fullName ?? '-', PHP_EOL;
}

$customer = $suqo->customers->read('cus_0390b1820');
```

`id` is a prefixed public id (`cus_0390b1820`) — not an integer and not a UUID,
unlike the subscription ids elsewhere in the API. Every field except `id`,
`buyerPhone` and `createdAt` can be `null`.

## Not yet exposed

openapi declares these operations, which this SDK does not expose. Each needs a
specification revision first, because §14 forbids public surface the
specification itself does not describe:

| openapi operationId        | Path                                      |
| -------------------------- | ----------------------------------------- |
| `subscriptions_read`       | `GET /api/v1/subscriptions/{id}/`         |
| `subscriptions_resume`     | `POST /api/v1/subscriptions/{id}/resume/` |
| `customers_create`         | `POST /api/v1/customers/`                 |
| `customers_partial_update` | `PATCH /api/v1/customers/{id}/`           |
| `webhooks_*`               | the eight `/api/v1/webhooks/…` operations |

The Webhooks _management_ resource is unexposed; `Suqo\Webhook::verify()` — which
verifies an inbound delivery and needs no network — is unrelated to it and is
fully supported.

## Examples

Four runnable scripts in [examples/](examples/). Each takes the key from
`$SUQO_API_KEY`, so none of them needs editing:

| Script                                                      | What it shows                                                                                                    |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| [list_products.php](examples/list_products.php)             | Walks the catalogue and prints each plan's billing periods. **Run this first** — it is where `pbpId` comes from. |
| [create_subscription.php](examples/create_subscription.php) | Creates a subscription from a `pbpId` and prints the `checkoutUrl` to send the buyer to.                         |
| [list_customers.php](examples/list_customers.php)           | Auto-pages the customer records, then reads one back by its public id.                                           |
| [webhook_handler.php](examples/webhook_handler.php)         | A complete endpoint: verify the signature against the raw bytes, then acknowledge.                               |

```bash
SUQO_API_KEY=su_test_key_… php examples/list_products.php
SUQO_API_KEY=su_test_key_… php examples/create_subscription.php pbp_3n9k2x
```

## Playground

A local web app that drives every operation, with the API key typed into its
homepage — no file in this project needs editing to switch keys or environments.

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
