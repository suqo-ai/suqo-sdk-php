#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Release plumbing. Three commands, each doing one mechanical step of a release
 * so that neither a human nor a workflow has to hand-edit a version string.
 *
 *   php tools/release.php next <patch|minor|major>
 *       Print the next version, derived from the newest v* tag. Prints 0.1.0
 *       when no tag exists yet.
 *
 *   php tools/release.php prepare <version>
 *       Move CHANGELOG's [Unreleased] entries under a dated heading for this
 *       version, open a fresh empty [Unreleased], and mark the version Shipped
 *       in VERSIONING.md's compatibility table (appending a row if absent).
 *
 *   php tools/release.php notes <version>
 *       Print that version's CHANGELOG section, for a GitHub Release body.
 *
 *   php tools/release.php suggest
 *       Print the bump the commits since the last tag imply, or "none" when
 *       nothing releasable has landed.
 *
 *       An explicit `Release-As: X.Y.Z` wins outright and is printed verbatim.
 *       It is read from two places: the CHANGELOG's [Unreleased] section, and
 *       any commit message since the last tag. The CHANGELOG is checked first
 *       and is the reliable one -- a commit footer does not survive a squash
 *       merge, which would silently downgrade a release to "none".
 *
 *   php tools/release.php pending
 *       Print the newest version in CHANGELOG that has no matching git tag --
 *       i.e. a release that has been prepared and merged but not yet tagged.
 *       Empty when there is none. Merge-strategy agnostic, so it works whether
 *       the release PR was squashed, rebased or merged.
 *
 * The version itself is never written into composer.json: the git tag is the
 * only source of truth (see VERSIONING.md). This script only touches prose.
 */

const CHANGELOG = __DIR__ . '/../CHANGELOG.md';
const VERSIONING = __DIR__ . '/../VERSIONING.md';

/** @param list<string> $argv */
function main(array $argv): int
{
    $command = $argv[1] ?? '';
    $argument = $argv[2] ?? '';

    return match ($command) {
        'next' => cmdNext($argument),
        'prepare' => cmdPrepare($argument),
        'notes' => cmdNotes($argument),
        'suggest' => cmdSuggest(),
        'pending' => cmdPending(),
        default => fail(
            "usage: release.php next <patch|minor|major>\n"
            . "       release.php prepare <version>\n"
            . "       release.php notes <version>\n"
            . "       release.php suggest\n"
            . "       release.php pending"
        ),
    };
}

function fail(string $message): int
{
    fwrite(STDERR, $message . PHP_EOL);

    return 1;
}

function assertVersion(string $version): void
{
    if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
        exit(fail("Not a release version: '{$version}'. Expected MAJOR.MINOR.PATCH."));
    }
}

/** The newest v* tag, or null when the repository has none yet. */
function latestTag(): ?string
{
    exec('git tag -l "v*.*.*" --sort=-v:refname', $lines, $status);

    if ($status !== 0 || $lines === []) {
        return null;
    }

    return ltrim(trim($lines[0]), 'v');
}

function cmdNext(string $bump): int
{
    if (!in_array($bump, ['patch', 'minor', 'major'], true)) {
        return fail("Unknown bump '{$bump}'. Expected patch, minor or major.");
    }

    $latest = latestTag();

    // No tags yet: the first release is 0.1.0 regardless of the bump asked for,
    // because there is nothing to bump from. VERSIONING.md explains why the
    // first release is deliberately 0.x.
    if ($latest === null) {
        echo '0.1.0', PHP_EOL;

        return 0;
    }

    [$major, $minor, $patch] = array_map('intval', explode('.', $latest));

    [$major, $minor, $patch] = match ($bump) {
        'major' => [$major + 1, 0, 0],
        'minor' => [$major, $minor + 1, 0],
        'patch' => [$major, $minor, $patch + 1],
    };

    echo "{$major}.{$minor}.{$patch}", PHP_EOL;

    return 0;
}

function cmdPrepare(string $version): int
{
    assertVersion($version);

    $changelog = file_get_contents(CHANGELOG);
    $versioning = file_get_contents(VERSIONING);

    if ($changelog === false || $versioning === false) {
        return fail('Could not read CHANGELOG.md or VERSIONING.md.');
    }

    if (str_contains($changelog, "## [{$version}]")) {
        return fail("CHANGELOG.md already has a section for {$version}.");
    }

    $unreleased = unreleasedBody($changelog);

    if ($unreleased === null) {
        return fail('CHANGELOG.md has no "## [Unreleased]" heading.');
    }

    if (trim($unreleased) === '') {
        return fail('Nothing to release: the [Unreleased] section is empty.');
    }

    $date = date('Y-m-d');

    $changelog = str_replace(
        "## [Unreleased]\n" . $unreleased,
        "## [Unreleased]\n\n_Nothing yet._\n\n## [{$version}] - {$date}\n" . rtrim($unreleased) . "\n\n",
        $changelog,
    );

    file_put_contents(CHANGELOG, $changelog);
    file_put_contents(VERSIONING, shipRow($versioning, $version));

    echo "Prepared {$version}.", PHP_EOL;

    return 0;
}

