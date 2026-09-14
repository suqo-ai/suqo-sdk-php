# Changelog

Notable changes to `suqo/sdk-php`.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this
project follows [Semantic Versioning](https://semver.org/) as described in
[VERSIONING.md](VERSIONING.md).

## [Unreleased]

_Nothing yet._

## [1.0.0] - 2026-09-14

<!-- Release-As: 1.0.0 -->

### Changed

- **The public surface is now stable.** `1.0.0` is a commitment to hold it:
  from here, a breaking change requires a major version, and additions — the
  operations listed under *Not yet exposed* in the README — arrive as minors.
  Nothing in this release changes behaviour; `0.1.0` and `1.0.0` are the same
  code plus the documentation change below.

### Removed

- `BINDING.md`, the binding companion document. It recorded internal design
  rationale rather than anything a consumer needs, and was never part of the
  published package. The handful of sentences in `README.md` and `docs/` that
  existed only to point into it have been rewritten to stand alone.

## [0.1.0] - 2026-09-14

### Added

- `SuqoClient` — environment inferred from the API key prefix, with
  `SuqoConfigError` raised before any request on a malformed key or a
  conflicting `environment`.
- `products->list()` / `->autoPaging()` — the seller's catalogue, including each
  plan's billing periods. `BillingPeriod::$pbpId` is the input to
  `subscriptions->create()`.
- `subscriptions->list()` / `->create()` / `->cancel()` / `->updateBillingCycle()`
  / `->autoPaging()`.
- `customers->list()` / `->read()` / `->autoPaging()` — read-only.
- `Webhook::verify()` — HMAC-SHA256 signature verification with replay
  protection. No client, no API key, no network.
- Automatic retries for reads on a network failure, `429` or `5xx`, with
  full-jitter backoff and `Retry-After` support. Writes are never retried,
  pending idempotency keys.
- `Cancellation` — an explicit token honoured before an attempt, mid-flight,
  during backoff and between pages.
- Pluggable HTTP layer: `CurlHttpClient` by default, `Psr18HttpClient` for any
  PSR-18 client, or your own `HttpClientInterface`.
- A full `SuqoError` hierarchy, a request id on every attempt, and configurable
  logging via `$SUQO_LOG`.

### Security

- `Transport::getAbsolute()` now refuses an absolute URL whose origin does not
  match the configured base URL. Pagination follows server-supplied `next`
  links, and every request carries the API key, so an off-host link would have
  leaked it.
- A 3xx response is now raised as a `SuqoError` carrying the redirect status,
  rather than being decoded as if its body were the payload. The SDK never
  follows redirects — cURL does not strip a manually-set `Authorization` header
  across one — so a redirect reaching the transport is an error, and raising it
  also surfaces an injected PSR-18 client that is safely configured not to
  follow them.
- `autoPaging()` stops after `Pagination::MAX_PAGES` (10 000) rather than
  following a `next` chain forever. A link pointing back at its own page is
  same-origin, so the origin guard does not catch it.

### Fixed

Against the live API and the current Swagger document:

- `products->list()` no longer discards each plan's billing periods, so `pbpId`
  is reachable from the typed surface instead of only through `toArray()`. Adds
  `BillingPeriod`, `Offer`, `ProductVat` and `ProductImage`.
- `Product::$vat` is a `?ProductVat` object, not a decimal string, and
  `Product::$productImage` is a `list<ProductImage>`, not a string. Both were
  permanently `null` before.
- `CreateSubscriptionResponse` is the real 201 schema — `subscriptionId`,
  `pbpId`, `status`, `checkoutUrl`, `nextBillingCycle`, `createdAt` — rather than
  an echo of the request. `checkoutUrl` is now a typed field.
- `Customer::$id` is `?string` (a `cus_…` public id), not `?int`, and the missing
  `address` field is added.
- A `400` whose body is a bare list of strings, which is how `cancel` and
  `resume` report an illegal state transition, surfaces its message instead of
  dropping it.
- A `403` without a KYC-shaped body raises `PermissionDeniedError` carrying the
  server's `detail`, instead of a bare `SuqoError` with the message discarded.

### Changed

Error handling now follows the TypeScript SDK wherever that is also standard PHP
practice, so both bindings classify a given response the same way:

- **Breaking.** `SuqoConfigError` extends `SuqoError` rather than
  `\InvalidArgumentException`, so `catch (SuqoError)` is total. Callers catching
  `\InvalidArgumentException` around client construction must switch.
- **Breaking.** An unmapped status raises `ServerError` rather than a bare
  `SuqoError`.
- `fieldErrors` collects nested errors into dotted paths, and renames a
  top-level `client` key to `customer`. `rawBody` still keeps wire names.
- `customers->*` no longer raises `NotImplementedError`; the operations are
  implemented. The exception type is kept but is currently raised by nothing.

Releases run on a train; see
[VERSIONING.md](VERSIONING.md#cutting-a-release). Merging a PR into `main` opens
a Release PR, and merging that cuts the release. Entries above move under a
dated version heading automatically, so add new ones under `## [Unreleased]` and
leave the rest alone.

[Unreleased]: https://github.com/Code-Pros-AI/sdk-php/commits/main
[0.1.0]: https://github.com/Code-Pros-AI/sdk-php/releases/tag/v0.1.0

