# SUQO PHP SDK

Official PHP SDK for the [SUQO](https://suqo.ai) API. Implements **SUQO SDK —
Specification v2.0**; binding decisions are recorded in [BINDING.md](BINDING.md).

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
`su_key_` is live. Passing `environment:` is a *check*, never an override: it can
only agree with the prefix or raise `SuqoConfigError`.

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

```php
$page = $suqo->products->list(page: 1, pageSize: 50);

echo $page->count;                        // total across all pages

foreach ($page->results as $product) {
    echo $product->productId, ' ', $product->name, PHP_EOL;
    echo '  vat ', $product->vat, ' subscribers ', $product->totalSubscribers, PHP_EOL;

    foreach ($product->plan as $plan) {
        echo '  plan ', $plan->planId, ' ', $plan->planName, PHP_EOL;
    }
}
```

Price is not on the product record — it lives on the plan billing point, and
surfaces as `$subscription->product->price` once a subscription exists.

Auto-paging is lazy — a page is fetched only when you exhaust the previous one:

```php
foreach ($suqo->products->autoPaging() as $product) {
    echo $product->name, PHP_EOL;
}
```

## Subscriptions

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

echo $created->pbpId, ' ', $created->returnUrl;
```

The API declares the 201 body as the same schema as the request, so the response
echoes what was sent. Anything the server adds beyond that — a checkout URL, say —
is reachable through `$created->toArray()` without an SDK upgrade.

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

Every monetary and decimal value — `price`, `vat`, `totalSubscribers` — is a
`string`, end to end, and is never parsed into a float inside the SDK. Dates are
strings for the same reason. Parse at your own boundary if you need arithmetic:

```php
$total = bcmul($subscription->product->price, '2', 2);
```

## Errors

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

| Type | Raised on |
| --- | --- |
| `AuthenticationError` | 401 |
| `KycRequiredError` | 403 with a KYC-shaped body |
| `ValidationError` | 400 |
| `NotFoundError` | 404 |
| `RateLimitError` | 429 |
| `ServerError` | ≥ 500 |
| `NetworkError` | transport failure or timeout (status 0) |
| `CancelledError` | caller cancelled (status 0) |
| `NotImplementedError` | unimplemented resource (status 0) |
| `SuqoError` | base type; also unmapped statuses |

`SuqoConfigError extends \InvalidArgumentException` and is raised during
construction, before any request exists.

## Retries

`GET` requests are retried up to twice — three attempts total — on a network
failure, a timeout, a 429 or a 5xx. Writes are not retried, pending idempotency
keys. Backoff is full jitter over 500 ms → 1 s → … capped at 8 s, and a
server-supplied `Retry-After` wins over the computed delay (capped at 60 s).
Absolute-URL page fetches take the same policy as page 1.

## Cancellation

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

## Not yet exposed

openapi.yaml declares four operations this SDK deliberately does not expose,
because §14 of the specification forbids public surface the specification itself
does not describe. Each needs a specification revision first:

| openapi operationId | Path |
| --- | --- |
| `subscriptions_read` | `GET /api/v1/subscriptions/{id}/` |
| `subscriptions_resume` | `POST /api/v1/subscriptions/{id}/resume/` |
| `customers_list` | `GET /api/v1/customers/` |
| `customers_read` | `GET /api/v1/customers/{id}/` |

`$suqo->customers` exists and raises `NotImplementedError` (§10.3) so the shape of
the client stays stable across the version that implements it. The record type
`Suqo\Model\Customer` is already un-stubbed, since §9.4 ties that to openapi.yaml
rather than to §10.3.

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
