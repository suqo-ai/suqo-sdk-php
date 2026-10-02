# Webhooks

Two unrelated things share the word:

| | |
| --- | --- |
| **Verifying a delivery** — `Suqo\Webhook::verify()` | No client, no API key, no network. Covered first, below. |
| **Managing subscriptions** — `$suqo->webhooks->*` | The eight `webhooks_*` API operations. See [Managing webhooks](#managing-webhooks). |

They meet at one point: [`$suqo->webhooks->secret()`](#secret) returns the
signing secret that `verify()` takes.

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

## Managing webhooks

`$suqo->webhooks` — `Suqo\Resource\Webhooks`. Registers and edits the endpoints
SUQO delivers to. Nothing here verifies anything; `Webhook::verify()` above is
the other half.

An account may hold **one webhook per event**. The events are the cases of
`Suqo\Model\WebhookEvent`:

`checkout.succeeded`, `checkout.failed`, `subscription.status_changed`,
`api_key.created`, `api_key.deleted`, `api_key.expiring_soon`,
`api_key.expired`.

An event the SDK does not yet name still reads back — as the raw string rather
than a case — and can still be registered by passing that string.

### The endpoint must survive a check

Before an endpoint is stored it must use **https**, resolve, and resolve **only
to public addresses**. Every address the name answers with is checked, so a host
with both a public and a loopback record is refused. The messages are
`Endpoint URL must use https.`, `Endpoint host could not be resolved: …` and
`Endpoint host resolves to a non-public address (…).`, all raised as
`ValidationError` on a 400.

Delivery re-runs the same check, because DNS can be re-pointed after this one
passes.

### The record

`Suqo\Model\WebhookEndpoint` — `id` (`whk_…`), `event`
(`WebhookEvent|string|null`), `endpointUrl`, `isActive`, `createdAt`, plus
`toArray()`.

### `list`

```php
public function list(?Cancellation $cancellation = null): array
```

`GET /api/v1/webhooks/` — every webhook registered by your account.

**Returns** `list<WebhookEndpoint>`. This is the one collection on the API that
is **not** paginated: it answers with a bare JSON array, so there is no `Page`
here and nothing to auto-page.

```php
foreach ($suqo->webhooks->list() as $webhook) {
    printf("%s  %s  %s\n", $webhook->id, $webhook->endpointUrl, $webhook->isActive ? 'on' : 'off');
}
```

### `create`

```php
public function create(WebhookParams $params, ?Cancellation $cancellation = null): WebhookEndpoint
```

`POST /api/v1/webhooks/` — subscribe an https endpoint to one event.

**Throws** `ValidationError` on 400 — `{"event": ["A webhook for this event
already exists."]}` when the event is taken, or one of the endpoint messages
above.

```php
use Suqo\Model\WebhookEvent;
use Suqo\Params\WebhookParams;

$webhook = $suqo->webhooks->create(new WebhookParams(
    event: WebhookEvent::CheckoutSucceeded,
    endpointUrl: 'https://partner.example.com/hooks/suqo',
));
```

### `read`

```php
public function read(string $id, ?Cancellation $cancellation = null): WebhookEndpoint
```

`GET /api/v1/webhooks/{public_id}/`. **Throws** `NotFoundError` on 404.

### `replace`

```php
public function replace(string $id, WebhookParams $params, ?Cancellation $cancellation = null): WebhookEndpoint
```

`PUT /api/v1/webhooks/{public_id}/` — overwrite the whole record. Both `event`
and `endpointUrl` are required, and a field you leave out is not preserved. It
is named `replace` for that reason; `update` is the one-field change.

### `update`

```php
public function update(string $id, WebhookUpdateParams $params, ?Cancellation $cancellation = null): WebhookEndpoint
```

`PATCH /api/v1/webhooks/{public_id}/` — change only what you set. Pausing
deliveries without deleting the subscription is a body of exactly one field:

```php
use Suqo\Params\WebhookUpdateParams;

$suqo->webhooks->update('whk_a1b2c3d4e', new WebhookUpdateParams(isActive: false));
```

### `delete`

```php
public function delete(string $id, ?Cancellation $cancellation = null): void
```

`DELETE /api/v1/webhooks/{public_id}/` — remove the subscription. Answers 204
with no body, so there is nothing to return. Past delivery records are kept for
debugging.

### `secret`

```php
public function secret(?Cancellation $cancellation = null): SigningSecret
```

`GET /api/v1/webhooks/secret/` — the account's signing secret, **minted on first
read**. Every delivery is signed with it, and it is what [`verify`](#verify)
takes. It is not rotatable through this API, so treat it as a credential: store
it, do not log it.

```php
$secret = $suqo->webhooks->secret()->signingSecret;
```

Fetch it once at deploy time and keep it in configuration rather than calling
this on every delivery — verification itself must not need the network.

### `testDelivery`

```php
public function testDelivery(string $id, ?Cancellation $cancellation = null): DetailResponse
```

`POST /api/v1/webhooks/{public_id}/test-delivery/` — enqueue a signed test
payload so you can confirm your receiver.

**Returns** `Suqo\Model\DetailResponse` — `detail`, not `message`, which is why
it is not a `MessageResponse`.

The 202 means *queued*, not *delivered*: the delivery itself is asynchronous, so
a success here says nothing about whether your endpoint accepted it. Watch your
own logs.
