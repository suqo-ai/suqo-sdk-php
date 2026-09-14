# Versioning policy — `suqo/sdk-php`

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
   CHANGELOG with no matching tag, and cuts the release: an annotated tag and a
   GitHub Release with that CHANGELOG section as its body. Packagist picks the
   tag up on its own — see below.

Merging the Release PR is the release decision. Until you merge it, nothing is
tagged and nothing is published.

Which path the train takes is decided by asking *"is there a prepared version
with no tag?"*, not by pattern-matching a commit subject — so squash, rebase and
merge commits all work.

### Choosing the version

The train suggests a bump from the Conventional Commit prefixes since the last
tag, by plain SemVer: a breaking change bumps the **major**, a `feat:` the
**minor**, a `fix:` the **patch**.

**Treat that as a suggestion, not a verdict.** The prefixes cannot see every
breaking change this SDK makes — the table above lists cases where widening a
property type or reordering a named argument ships under `fix:`. Nor is there
any prefix that means "promote to 1.0.0", which is a statement about stability
rather than a change.

To pin an exact version, put the directive in the CHANGELOG's `[Unreleased]`
section, as an HTML comment so it does not render:

```markdown
## [Unreleased]

<!-- Release-As: 1.0.0 -->
```

The train reads it and prepares the Release PR at that version, then the
directive disappears with the rest of `[Unreleased]` when the section is moved
under its dated heading — so it applies to exactly one release and cannot leak
into the next.

The same directive also works as a commit footer:

```
Release-As: 1.0.0
```

**Prefer the CHANGELOG form.** A commit footer does not survive a squash merge:
GitHub composes a fresh message from the PR title and body, the footer is
dropped, and the train falls back to prefix inference. For a release whose only
signal was that footer, inference returns `none` and **no Release PR appears at
all** — a failure that looks exactly like the train not running.

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

**Nothing in CI calls Packagist.** Indexing is handled by the Packagist GitHub
App, which is subscribed to this repository's push events. A tag push is a push
event whoever made it — the `GITHUB_TOKEN` restriction that stops a bot-pushed
tag triggering *workflows* does not apply to outbound webhooks — so the App sees
the train's tags and hand-pushed ones alike. That is why no Packagist API token
is kept in CI secrets.

The consequence worth knowing: `release.yml`, which runs the gate on a tag push,
never fires for a train release, because the train's tag comes from the built-in
token. It is there for a tag pushed by hand from a laptop, so that a tag on a
commit CI never saw is loud rather than silently published.

If a release ever fails to appear on Packagist, check the App's delivery log
under the repository's Settings → Integrations before suspecting the train — the
tag and the GitHub Release will already exist.

## SDK version ↔ API version compatibility

Every real release adds a row, marked `Shipped` once it is actually published.

| SDK version | Supported API version | Change type | Status |
| --- | --- | --- | --- |
| `0.1.0` | `v1` | Initial release | Shipped |
| `1.0.0` | `v1` | See CHANGELOG | Shipped |

## Stability, from 1.0.0

`1.0.0` is the point at which the public surface stops moving without a major
version. Concretely, from that release onwards:

- A breaking change — anything in the table above marked *yes* — requires a
  **MAJOR** bump, and is announced one minor in advance wherever the change can
  be deprecated rather than simply made.
- The operations openapi declares but this SDK does not yet expose
  (`subscriptions_read`, `subscriptions_resume`, the Customers write operations,
  the Webhooks management resource) are **additive**. Each lands as a **MINOR**,
  never a major, because adding a method breaks nobody.
- `0.1.0` and `1.0.0` are the same code. The promotion is a statement about
  stability, not a change in behaviour.

The `0.x` rule that preceded this — where the minor acted as the major and no
compatibility was promised — no longer applies. It is recorded here only so the
`0.1.0` row below reads correctly in hindsight.

