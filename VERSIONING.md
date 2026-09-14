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

## Cutting a release

Releases run on a train. **You never tag by hand, and you never edit a version
string.**

1. You merge an ordinary PR into `main`.
2. **Release train** runs and opens a single **Release PR** on the branch
   `release/next`. It contains nothing but the `CHANGELOG.md` and
   `VERSIONING.md` edits for the next version. It stays open and re-prepares
   itself as further PRs land, so at any moment it shows exactly what the next
   release would be.
3. When you merge the Release PR, the train runs again, sees a version in the
   CHANGELOG with no matching tag, and cuts the release: annotated tag, GitHub
   Release with that CHANGELOG section as its body, and a Packagist update.

Merging the Release PR is the release decision. Until you merge it, nothing is
tagged and nothing is published.

Which path the train takes is decided by asking *"is there a prepared version
with no tag?"*, not by pattern-matching a commit subject — so squash, rebase and
merge commits all work.

### Choosing the version

The train suggests a bump from the Conventional Commit prefixes since the last
tag, using the pre-1.0 rules in this document: while under `1.0.0`, a breaking
change bumps the **minor** and a `feat:` bumps the **patch**.

**Treat that as a suggestion, not a verdict.** The prefixes cannot see every
breaking change this SDK makes — the table above lists cases where widening a
property type or reordering a named argument ships under `fix:`, and exactly
that has already happened here. To override, put a footer on any commit on
`main`:

```
Release-As: 0.2.0
```

The train picks it up and re-prepares the Release PR at that version.

### Running the steps by hand

The plumbing is a script, so nothing is locked inside the workflow:

```bash
php tools/release.php suggest         # the bump the commits imply
php tools/release.php next minor      # what that version would be
php tools/release.php prepare 0.2.0   # rewrite CHANGELOG + the table below
php tools/release.php notes 0.2.0     # print that section
php tools/release.php pending         # a prepared version that is not tagged
```

`prepare` refuses to run twice for the same version, and refuses to run at all
when `[Unreleased]` is empty — a release with nothing in it is a mistake.

### Two things that will bite you

**A PR opened by the bot does not trigger `pull_request` workflows.** GitHub
deliberately prevents that recursion for the built-in token. So CI will not run
on the Release PR. That is usually fine, since it only touches two Markdown
files — but if `main` requires status checks, the PR cannot be merged, because
the checks never start. Either exempt `release/next`, or give the workflow a PAT
instead of the built-in token so the PR is attributed to a user.

Note that the train does **not** push commits to `main`; the release commit
arrives through the PR you merge. Ordinary branch protection is therefore not a
problem — only required status checks are.

**Packagist is notified by the train,** not by `release.yml`. A tag pushed with
the built-in token does not trigger other workflows, so `release.yml`'s
`on: push: tags` never fires for a train release. `release.yml` remains the path
for a tag pushed by hand from a laptop.

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
