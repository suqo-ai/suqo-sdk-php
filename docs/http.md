# The HTTP layer

Everything below sits under the resources. You need none of it for ordinary use;
you need some of it to inject a client, to cancel work, or to build a resource of
your own.

The layer is deliberately narrow. There is exactly one HTTP call site in the SDK
(inside `Transport`), cURL appears in exactly one file, and URL literals appear in
exactly one file — asserted by `composer invariants` in CI.

## Cancellation

`Suqo\Cancellation` is a one-way latch. It is the SDK's cancellation primitive
because PHP has no ambient one, and it is the last parameter of every request
method.

| Method | Signature | Notes |
| --- | --- | --- |
| `Cancellation::none()` | `static (): self` | A fresh, never-cancelled token. What every method uses when you pass `null`. |
| `cancel()` | `(): void` | Trips the latch. Idempotent, and cannot be untripped. |
| `isCancelled()` | `(): bool` | |

The class is not `final`, so you can subclass it — for a token that trips on a
deadline, say.

```php
use Suqo\Cancellation;

$token = new Cancellation();

pcntl_signal(SIGTERM, static fn () => $token->cancel());

foreach ($suqo->subscriptions->autoPaging(cancellation: $token) as $subscription) {
    handle($subscription);
}
```

A cancelled token raises `CancelledError`, which is never retried and stays distinct
from the `NetworkError` a timeout produces. It is honoured at four points: before an
attempt starts, mid-flight (via the cURL progress callback), during retry backoff,
and at each page boundary.

## `Pagination::autoPage`

```php
Suqo\Pagination::autoPage(
    Transport $transport,
    string $firstUrl,
    callable $factory,
    ?Cancellation $cancellation = null,
): Generator
```

The lazy generator every `autoPaging()` delegates to. A PHP `Generator` is the
binding's lazy-iteration primitive: a page is fetched only once the consumer has
exhausted the previous one, and nothing is accumulated.

| Parameter | Type | Notes |
| --- | --- | --- |
| `transport` | `Transport` | |
| `firstUrl` | `string` | Absolute. Built with `Transport::url()`. |
| `factory` | `callable(array): T` | Builds one record, and is where the read-direction rename happens. |
| `cancellation` | `?Cancellation` | Checked before each page fetch. |

**Returns** `Generator<int, T>`. **Throws** `SuqoError` and subclasses from
iteration, and `CancelledError` if the token trips between pages.

Page 1 is fetched through the same absolute-URL `GET` as every later page, so the
retry policy is identical throughout. Iteration stops when the page's `next` is
absent or not a string.

## Injecting a client

The default is cURL. Swap it at construction:

```php
use Suqo\Http\CurlHttpClient;
use Suqo\Http\Psr18HttpClient;
use Suqo\SuqoClient;

$suqo = new SuqoClient(httpClient: new CurlHttpClient([CURLOPT_PROXY => '…']));

// Or bring your own PSR-18 client.
$suqo = new SuqoClient(httpClient: new Psr18HttpClient($client, $requestFactory, $streamFactory));
```

### `HttpClientInterface::send`

```php
public function send(HttpRequest $request): HttpResponse;
```

The whole interface — one method. Implement it and the transport handles headers,
serialisation, error mapping, retries and pagination.

**Returns** an `HttpResponse` for **any** HTTP status, 5xx included. Do not throw on
a status; the transport maps statuses.

**Throws** — and only these two:

| Exception | Raise it when |
| --- | --- |
| `Http\HttpCancelledException` | the request stopped because the token was cancelled → becomes `CancelledError` |
| `Http\HttpClientException` | any other transport failure, timeout included → becomes `NetworkError` |

Both extend `\RuntimeException`; `HttpCancelledException` extends
`HttpClientException`, so catch the cancelled one first. Anything else escaping
`send()` propagates raw and unmapped, so wrap your client's own exceptions.

```php
use Suqo\Http\HttpClientException;
use Suqo\Http\HttpClientInterface;
use Suqo\Http\HttpRequest;
use Suqo\Http\HttpResponse;

final class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(private readonly HttpClientInterface $inner)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        error_log("{$request->method} {$request->url}");

        return $this->inner->send($request);
    }
}

$suqo = new SuqoClient(httpClient: new LoggingHttpClient(new CurlHttpClient()));
```

### `HttpRequest`

Readonly, constructed by the transport and handed to your client.

| Property | Type | Notes |
| --- | --- | --- |
| `method` | `string` | Upper-case. |
| `url` | `string` | Absolute, query string included. |
| `headers` | `array<string, string>` | Name ⇒ value. |
| `body` | `?string` | Already-serialised JSON, or `null`. `null` means send no body and set no `Content-Type`. |
| `timeout` | `float` | Seconds, for this attempt. |
| `cancellation` | `Cancellation` | Poll it if your client can abort mid-flight. |

