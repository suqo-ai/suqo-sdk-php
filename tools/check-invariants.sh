#!/usr/bin/env bash
# §5.1 — the mechanically checkable invariants, as a CI grep gate.
#
# I1: exactly one place in the SDK performs an HTTP call.
# I4: every URL path literal lives in one endpoint table.
# I6: the read/write retry decision is a single constant.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

fail=0

check() {
    local label="$1" expected="$2" pattern="$3"
    local actual
    actual=$(grep -rlE "$pattern" src --include='*.php' | sed 's|^src/||' | sort | paste -sd, -)

    if [ "$actual" != "$expected" ]; then
        printf 'FAIL %s\n  expected: %s\n  actual:   %s\n' "$label" "$expected" "${actual:-<none>}"
        fail=1
    else
        printf 'ok   %s (%s)\n' "$label" "$expected"
    fi
}

check "I1 single HTTP call site"   "Http/Transport.php"                     '\->send[[:space:]]*\('
check "I1 cURL confined"           "Http/CurlHttpClient.php"               'curl_(init|exec|setopt_array|getinfo|errno|error)[[:space:]]*\('
check "I4 single endpoint table"   "Endpoints.php"                         "['\"]/api/"
check "I6 retry constant declared once" "Constants.php"                    'WRITES_RETRYABLE'
check "I6 retry constant read once"     "Constants.php,Http/RetryPolicy.php" 'writesRetryable\('

exit "$fail"
