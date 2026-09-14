# SUQO PHP binding — companion document

Companion to **SUQO SDK — Specification v2.0**. §14 lists exactly what a binding
decides; this document answers those twelve questions and nothing else, then
records the deviations §1 requires to be documented.

Package: `suqo/suqo-php` · Namespace: `Suqo\` · Spec version: 2.0

---

## §14 checklist

### B1 — Surface naming convention (N3)

PHP's dominant convention, as codified by PSR-1/PSR-12:

| Kind | Convention | Example |
| --- | --- | --- |
| Classes, interfaces, enums, enum cases | `PascalCase` | `SuqoClient`, `SubscriptionStatus::PendingCheckout` |
| Methods | `camelCase` | `updateBillingCycle()`, `autoPaging()` |
| Properties, parameters, local variables | `camelCase` | `$pageSize`, `$vatPercentage`, `$requestId` |
| Constants | `SCREAMING_SNAKE_CASE` | `Constants::MAX_DELAY_MS` |
| Namespaces | `PascalCase`, PSR-4 | `Suqo\Resource\Subscriptions` |

Concept stems are unchanged (N2): `pbp_id` → `$pbpId`, `vat_percentage` →
`$vatPercentage`, `total_subscribers` → `$totalSubscribers`. Wire values are never
translated (N1) — every serialised payload and every `toArray()` result carries the
wire spelling.

### B2 — Parameter grouping (§10.4)

Two mechanisms, both additive:

1. **Named arguments** for scalar and optional parameters:
   `$suqo->products->list(page: 2, pageSize: 50, cancellation: $token)`.
2. **Params objects** for request bodies, one per `*Request` schema, renamed to
   `*Params` per §3: `CreateSubscriptionParams`, `UpdateBillingCycleParams`. They
   are constructed with named arguments too, and expose `toWire()`.

Adding an optional parameter — a new named argument with a default, or a new
optional constructor argument on a params object — does not break an existing call
site, which is what §10.4 requires.

### B3 — Cancellation primitive (I7)

`Suqo\Cancellation`: an explicit one-way latch (`cancel()`, `isCancelled()`,
`Cancellation::none()`), passed as the last argument to every public operation and
forwarded to the transport. PHP has no ambient request context and no
cancellation-token type in the standard library, so the SDK supplies one.

It is observed in four places: before an attempt (§6.5), inside the cURL progress
callback (mid-flight, §6.5), inside the backoff sleep (§7.4), and before each page
fetch (§11.2).

### B4 — Lazy iteration primitive (§11.2)

A PHP `Generator`. `products->autoPaging()` and `subscriptions->autoPaging()`
return `Generator<int, Product>` / `Generator<int, Subscription>`. A page is
fetched only when the consumer has exhausted the previous one; nothing is
accumulated.

### B5 — HTTP client injection point (§4.1)

`Suqo\Http\HttpClientInterface` — a single `send(HttpRequest): HttpResponse`.

PSR-18 is deliberately *not* the injection point: it can express neither a
per-request timeout nor mid-flight cancellation, both of which §6.5 requires.
`Suqo\Http\Psr18HttpClient` adapts a PSR-18 client for callers who want one, with
the documented caveat that it honours cancellation only at the call boundaries and
takes its timeout from the injected client.

Default: `Suqo\Http\CurlHttpClient` (ext-curl), which supports both.

### B6 — Error representation (N6, §8.1)

An exception hierarchy rooted at `Suqo\Exception\SuqoError extends
\RuntimeException`. Every mapped type is `final` and extends `SuqoError`.

N6 permits either the literal `Error` suffix or the host language's idiomatic
equivalent. This binding keeps the spec's `Error` suffix rather than PHP's
`Exception` idiom, so that type names read identically across bindings and the
§13 test names transfer verbatim. All of them are still `\Throwable`, so
`catch (\Throwable)` and `catch (SuqoError)` both work as a PHP reader expects.

`SuqoConfigError` takes the §8.1 option to sit outside the hierarchy: it extends
`\InvalidArgumentException`, because a construction-time argument fault is
idiomatically that in PHP, and it carries no status, request id or body.

### B7 — Optional/nullable representation (§9.2)

Native nullable types: `?string`, `?int`, `?SubscriptionCustomer`. No zero values,
empty strings or sentinels stand in for absence. Reads use the null-safe operator
naturally: `$subscription->customer?->email`.

### B8 — Decimal string type (§9.1)

Plain `string`, with no newtype wrapper. `price`, `vat` and `totalSubscribers` are
`?string`, as is `nextBillingCycle` — a date, kept unparsed for the same reason. No `float`, `double` or `(float)` cast appears
anywhere in the model or params surface — `tests/Unit/DecimalsTest.php` asserts
this reflectively, and `tests/Unit/InvariantsTest.php` greps for the casts.

Callers who need arithmetic parse at their own boundary — `bcmath`, `ext-decimal`,
or `brick/math`.

### B9 — Async model

Synchronous and blocking. There is no async variant, no promise and no fiber-aware
API: PHP's dominant execution model is one blocking request per process, and Fibers
are not yet a portable concurrency story for library authors. Cancellation covers
the case a promise would otherwise be needed for.

### B10 — Package name, module layout, build tooling

* Package: `suqo/suqo-php` on Packagist. Namespace `Suqo\`, PSR-4 from `src/`.
* Layout maps 1:1 onto §5's layers:

  | §5 layer | Location |
  | --- | --- |
  | Config | `src/Config.php`, `src/Environment.php`, `src/LogLevel.php`, `src/Constants.php` |
  | URL builder | `src/Http/UrlBuilder.php` |
  | Logger | `src/Logging/Logger.php` |
  | Errors | `src/Exception/` |
  | Transport | `src/Http/Transport.php` (+ `HttpClientInterface`, `CurlHttpClient`, `Psr18HttpClient`) |
  | Retry | `src/Http/RetryPolicy.php` |
  | Pagination | `src/Pagination.php`, `src/Model/Page.php`, `src/Model/SubscriptionPage.php` |
  | Resources | `src/Resource/` |
  | Webhooks | `src/Webhook.php` |
  | Client | `src/SuqoClient.php` |
  | Endpoint table (I4) | `src/Endpoints.php` |

* Tooling: Composer, PHPUnit 10, PHPStan at `level: max`, PHP-CS-Fixer (`@PSR12`).
  `composer ci` runs lint, static analysis, the I1/I4/I6 grep gate and the test
  suite.

### B11 — Minimum runtime version

**PHP 8.1.** Required for native enums (`Environment`, `LogLevel`,
`SubscriptionStatus`), `readonly` properties, `never`, and pure intersection-free
union types in property positions. Extensions: `curl`, `json`, `hash`.

### B12 — Ordering guarantee for §8.3 field errors

**Preserved.** `json_decode($body, true)` yields a PHP array whose iteration order
is the JSON document's key order, so §8.3's "first value of first entry" is the
first key in the response body as the server wrote it. No documented fallback
ordering is needed.

---

## Deviations and documented decisions (§1)

1. **`{}` and `[]` are indistinguishable after `json_decode(..., true)`.** §8.3
   classification therefore treats an empty array as an object with no keys, which
   routes both to the FIELD branch with an empty map. §13 E6 is satisfied and the
   two cases produce identical observable behaviour, so the ambiguity is not
   reachable from the surface.
2. **`Retry-After` is seconds only.** §6.7 says to interpret the header as
   seconds; an HTTP-date value is treated as absent, so computed backoff applies.
3. **No `User-Agent` header.** §6.3's header table is treated as closed.
4. **`SUQO_LOG` with an unrecognised value falls back to `warn`** rather than
   failing construction. An explicitly passed `logLevel` string is validated and
   does raise `SuqoConfigError` — the difference is that a call site is the
   caller's to fix and the environment is not.
5. **`timeout` is a `float` of seconds**, PHP's idiom for a duration; the spec's
   `duration` type has no PHP equivalent. `RetryPolicy` works in integer
   milliseconds internally.
6. **`SubscriptionStatus` fields are typed `SubscriptionStatus|string|null`.**
   §9.3 asks for a closed enumeration *and* a tolerant read; a PHP backed enum
   cannot hold an unrecognised value, so a recognised status arrives as a case and
   an unrecognised one as the raw wire string. `SubscriptionStatus::parse()`
   performs the widening.
7. **`Model::toArray()`** exposes the complete decoded payload under wire names on
   every read model. It is the read-side counterpart of N8 and the
   forward-compatibility path for fields openapi.yaml adds later.
8. **`extra` on params objects** merges caller-supplied wire-named keys into a
   request body, verbatim (N1). Same motivation as `toArray()`, write side.
9. **`SuqoClient::verifyWebhook()`** is an alias for `Webhook::verify()`. §12
   requires the standalone function, which is the real entry point; the alias
   exists only for discoverability from the client.
10. **`Constants::WRITES_RETRYABLE` is read through `Constants::writesRetryable()`.**
    I6 requires one constant, flipped in one place. The accessor is what makes that
    literally true — the constant is named in exactly one file — and it also keeps
    static analysis honest, since a folded `false` constant otherwise reads as dead
    code at the branch. `tools/check-invariants.sh` asserts both halves.
11. **The `External` prefix is stripped from schema keys.** N5 derives model type
    names from schema keys with the stem verbatim, which would give
    `ExternalProduct` and `ExternalCustomer`. §10, §11 and §3 name the same types
    `Product`, `Subscription`, `SubscriptionPage`, `MessageResponse`,
    `CreateSubscriptionResponse` and `Customer` directly, and those sections are
    normative, so the prefix — a server-side artefact — is dropped. Strictly this is
    a wire-to-surface deviation beyond §3's table, which N7 calls complete; §3
    should grow an `External*` → `*` row in the next revision. The same reasoning
    drops the `Read` suffix from `ExternalProductPlanRead`, exactly as §3 already
    drops it from `ClientRead`.
12. **`Product.type` is `?string`, not an enum.** openapi constrains it to
    `simple|tiered`, but §9.3 mandates a closed enumeration for `SubscriptionStatus`
    only, and a `ProductType` enum would be surface §14 does not describe.
13. **`Cancellation` is not `final`.** Tests need a token that trips on the Nth
    observation to reproduce "cancelled mid-flight" in a single-threaded runtime.

## Reconciled against openapi.yaml

openapi.yaml (Swagger 2.0, `host: be.suqo.ai`, `basePath: /api/v1`) is the source
of truth for wire shapes, and every model and params object below is taken from it
rather than inferred. Where §9's prose named a field the schema does not have,
openapi wins per §1.

| §9 named | openapi actually declares |
| --- | --- |
| `Product.price` | absent — price lives on the plan billing point, surfacing as `Subscription.product.price` |
| `Product.pbp_id` | absent — `pbp_id` is on the subscription's embedded product |
| `vat_percentage` | `vat` (`ExternalProduct.vat`, a string) |
| `Subscription.amount` | absent |
| `Subscription.id` | `subscription_id` |
| `UpdateBillingCycle.billing_cycle` | `next_billing_cycle`, a required date |
| `CreateSubscriptionResponse` | openapi declares the 201 body as `ExternalSubscriptionCreate` — the request schema, echoed back |

Schema key → surface type, per N5 with the §3 renames applied:

| openapi definition | Surface type |
| --- | --- |
| `ExternalProduct` | `Model\Product` |
| `ExternalProductPlanRead` | `Model\ProductPlan` |
| `ExternalCustomer` | `Model\Customer` |
| `ExternalSubscriptionCreate` (request) | `Params\CreateSubscriptionParams` |
| `ExternalSubscriptionCreate` (201 body) | `Model\CreateSubscriptionResponse` |
| `ExternalUpdateBillingCycle` | `Params\UpdateBillingCycleParams` |
| `ExternalClient` | `Params\CustomerInput` |
| `ExternalClientBilling` | `Params\CustomerBilling` |
| `ExternalClientShipping` | `Params\CustomerShipping` |
| `/subscriptions/` results item | `Model\Subscription` |
| … its `client` | `Model\SubscriptionCustomer` |
| … its `client.billing` | `Model\SubscriptionCustomerBilling` |
| … its `client.shipping` | `Model\SubscriptionCustomerShipping` |
| … its `product` | `Model\SubscriptionProduct` |
| `/subscriptions/` 200 envelope | `Model\SubscriptionPage` |
| `/products/` 200 envelope | `Model\Page<Product>` |
| cancel / update-billing-cycle 200 body | `Model\MessageResponse` |

Two shapes that look like one are deliberately two types: the write-side
`CustomerBilling` spells its keys `billing_business_name`, `billing_email`,
`billing_address`, `billing_pan_vat`, while the read-side
`SubscriptionCustomerBilling` spells them `business_name`, `email`, `address`,
`pan_vat`. N1 forbids adjusting either, so neither can stand in for the other.

`ExternalSubscriptionRead` (the `GET /subscriptions/{id}/` schema) is not modelled:
its `client`, `product` and `is_active` are all declared `type: string`, which is a
generator artefact rather than the shape the server sends. The operation is not
exposed either — see below.

## Needs a specification revision

These operations have no §10 counterpart. §14 forbids public API surface this
specification does not describe, so none of them is exposed and each is listed
here instead of being quietly added.

| openapi operationId | Path | Note |
| --- | --- | --- |
| `subscriptions_read` | `GET /api/v1/subscriptions/{id}/` | Would be `subscriptions.read` (N4). Needs a §10.2 row and a modelled response schema. |
| `subscriptions_resume` | `POST /api/v1/subscriptions/{id}/resume/` | Would be `subscriptions.resume`, returning `MessageResponse`. Needs a §6.1 endpoint and a §10.2 row. |
| `customers_create` | `POST /api/v1/customers/` | Upsert semantics (201 new / 200 corrected). No §10 row. |
| `customers_partial_update` | `PATCH /api/v1/customers/{id}/` | No §10 row. |
| `webhooks_*` (eight) | `/api/v1/webhooks/…` | A whole management resource with no §10 counterpart. |

### Customers — implemented 2026-09-11

`customers_list` and `customers_read` were previously in the table above, with
the operations raising `NotImplementedError` per §10.3 while `Model\Customer`
stayed un-stubbed per §9.4.

They are now implemented. The §9.4 / §10.3 tension was resolved in favour of
§9.4 once the endpoints were confirmed against the live API: both the list
envelope and the record shape were verified against real responses on
2026-09-11, so neither the operation set nor the decoded shape is a guess. Names
come from the declared operationIds minus the resource noun (N4) — `list` and
`read`, plus the `autoPaging` counterpart §10.1 and §10.2 give every list.

This needs the matching §10.3 revision in the specification proper; the SDK
behaviour is recorded here so the two do not silently disagree. `customers_create`
and `customers_partial_update` remain unexposed, since no §10 row covers them.

Two shape corrections came out of the same confirmation, both breaking:

- `Customer::$id` is `?string` (`cus_0390b1820`), not `?int`. The path parameter
  is `public_id` in openapi for the same reason.
- `Customer::$address` was missing from the record entirely.

openapi's declared 200 schema for `customers_list` is a bare array. The live API
returns the ordinary `{count, next, previous, results}` envelope, so the declared
schema is a generation artifact and the SDK follows the live shape.

## Deviation from §4.3 — SANDBOX_URL

§4.3 gives `SANDBOX_URL = https://test.be.suqo.ai`. That hostname does not resolve
(NXDOMAIN), so every sandbox request under it fails as a retryable `NetworkError`
and burns three attempts before surfacing. The deployed host is
**`https://test-be.suqo.ai`** — a hyphen, not a dot — and that is what
`Constants::SANDBOX_URL` holds. §4.3 needs correcting in the next revision.

## Open question for the API team

`securityDefinitions.Token` declares `type: apiKey, name: Authorization, in: header`
but not the scheme prefix. §6.3 specifies `Bearer {api_key}`, which is what the SDK
sends and what §1 makes authoritative for behaviour. A Django REST `Token`
authenticator conventionally expects `Token {api_key}` instead — worth confirming
against a live sandbox key before 1.0, because getting it wrong 401s every request.