### `HttpResponse`

```php
public function __construct(int $status, array $headers, string $body)
```

Header names are lower-cased on construction, so `$response->headers` is always
lower-case keyed.

| Member | Type | Notes |
| --- | --- | --- |
| `status` | `int` | |
| `headers` | `array<string, string>` | Lower-cased names. |
| `body` | `string` | Raw bytes. The transport parses. |
| `header(string $name): ?string` | | Case-insensitive lookup. |

### `CurlHttpClient`

```php
public function __construct(array $curlOptions = [])
```

The default client. Options you pass are applied first, then the SDK's own
overwrite anything it must control: URL, method, `RETURNTRANSFER`,
`FOLLOWLOCATION` (off — redirects are not followed), `NOSIGNAL`, both timeouts,
headers, body, the header collector and the progress callback. So a proxy, a CA
bundle or an interface binding works; overriding `CURLOPT_TIMEOUT_MS` does not.

Cancellation is honoured mid-flight through `CURLOPT_PROGRESSFUNCTION`.

| Constant | Value | Meaning |
| --- | --- | --- |
| `CurlHttpClient::ABORTED_BY_CALLBACK` | `42` | cURL's code for a progress-callback abort — i.e. cancellation. |
| `CurlHttpClient::OPERATION_TIMEDOUT` | `28` | cURL's timeout code. |

Both are exposed so a custom client can report the same codes.

### `Psr18HttpClient`

```php
public function __construct(
    ClientInterface $client,
    RequestFactoryInterface $requestFactory,
    StreamFactoryInterface $streamFactory,
)
```

Adapts any PSR-18 client. Requires `psr/http-client` and `psr/http-factory`, which
are suggested rather than required dependencies.

Two caveats, both inherent to PSR-18:

- **No per-request timeout.** PSR-18 cannot express one, so `$request->timeout` is
  ignored — configure the timeout on your own client.
- **Cancellation only at boundaries.** The token is checked before the request and
  after it returns, never mid-flight.

Any `Throwable` from the injected client becomes `HttpClientException`, or
`HttpCancelledException` if the token was tripped.

## `Transport`

`Suqo\Http\Transport` — the single HTTP call site. Status-code mapping happens here
and never in a resource. Public so a custom resource can use it; you get one already
built inside `SuqoClient`, though the property is private, so a custom resource
means building your own `Transport`.

```php
public function __construct(
    Config $config,
    UrlBuilder $urls,
    RetryPolicy $retry,
    Logger $logger,
    ?Closure $requestIdFactory = null,
)
```

`$requestIdFactory` is a test seam, not a public option.

### `Transport::request`

```php
public function request(
    string $method,
    string $path,
    ?array $body = null,
    array $query = [],
    ?Cancellation $cancellation = null,
): TransportResponse
```

A request against a path from the endpoint table, wrapped in the retry policy.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `method` | `string` | — | `'GET'`, `'POST'`, … |
| `path` | `string` | — | Relative, from `Endpoints`. The trailing slash is added for you. |
| `body` | `?array` | `null` | `null` sends no body and sets no `Content-Type`. An empty array serialises as `{}`, not `[]`. |
| `query` | `array<string, string\|int\|float\|bool\|null>` | `[]` | Null values are dropped. |
| `cancellation` | `?Cancellation` | `null` | |

**Returns** `TransportResponse`. **Throws** `SuqoError` and subclasses; a status
`>= 400` is mapped by `ErrorMapper` and thrown.

Per attempt it sets:

| Header | Value |
| --- | --- |
| `Authorization` | `Bearer {apiKey}` |
| `X-Request-Id` | a fresh UUID v4 — **one per attempt**, so a retry carries a new id |
| `Content-Type` | `application/json`, only when a body exists |

No `User-Agent` is sent; recorded as a deviation in [BINDING.md](../BINDING.md).

A body that fails to parse as JSON becomes `null` rather than an exception, on both
the success and error paths.

### `Transport::url`

```php
public function url(string $path, array $query = []): string
```

The absolute URL for a relative path. Exposed so auto-paging can start from an
absolute URL and take the same code path for every page, page 1 included.

```php
$url = $transport->url(Endpoints::PRODUCTS, ['page' => 1, 'page_size' => 50]);
// https://test-be.suqo.ai/api/v1/products/?page=1&page_size=50
```

### `Transport::getAbsolute`

```php
public function getAbsolute(string $url, ?Cancellation $cancellation = null): TransportResponse
```

An absolute-URL `GET`, for following server-supplied pagination links. Applies the
same headers, timeout, cancellation, error mapping and retry policy as `request()`.

### `TransportResponse`

Readonly.