/** Everything between the [Unreleased] heading and the next "## " heading. */
function unreleasedBody(string $changelog): ?string
{
    $start = strpos($changelog, "## [Unreleased]\n");

    if ($start === false) {
        return null;
    }

    $start += strlen("## [Unreleased]\n");
    $next = strpos($changelog, "\n## ", $start);

    return $next === false
        ? substr($changelog, $start)
        : substr($changelog, $start, $next - $start + 1);
}

/**
 * Mark the version Shipped in the compatibility table, appending a row when the
 * table does not already carry one for it.
 */
function shipRow(string $versioning, string $version): string
{
    $existing = '/^\| `' . preg_quote($version, '/') . '` \|.*\|$/m';

    if (preg_match($existing, $versioning, $match) === 1) {
        $shipped = preg_replace('/\|\s*[A-Za-z]+\s*\|$/', '| Shipped |', $match[0]);

        return str_replace($match[0], (string) $shipped, $versioning);
    }

    // Append after the last row of the table.
    $lines = explode("\n", $versioning);
    $lastRow = null;

    foreach ($lines as $index => $line) {
        if (preg_match('/^\| `\d+\.\d+\.\d+` \|/', $line) === 1) {
            $lastRow = $index;
        }
    }

    if ($lastRow === null) {
        fwrite(STDERR, "warning: no compatibility table row found; VERSIONING.md not updated.\n");

        return $versioning;
    }

    array_splice($lines, $lastRow + 1, 0, "| `{$version}` | `v1` | See CHANGELOG | Shipped |");

    return implode("\n", $lines);
}

/**
 * The bump implied by the commits since the last tag.
 *
 * Conventional Commits cannot see every breaking change this SDK makes -- a
 * widened property type or a reordered named argument can ship under `fix:`
 * (VERSIONING.md) -- so this is a *suggestion*. A `Release-As: X.Y.Z` footer
 * overrides it, and the release PR's version can be edited before merging.
 */
function cmdSuggest(): int
{
    // An explicit version pinned in the tree. Checked before the commit log
    // because it survives a squash merge, which rewrites commit messages and
    // would otherwise drop a `Release-As:` footer on the floor.
    $changelog = file_get_contents(CHANGELOG);

    if ($changelog !== false) {
        $unreleased = unreleasedBody($changelog) ?? '';

        if (preg_match('/Release-As:\s*v?(\d+\.\d+\.\d+)/i', $unreleased, $pinned) === 1) {
            echo $pinned[1], PHP_EOL;

            return 0;
        }
    }

    $range = latestTag() === null ? 'HEAD' : 'v' . latestTag() . '..HEAD';

    exec('git log --no-merges --format=%B ' . escapeshellarg($range), $lines, $status);

    if ($status !== 0) {
        return fail('Could not read the commit log.');
    }

    $log = implode("\n", $lines);

    // The same directive in a commit footer. Kept as a convenience, but the
    // CHANGELOG form above is the one to rely on.
    if (preg_match('/^Release-As:\s*v?(\d+\.\d+\.\d+)\s*$/mi', $log, $match) === 1) {
        echo $match[1], PHP_EOL;

        return 0;
    }

    $breaking = preg_match('/^BREAKING[ -]CHANGE:/mi', $log) === 1
        || preg_match('/^[a-z]+(\([^)]*\))?!:/mi', $log) === 1;
    $feature = preg_match('/^feat(\([^)]*\))?!?:/mi', $log) === 1;
    $fix = preg_match('/^(fix|perf|refactor|revert)(\([^)]*\))?!?:/mi', $log) === 1;

    // Plain SemVer. The 0.x demotion this used to apply -- breaking bumps the
    // minor while under 1.0 -- is gone along with the pre-1.0 policy it served.
    echo match (true) {
        $breaking => 'major',
        $feature => 'minor',
        $fix => 'patch',
        default => 'none',
    }, PHP_EOL;

    return 0;
}

/** A version prepared in CHANGELOG but not yet tagged, if any. */
function cmdPending(): int
{
    $changelog = file_get_contents(CHANGELOG);

    if ($changelog === false) {
        return fail('Could not read CHANGELOG.md.');
    }

    if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $match) !== 1) {
        return 0;
    }

    $version = $match[1];

    exec('git rev-parse -q --verify ' . escapeshellarg('refs/tags/v' . $version), $out, $status);

    if ($status === 0) {
        return 0;
    }

    echo $version, PHP_EOL;

    return 0;
}

function cmdNotes(string $version): int
{
    assertVersion($version);

    $changelog = file_get_contents(CHANGELOG);

    if ($changelog === false) {
        return fail('Could not read CHANGELOG.md.');
    }

    $start = strpos($changelog, "## [{$version}]");

    if ($start === false) {
        return fail("CHANGELOG.md has no section for {$version}.");
    }

    $start = (int) strpos($changelog, "\n", $start) + 1;
    $next = strpos($changelog, "\n## ", $start);

    echo trim($next === false ? substr($changelog, $start) : substr($changelog, $start, $next - $start)), PHP_EOL;

    return 0;
}

exit(main($argv));
