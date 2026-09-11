# SUQO PHP SDK playground

A small local web app that drives the SDK. The API key is typed into the
homepage — nothing in this project has to be edited to change keys, environments,
timeouts or retry counts.

## Run it

From the repository root:

```bash
composer install          # once
composer playground
```

Then open <http://127.0.0.1:8000>.

Without Composer scripts, the same thing:

```bash
php -S 127.0.0.1:8000 -t examples/playground examples/playground/index.php
```

Stop it with Ctrl-C. Pick a different port by editing the command, not the code.

## What's on each page

| Page | SDK surface exercised |
| --- | --- |
| **Connect** (`/`) | `Config::resolve()` — prefix check and environment inference, entirely offline |
| **Overview** (`/`) | resolved config; `products->list(pageSize: 1)` as a connectivity check |
| **Products** (`/products`) | `products->list()` page by page, and `products->autoPaging()` walking every page lazily |
| **Subscriptions** (`/subscriptions`) | `subscriptions->list()`, `cancel()`, `updateBillingCycle()`; status counts; the `client` → `customer` rename |
| **Create** (`/subscriptions/new`) | `subscriptions->create()`, showing the exact request body that went out |
| **Customers** (`/customers`) | `customers->list()` — the read-only customer records, with nulls shown as nulls |
| **Webhooks** (`/webhook`) | `Webhook::verify()` — no client, no key, no network. "Sign it for me" produces a valid signature so the page is demonstrable without a real delivery |

Errors are rendered rather than thrown: type, HTTP status, request id, field errors,
`kycStatus` where applicable, `retryAfter` where the server sent one, and the raw
body with wire names intact.

## About the key

The key is held in the PHP session for your browser and nowhere else. It is not
written into any file in this project, never appears in a URL, and is never
rendered back to the page — the header shows a masked form. **Disconnect** clears
it and rotates the session id.

Two things worth knowing before you point this at a live key:

- PHP writes session data to a file on disk (usually under the system temp
  directory), so the key does touch the filesystem for as long as the session
  lives.
- The server binds to `127.0.0.1`, so it is not reachable from your network. Keep
  it that way, and prefer a `su_test_key_` sandbox key.

This is a development tool. It has no place in a deployment.

## Notes

- Forms that change remote state carry a CSRF token and reject a mismatch.
- Every request goes through the SDK's transport, so retries, backoff, request ids
  and error mapping behave exactly as they do in your own code.
- No JavaScript, no build step, no assets — the CSS is inline in `views/layout.php`
  and adapts to your system light/dark setting.