| Member | Type | Notes |
| --- | --- | --- |
| `status` | `int` | Always `< 400` — anything else was thrown. |
| `body` | `mixed` | Parsed JSON, or `null` when absent or unparseable. |
| `headers` | `array<string, string>` | Lower-cased names. |
| `requestId` | `string` | The id actually sent on this attempt. |
| `object(): array` | | The body as a wire object. A JSON array, a scalar or an unparseable body yields `[]` rather than a type error. |

## `RetryPolicy`

`GET` requests are retried up to `maxRetries` times — three attempts total by
default — on a network failure, a timeout, a 429 or a 5xx. Writes are not retried,
pending idempotency keys.

```php
public function __construct(int $maxRetries, Logger $logger, ?Closure $sleeper = null)
```

`$sleeper` is a test seam, not a public option; a custom one must honour
cancellation.

### `RetryPolicy::execute`

```php
public function execute(string $method, Cancellation $cancellation, callable $attempt): mixed
```

Runs `$attempt` up to `maxRetries + 1` times, sleeping between eligible failures.
Returns whatever `$attempt` returns. Cancellation is checked before each attempt and
throughout the backoff, so a token tripped mid-wait raises `CancelledError` rather
than completing the sleep. Each retry is logged at `warn` with the attempt number
and the delay.

**Throws** the last `SuqoError` once the attempts are exhausted; a `CancelledError`
or an ineligible error propagates immediately, unretried.

### `RetryPolicy::isEligible`

```php
public static function isEligible(string $method, SuqoError $error): bool
```

Both conditions must hold:

1. the method is in the eligible set — `['GET']` today, because
   `Constants::writesRetryable()` is `false`; and
2. `$error->status` is `0`, `429`, or `>= 500`.

`CancelledError` is excluded unconditionally, whatever the method or status.

When idempotency keys ship, the constant behind `writesRetryable()` flips and writes
join the eligible set with no other change — one decision, in one place.

### `RetryPolicy::computeDelayMs`

```php
public static function computeDelayMs(int $attempt, SuqoError $error): int
```

Milliseconds to wait before the next attempt. `$attempt` is 0-based.

A server-supplied `Retry-After` wins and is **not** jittered, capped at 60 000 ms.
Otherwise the delay is full jitter — a uniform draw across `[0, cap)`, not the cap
itself — with `cap = min(8000, 500 * 2 ** attempt)`:

| `attempt` | cap | delay drawn from |
| --- | --- | --- |
| 0 | 500 ms | 0–499 ms |
| 1 | 1 000 ms | 0–999 ms |
| 2 | 2 000 ms | 0–1 999 ms |
| 3 | 4 000 ms | 0–3 999 ms |
| 4+ | 8 000 ms | 0–7 999 ms |

`Retry-After` is read as **seconds only**; an HTTP-date value is ignored (it yields
`null`, so computed backoff applies), as is a negative value.

## `UrlBuilder::build`

```php
public function __construct(string $baseUrl)

public function build(string $path, array $query = []): string
```

Owns the trailing slash, so no endpoint literal carries one. It:

- trims a trailing slash from the base URL, then appends the path;
- appends `/` to the path when it lacks one;
- drops query entries whose value is `null` — entirely, not as an empty value;
- `rawurlencode`s both keys and values;
- renders booleans as `true` / `false`.

```php
use Suqo\Http\UrlBuilder;

$urls = new UrlBuilder('https://test-be.suqo.ai');

$urls->build('/api/v1/products', ['page' => 2, 'page_size' => null]);
// https://test-be.suqo.ai/api/v1/products/?page=2
```

The endpoint constants carry no trailing slash; the request URL does. Document and
debug against the built URL, not the constant.

## `Logger`

`Suqo\Logging\Logger` — level-filtered diagnostics on `STDERR`, so they never
contaminate `STDOUT` in a CLI program. A binding-level convenience: **there is no
logger injection point**, because the configuration surface lists none. Set
`logLevel` (or `$SUQO_LOG`) and the SDK builds its own.

```php
public function __construct(LogLevel $level, $stream = null)

public function debug(string $event, array $context = []): void
public function info(string $event, array $context = []): void
public function warn(string $event, array $context = []): void
public function error(string $event, array $context = []): void
public function level(): LogLevel
```

`$stream` is any writable resource, resolved lazily to `php://stderr` — so
constructing a client never opens a handle. Lines are formatted
`[suqo] level event {json context}`.

What the SDK itself logs: `request` and `response` at `debug` (method, URL, request
id, status, elapsed ms), and `retry` at `warn` (attempt number, delay).

```php
$suqo = new SuqoClient(logLevel: 'debug');
// [suqo] debug request {"method":"GET","url":"https://…/api/v1/products/","request_id":"…"}
// [suqo] debug response {"status":200,"request_id":"…","elapsed_ms":184}
```

The API key is never a logged value. Context is encoded with
`JSON_PARTIAL_OUTPUT_ON_ERROR`, so an unencodable value degrades the line rather
than throwing.

