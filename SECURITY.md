# Security policy — `suqo/sdk-php`

## Reporting a vulnerability

Report privately through GitHub's [private vulnerability
reporting](https://github.com/Code-Pros-AI/sdk-php/security/advisories/new) on
this repository. Please do not open a public issue for anything exploitable.

Include the SDK version, the PHP version, and the smallest reproduction you
have. You will get an acknowledgement, and a fix or an explanation of why it
isn't one.

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | ✅        |
| 0.x     | ❌        |

## Runtime requirements

This package is a client library: it holds your API key, signs requests, and
verifies webhook signatures. None of that is safe on an interpreter with known
remote-code-execution bugs, and a Composer constraint cannot fix one — only the
PHP on the machine can. Patching the runtime is the operator's job, so the
versions matter here.

Two critical flaws in PHP's request handling were disclosed on 22 October 2026:

- **CVE-2026-109618** (CVSS 9.2) — out-of-memory handling via `longjmp()` leaves
  request state incomplete; shutdown then walks the corrupted state. Remote code
  execution was demonstrated against `mod_php` at default input and memory
  limits, unauthenticated.
- **CVE-2026-109619** (CVSS 9.2) — global state referencing request-owned memory
  survives the request that owned it, giving a use-after-free on the next one.
  Confirmed RCE against both `mod_php` and PHP-FPM, unauthenticated.

Both are reachable through ordinary HTTP request parsing — multipart bodies,
header counts, session upload progress — so any host serving traffic is in
scope, whether or not it uses this SDK on those paths.

Run at least:

| PHP line | Minimum patched release |
| -------- | ----------------------- |
| 8.2      | 8.2.35                  |
| 8.3      | 8.3.36                  |
| 8.4      | 8.4.27                  |
| 8.5      | 8.5.12                  |

**PHP 8.1 reached end of security support on 31 December 2025 and will not
receive these fixes.** `composer.json` still declares `"php": "^8.1"`, because
narrowing it would break installs without patching anyone — the constraint
describes what this package's code needs, not what your server is safe to run.
Treat 8.2.35 as the real floor for production and plan the move off 8.1.

Verify with:

```bash
php -v
```

Upstream details: [PHP 8 ChangeLog](https://www.php.net/ChangeLog-8.php),
[GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w),
[GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).

## Handling credentials

- Keep API keys in the environment (`SUQO_API_KEY`), not in version control.
  The client reads it for you — see the [README](README.md).
- A `su_key_` prefix is a live key. The SDK refuses to let `environment:`
  contradict the prefix, but it cannot tell you that a live key leaked.
- Verify webhook signatures with `Suqo\Webhook::verify()` before trusting a
  payload. Rotate the signing secret if a verification failure is ever caused
  by anything but a bad request.
