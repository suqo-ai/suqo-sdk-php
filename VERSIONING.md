# Versioning policy — `suqo/suqo-php`

Applies to this package. The policy itself is language-agnostic and is shared
with every SUQO SDK; the TypeScript binding keeps its own copy at
`specs/versioning.md`. Keep the two in agreement — if this file and that one ever
disagree, the specification's §13 wins.

## The rule

Strict [SemVer](https://semver.org/): `MAJOR.MINOR.PATCH`.

**MAJOR** — bump on *either* trigger, whichever happens first:

1. **The API's path version changes** (`/api/v1/` → `/api/v2/`). §13 states this
   one directly: the major version tracks the API path version. A wire-breaking
   backend change always forces a new SDK major.
2. **The SDK makes its own breaking change with no API change at all.** Renaming
   a public class or method, changing a return shape, changing an exception's
   base class, narrowing a parameter type. These break callers even though the
   wire contract underneath did not move.

**MINOR** — additive and backward-compatible: a new resource, a new optional
named argument, a new field on a record, a new error type that only narrows an
existing one's cases.

**PATCH** — fixes only, no contract change of any kind.

## Where the version lives

**The git tag is the version.** `composer.json` deliberately has no `version`
field: Packagist reads the tag, and a second copy in the manifest would drift.
`composer validate --strict` warns if one is ever added.

There is no `VERSION` constant in the source either. Nothing in the SDK sends its
own version to the API today — if a `User-Agent` is added later, it must be fed
from a single constant, and that constant becomes a release-checklist item.

## What counts as breaking here

PHP-specific cases that are easy to get wrong:

| Change | Breaking? |
| --- | --- |
| Adding an optional named argument **after** the existing ones | no |
| Adding a named argument **between** existing ones | yes — positional callers shift |
| Adding a constructor argument to a `Params` object, with a default, last | no |
| Adding a public readonly property to a model | no |
| Changing a property's type, including widening `?int` → `?string` | yes |
| Changing an exception's parent class | yes |
| Making a previously-throwing method actually work | no — it is additive |
| Adding a new `SubscriptionStatus` enum case | no — decoding is tolerant by §9.3 |
| Renaming a wire key the SDK reads | no — surface is unchanged |

Every model has a private constructor and is `final`, so callers cannot
instantiate or subclass one; that keeps most model changes additive by
construction.

## Deprecation

Anything to be removed is deprecated one minor ahead: documented in the
CHANGELOG, marked `@deprecated` in the docblock, and left working. It is removed
only at the next major. Nothing is ever removed in a minor or a patch.

## SDK version ↔ API version compatibility

Every real release adds a row, marked `Shipped` once it is actually published.

| SDK version | Supported API version | Change type | Status |
| --- | --- | --- | --- |
| `0.1.0` | `v1` | Initial release | Pending |

## Pre-1.0

While the version is `0.x`, SemVer makes no compatibility promise and the minor
acts as the major: `0.1.0` → `0.2.0` may break. That is deliberate for the first
releases, because several parts of the surface are still being reconciled against
the live API. The `0.1.0` work already included breaking changes to model types
and to `SuqoConfigError`'s base class, and more are likely as the remaining
unexposed operations land.

Tag `1.0.0` only when you are willing to hold the current public surface stable,
and are content that the unexposed operations (`subscriptions.read`,
`subscriptions.resume`, the Customers write operations, the Webhooks management
resource) landing later are *minor* bumps.
