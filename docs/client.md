# The client

`Suqo\SuqoClient` is the whole entry point: construct it, then reach a resource
through one of its four readonly properties. It is `final`, and it exposes resources
and nothing else — no request method, no header hook, no mutable state.

```php
use Suqo\SuqoClient;

$suqo = new SuqoClient();          // api key from $SUQO_API_KEY
```

## `__construct`

```php
public function __construct(
    ?string $apiKey = null,
    Environment|string|null $environment = null,
    ?float $timeout = null,
    ?int $maxRetries = null,
    LogLevel|string|null $logLevel = null,
    ?HttpClientInterface $httpClient = null,
)
```

Resolves and validates configuration, then wires the transport and the three
resources. No network request is made.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `apiKey` | `?string` | `$SUQO_API_KEY` | Must start `su_test_key_` (sandbox) or `su_key_` (live). |
| `environment` | `Environment\|string\|null` | inferred | A **check** against the key prefix, never an override. `'live'`, `'sandbox'`, or an `Environment` case. |
| `timeout` | `?float` | `30.0` | Seconds. Must be greater than zero. Applies per attempt, connect and total. |
| `maxRetries` | `?int` | `2` | Retries *after* the first attempt, so `2` means three attempts. Must be zero or greater. |
| `logLevel` | `LogLevel\|string\|null` | `$SUQO_LOG`, else `warn` | `debug`, `info`, `warn`, `error`, `off`. |
| `httpClient` | `?HttpClientInterface` | `new CurlHttpClient()` | See [http.md](http.md#injecting-a-client). |

**Throws** `Suqo\Exception\SuqoConfigError` — a missing or malformed key, an
`environment` that disagrees with the key prefix, an unparseable `environment` or
`logLevel` string, `timeout <= 0`, or `maxRetries < 0`. It extends
`\InvalidArgumentException`, **not** `SuqoError`, so a `catch (SuqoError)` will not
catch it.

```php
$suqo = new SuqoClient(
    apiKey: 'su_test_key_…',
    environment: 'sandbox',   // optional; must agree with the prefix
    timeout: 30.0,
    maxRetries: 2,
    logLevel: 'warn',
);
```

An unrecognised `$SUQO_LOG` value falls back to the default rather than failing
construction — the environment is not the caller's call site. An unrecognised
`logLevel` *argument* does throw.

### Properties

| Property | Type | Notes |
| --- | --- | --- |
| `$suqo->config` | `Config` | The resolved, validated configuration. |
| `$suqo->products` | `Resource\Products` | See [products.md](products.md). |
| `$suqo->subscriptions` | `Resource\Subscriptions` | See [subscriptions.md](subscriptions.md). |
| `$suqo->customers` | `Resource\Customers` | Reserved; every call throws. See [customers.md](customers.md). |

All four are `readonly`. The `Transport` behind them is private by design.

## `SuqoClient::verifyWebhook`

```php
public static function verifyWebhook(
    string $rawBody,
    ?string $signature,
    ?string $timestamp,
    string $secret,
    int $maxAge = Constants::WEBHOOK_MAX_AGE,   // 300
): bool
```

A pure alias for `Webhook::verify()`, exposed on the client only for
discoverability. It needs no instance, no API key and no network. Documented in
full in [webhooks.md](webhooks.md).

## `Config`

`Suqo\Config` is the resolved configuration — immutable, with a **private**
constructor. The only way to build one is `Config::resolve()`, which
`SuqoClient::__construct()` calls for you.

| Property | Type | Notes |
| --- | --- | --- |
| `apiKey` | `string` | As supplied or read from the environment. |
| `environment` | `Environment` | Inferred from the key prefix. |
| `baseUrl` | `string` | `$environment->baseUrl()`. |
| `timeout` | `float` | Seconds. |
| `maxRetries` | `int` | |
| `logLevel` | `LogLevel` | |
| `httpClient` | `HttpClientInterface` | |

```php
echo $suqo->config->environment->value;   // "sandbox"
echo $suqo->config->baseUrl;              // "https://test-be.suqo.ai"
```

### `Config::resolve`

```php
public static function resolve(
    ?string $apiKey = null,
    Environment|string|null $environment = null,
    ?float $timeout = null,
    ?int $maxRetries = null,
    LogLevel|string|null $logLevel = null,
    ?HttpClientInterface $httpClient = null,
): self
```

Same parameters as the client constructor, and the same `SuqoConfigError`. Useful on
its own only when you want to validate credentials without building a client.

Resolution order:

1. `apiKey` argument, else `$SUQO_API_KEY` (read via `getenv()`, then `$_ENV`, then
   `$_SERVER`). Empty or absent → `SuqoConfigError`.
2. Environment inferred from the prefix. The sandbox prefix is tested first, because
   both prefixes begin `su_`.
3. An explicit `environment` is compared with the inferred one and must agree.
4. `timeout` defaults to `30.0` and must be `> 0`; `maxRetries` defaults to `2` and
   must be `>= 0`.
5. `logLevel` argument, else `$SUQO_LOG` (case-insensitive, trimmed, unrecognised
   ignored), else `LogLevel::Warn`.
6. `httpClient` argument, else a fresh `CurlHttpClient`.

```php
use Suqo\Config;
use Suqo\Exception\SuqoConfigError;

try {
    $config = Config::resolve(apiKey: $candidate);
} catch (SuqoConfigError $e) {
    echo 'bad key: ', $e->getMessage();
}
```

## `Environment`

```php
enum Environment: string
{
    case Live = 'live';
    case Sandbox = 'sandbox';
}
```

### `Environment::baseUrl`

```php
public function baseUrl(): string
```

`Live` → `https://be.suqo.ai`, `Sandbox` → `https://test-be.suqo.ai`. (The sandbox
host uses a hyphen, not a dot; recorded as a deviation in
[BINDING.md](../BINDING.md).)

```php
use Suqo\Environment;

echo Environment::Sandbox->baseUrl();     // https://test-be.suqo.ai
```

## `LogLevel`

```php
enum LogLevel: string
{
    case Debug = 'debug';
    case Info  = 'info';
    case Warn  = 'warn';
    case Error = 'error';
    case Off   = 'off';
}
```

### `LogLevel::severity`

```php
public function severity(): int
```

`10` debug, `20` info, `30` warn, `40` error, `100` off.

### `LogLevel::emits`

```php
public function emits(self $level): bool
```

Whether a logger configured at `$this` should emit a record of severity `$level` —
that is, `$level->severity() >= $this->severity()`. `Off` emits nothing.

```php
use Suqo\LogLevel;

LogLevel::Warn->emits(LogLevel::Error);   // true
LogLevel::Warn->emits(LogLevel::Debug);   // false
```

Diagnostics go to `STDERR`, so they never contaminate `STDOUT` in a CLI program.
There is no logger injection point — see [http.md#logger](http.md#logger).