## `Endpoints`

`Suqo\Endpoints` — the only file in the SDK allowed to contain a URL path literal,
enforced by `composer invariants`. Private constructor; constants and one method.

| Member | Value |
| --- | --- |
| `Endpoints::PRODUCTS` | `/api/v1/products` |
| `Endpoints::SUBSCRIPTIONS` | `/api/v1/subscriptions` |
| `Endpoints::SUBSCRIPTION_BILLING_CYCLE` | `/api/v1/subscriptions/update-billing-cycle` |

### `Endpoints::subscriptionCancel`

```php
public static function subscriptionCancel(string $id): string
```

`/api/v1/subscriptions/{id}/cancel` with the id `rawurlencode`d. No trailing slash —
`UrlBuilder` adds it.

```php
use Suqo\Endpoints;

Endpoints::subscriptionCancel('3fa85f64-5717-4562-b3fc-2c963f66afa6');
// /api/v1/subscriptions/3fa85f64-5717-4562-b3fc-2c963f66afa6/cancel
```

## `Constants`

`Suqo\Constants` — every tunable in one place. Private constructor.

| Constant | Value |
| --- | --- |
| `LIVE_URL` | `https://be.suqo.ai` |
| `SANDBOX_URL` | `https://test-be.suqo.ai` |
| `LIVE_KEY_PREFIX` | `su_key_` |
| `SANDBOX_KEY_PREFIX` | `su_test_key_` |
| `ENV_API_KEY` | `SUQO_API_KEY` |
| `ENV_LOG` | `SUQO_LOG` |
| `DEFAULT_TIMEOUT` | `30.0` |
| `MAX_RETRIES` | `2` |
| `BASE_DELAY_MS` | `500` |
| `FACTOR` | `2` |
| `MAX_DELAY_MS` | `8000` |
| `RETRY_AFTER_CAP_MS` | `60000` |
| `WRITES_RETRYABLE` | `false` |
| `WEBHOOK_MAX_AGE` | `300` |
| `WEBHOOK_FORWARD_SKEW` | `60` |
| `MSG_MALFORMED_KEY` | the malformed-key message |
| `MSG_NOT_IMPLEMENTED` | `customers API not yet available in this SDK version` |

### `Constants::writesRetryable`

```php
public static function writesRetryable(): bool
```

Reads `WRITES_RETRYABLE`. The single gate on whether writes join the retry-eligible
set; read in exactly two places, asserted by `composer invariants`.

### `Constants::msgEnvConflict`

```php
public static function msgEnvConflict(Environment $inferred, Environment $given): string
```

The `SuqoConfigError` message for an `environment` argument that disagrees with the
key prefix.

## `AbstractResource`

```php
public function __construct(protected readonly Transport $transport)

protected static function pageQuery(?int $page, ?int $pageSize): array
```

The base every resource extends. `pageQuery()` maps `page` / `pageSize` onto
`['page' => …, 'page_size' => …]`; null values survive into the array and are
dropped later by `UrlBuilder`.

Extending it means building your own `Transport`, since `SuqoClient::$transport` is
private:

```php
use Suqo\Config;
use Suqo\Http\RetryPolicy;
use Suqo\Http\Transport;
use Suqo\Http\UrlBuilder;
use Suqo\Logging\Logger;

$config    = Config::resolve();
$logger    = new Logger($config->logLevel);
$transport = new Transport(
    $config,
    new UrlBuilder($config->baseUrl),
    new RetryPolicy($config->maxRetries, $logger),
    $logger,
);

$mine = new MyResource($transport);
```

## `Wire` — internal, may change

`Suqo\Model\Wire` is marked `@internal`. It is technically public static API and is
listed here for anyone writing a model of their own, but it carries no stability
guarantee.

Every helper is tolerant: a field of an unexpected type reads as **absent** rather
than failing deserialisation.

| Method | Signature | Reads |
| --- | --- | --- |
| `nstr` | `(array $wire, string $key): ?string` | a string, else null |
| `str` | `(array $wire, string $key, string $default = ''): string` | a string, else the default |
| `decimal` | `(array $wire, string $key): ?string` | a string as-is; an int stringified; else null. Never a float — decimals stay strings. |
| `nint` | `(array $wire, string $key): ?int` | an int, else null |
| `int` | `(array $wire, string $key, int $default = 0): int` | an int, else the default |
| `count` | `(array $wire, string $key): ?int` | an int, or a digit-only string coerced; else null |
| `nbool` | `(array $wire, string $key): ?bool` | a bool, else null |
| `object` | `(array $wire, string $key): ?array` | a JSON object, else null (a JSON list reads as null) |
| `objectList` | `(array $wire, string $key): list<array>` | a list of objects; non-object entries are dropped; `[]` when absent |
