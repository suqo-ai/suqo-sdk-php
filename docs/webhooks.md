# Webhooks

Verification needs no client, no API key and no network, so it can be called
straight from a serverless handler. It never throws: every failure path, malformed
input included, returns `false`.

## `verify`

```php
Suqo\Webhook::verify(
    string $rawBody,
    ?string $signature,
    ?string $timestamp,
    string $secret,
    int $maxAge = Constants::WEBHOOK_MAX_AGE,   // 300
): bool
```

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `rawBody` | `string` | — | **The exact bytes received.** Never a re-serialised parse. |
| `signature` | `?string` | — | The `X-Suqo-Signature` header value. `null` when absent. |
| `timestamp` | `?string` | — | The `X-Suqo-Timestamp` header value. `null` when absent. |
| `secret` | `string` | — | Your webhook signing secret. |
| `maxAge` | `int` | `300` | Seconds of **backward** tolerance. Forward skew is fixed at 60 s and is not configurable. |

**Returns** `bool`. **Throws** nothing, ever.

```php
use Suqo\Webhook;

$raw = file_get_contents('php://input');       // the exact bytes received

$verified = Webhook::verify(
    rawBody: $raw,
    signature: $_SERVER['HTTP_X_SUQO_SIGNATURE'] ?? null,
    timestamp: $_SERVER['HTTP_X_SUQO_TIMESTAMP'] ?? null,
    secret: getenv('SUQO_WEBHOOK_SECRET'),
);

if (!$verified) {
    http_response_code(400);
    exit;
}

$event = json_decode($raw, true);   // parse only after verifying
```

### What is checked, in order

Each check returns `false` on failure; none raises.

| # | Check |
| --- | --- |
| 1 | `signature` and `timestamp` are both non-null |
| 2 | `timestamp` is numeric after trimming |
| 3 | the parsed timestamp is finite |
| 4 | `now - timestamp <= maxAge` — not too old |
| 5 | `timestamp - now <= 60` — not too far in the future |
| 6 | `signature` starts with `sha256=` |
| 7 | the part after `sha256=` is exactly 64 characters |
| 8 | that part is valid hex |
| 9 | HMAC-SHA256 over the signed payload matches, compared with `hash_equals` |

The signed payload is the timestamp, a literal `.`, then the body — no other
separator, no encoding, and the timestamp used exactly as received:

```
"{timestamp}.{rawBody}"
```

Comparison is on decoded bytes via `hash_equals`, which is constant-time. A naive
`===` would leak timing.

### Pass the raw bytes

A body re-serialised from a parse verifies only by luck — the signature covers
bytes, not structure, and a round-trip through a JSON decoder changes whitespace,
key order, escaping and number formatting. A trivial payload can survive the
round-trip and pass in a test, then fail on the first real event with a nested
object or unicode in it.

```php
// Wrong: verification will fail even for a genuine event.
$verified = Webhook::verify(
    rawBody: json_encode($request->all()),
    // …
);
```

In a framework, reach for the untouched body: `$request->getContent()` in Symfony or
Laravel, `(string) $request->getBody()` on a PSR-7 request, `php://input` in plain
PHP. If a middleware has already consumed the stream, capture the bytes before it
does.

### `maxAge` and clock skew

`maxAge` widens only the backward window. A replayed event older than `maxAge`
seconds fails; an event whose timestamp is more than 60 seconds in the future fails
regardless of `maxAge`, which is what catches a badly skewed sender rather than a
slow queue.

```php
// A retry queue that can sit for ten minutes.
Webhook::verify($raw, $sig, $ts, $secret, maxAge: 600);
```

## `SuqoClient::verifyWebhook`

```php
Suqo\SuqoClient::verifyWebhook(
    string $rawBody,
    ?string $signature,
    ?string $timestamp,
    string $secret,
    int $maxAge = Constants::WEBHOOK_MAX_AGE,
): bool
```

A static alias that forwards to `Webhook::verify()` unchanged, for callers who
already have `SuqoClient` imported. Identical parameters, identical behaviour, and
still no instance or network needed.

```php
use Suqo\SuqoClient;

if (!SuqoClient::verifyWebhook($raw, $sig, $ts, $secret)) {
    http_response_code(400);
    exit;
}
```

Prefer `Webhook::verify()` in a handler that has no other use for the client — it
imports one small class instead of the whole entry point.

## A complete handler

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Suqo\Webhook;

$raw = file_get_contents('php://input');

if ($raw === false) {
    http_response_code(400);
    exit;
}

$verified = Webhook::verify(
    rawBody: $raw,
    signature: $_SERVER['HTTP_X_SUQO_SIGNATURE'] ?? null,
    timestamp: $_SERVER['HTTP_X_SUQO_TIMESTAMP'] ?? null,
    secret: (string) getenv('SUQO_WEBHOOK_SECRET'),
);

if (!$verified) {
    http_response_code(400);
    exit;
}

$event = json_decode($raw, true);

// Acknowledge fast, then process out of band.
http_response_code(200);
```

See also [examples/webhook_handler.php](../examples/webhook_handler.php).
