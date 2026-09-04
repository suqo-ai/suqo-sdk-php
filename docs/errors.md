# Errors

Every failure raised after construction derives from `Suqo\Exception\SuqoError`,
which extends `\RuntimeException`. Construction itself raises `SuqoConfigError`,
which does **not**.

```
\RuntimeException
└── SuqoError                 base type; also raised for unmapped statuses
    ├── AuthenticationError   401
    ├── KycRequiredError      403 with a KYC-shaped body
    ├── ValidationError       400
    ├── NotFoundError         404
    ├── RateLimitError        429
    ├── ServerError           >= 500
    ├── NetworkError          transport failure or timeout (status 0)
    ├── CancelledError        caller cancelled (status 0)
    └── NotImplementedError   unimplemented resource (status 0)

\InvalidArgumentException
└── SuqoConfigError           bad configuration, before any request exists
```

The `Error` suffix is kept rather than PHP's `Exception` idiom so type names read
the same across SUQO's SDKs; every type is still a `\Throwable`.

## `SuqoError`

```php
public function __construct(
    string $message,
    int $status = 0,
    string $requestId = '',
    mixed $rawBody = null,
    array $fieldErrors = [],
    ?float $retryAfter = null,
)
```

You rarely construct one — the transport does. What matters is the five readonly
fields, present on every subclass:

| Property | Type | Notes |
| --- | --- | --- |
| `status` | `int` | HTTP status, or `0` for a non-HTTP failure. Also passed to `getCode()`. |
| `requestId` | `string` | The `X-Request-Id` actually sent on the failing attempt. Empty when the error predates a request. Quote it in a support ticket. |
| `rawBody` | `mixed` | The parsed response body, **wire names preserved**. `null` when there was no body or it was not valid JSON. |
| `fieldErrors` | `array<string, list<string>>` | Always present, empty when not applicable. Populated only for a 400 with a field-shaped body. |
| `retryAfter` | `?float` | Seconds, from the `Retry-After` response header. `null` unless the server sent a numeric value. |

`getMessage()` carries the server's message when the body supplied one, else a
per-status default (`'Authentication failed.'`, `'Not found.'`,
`'Validation failed.'`, `'Rate limited.'`, `'Server error (503).'`).

## Catching

Order matters: catch the specific types first, then `SuqoError` as the backstop.

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

## The types

### `AuthenticationError` — 401

A bad, revoked or wrong-environment key. Not retried: retrying a 401 will not fix
it. Check `$suqo->config->environment` against the key you meant to use.

### `ValidationError` — 400

The only type that populates `fieldErrors`, and only when the body is
field-shaped. `getMessage()` is the first message in the body's own key order.

```php
$emailProblems = $e->fieldErrors['email'] ?? [];   // inside the catch block
```

Keys are **wire** names (`pbp_id`, `billing_email`), not surface names — the body is
reported as it arrived.

### `KycRequiredError` — 403 with a KYC-shaped body

```php
public readonly ?string $kycStatus;
```

The wire field `status_code`, surfaced as `kycStatus` because it sits next to the
HTTP status and means something else entirely. The raw body still carries
`status_code`. Its constructor defaults `$status` to `403`.

A 403 whose body is *not* KYC-shaped yields a bare `SuqoError` with the message
`'Unexpected status 403.'` — worth knowing when you are catching `KycRequiredError`
and seeing nothing.

### `NotFoundError` — 404

An unknown id. Note that a `cancel()` on an id from another environment looks
exactly like this.

### `RateLimitError` — 429

Retried automatically on a `GET` (up to `maxRetries`), and `retryAfter` is honoured
by the retry policy, capped at 60 s. If it still surfaces, you have exhausted the
retries; back off using `$e->retryAfter` when it is non-null.

### `ServerError` — 5xx

Retried automatically on a `GET`. `getMessage()` is `'Server error (%d).'` with the
actual status, so the status is in the log line even without reading `$e->status`.

### `NetworkError` — status 0

A transport failure, a DNS failure, a TLS failure or a timeout. Retried on a `GET`.
On a write it means the request may or may not have landed — writes are not retried
pending idempotency keys, so reconcile rather than blindly resend.

### `CancelledError` — status 0

Your `Cancellation` token was tripped. **Never retried**, and deliberately distinct
from the `NetworkError` a timeout produces, so "I stopped it" and "the network
stopped it" stay separable. Honoured before an attempt, mid-flight, during backoff,
and between pages.

### `NotImplementedError` — status 0

Raised by every `$suqo->customers` method. See [customers.md](customers.md).

### `SuqoConfigError`

```php
final class SuqoConfigError extends \InvalidArgumentException
```

Raised from `new SuqoClient(...)` or `Config::resolve(...)` — a missing or malformed
key, an `environment` disagreeing with the key prefix, an unparseable `environment`
or `logLevel`, a non-positive `timeout`, a negative `maxRetries`.

It sits outside the `SuqoError` tree on purpose: it is a programming or deployment
mistake, not an API outcome, and there is no `status` or `requestId` to report
because no request exists.

```php
use Suqo\Exception\SuqoConfigError;

try {
    $suqo = new SuqoClient(apiKey: $key);
} catch (SuqoConfigError $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
```

A `catch (SuqoError)` will **not** catch it. Catch both where a single handler must
cover startup and runtime.

## `ErrorMapper::map`

```php
Suqo\Exception\ErrorMapper::map(
    int $status,
    mixed $body,
    string $requestId,
    ?float $retryAfter = null,
): SuqoError
```

Turns a status and parsed body into the right exception instance. The transport is
the only caller; it is public so a custom transport can reuse the mapping rather
than reinvent it. Never throws — it *returns* the error for the caller to throw.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `status` | `int` | — | HTTP status. |
| `body` | `mixed` | — | Parsed JSON, or `null` when absent or unparseable. |
| `requestId` | `string` | — | Recorded on the error. |
| `retryAfter` | `?float` | `null` | Seconds. |

### Body classification, in order

The order is normative — the first match wins.

1. **KYC** — the body is an object with both `status_code` and `message`, both
   strings. Message is `message`; `kycStatus` is `status_code`.
2. **Detail** — the body has exactly one key, named `detail`, string-valued.
   Message is that string.
3. **Field errors** — every string or list-of-strings value is collected into
   `fieldErrors`. The message is the first value in the body's own JSON key order
   (`json_decode` preserves document order).
4. **None** — a non-object body (a scalar, a JSON array, an unparseable body) gives
   no message, so the per-status default text is used.

`fieldErrors` is attached only on a 400. A KYC body on a status other than 403
classifies as KYC but maps by status, so its message survives while the type does
not.

```php
use Suqo\Exception\ErrorMapper;

$error = ErrorMapper::map(400, ['email' => ['Enter a valid email.']], 'req_1');

$error instanceof \Suqo\Exception\ValidationError;   // true
$error->getMessage();                                // "Enter a valid email."
$error->fieldErrors['email'][0];                     // "Enter a valid email."
```
