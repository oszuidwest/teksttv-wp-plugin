#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
# ROOT is resolved from this script's location.
# shellcheck disable=SC1091
source "$ROOT/bin/package-lib.sh"

validate_plugin_version "$ROOT/teksttv.php" >/dev/null
printf 'PASS: valid production version header\n'

FIXTURE="$(mktemp "${TMPDIR:-/tmp}/teksttv-version-test.XXXXXX")"
trap 'rm -f -- "$FIXTURE"' EXIT

sed 's/^\( \* Version:\).*/\1 invalid/' "$ROOT/teksttv.php" > "$FIXTURE"
if (validate_plugin_version "$FIXTURE" >/dev/null 2>&1); then
    echo "FAIL: invalid production version was accepted" >&2
    exit 1
fi
printf 'PASS: invalid production version is rejected\n'
