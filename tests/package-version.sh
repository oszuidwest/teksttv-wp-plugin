#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
# ROOT is resolved from this script's location.
# shellcheck disable=SC1091
source "$ROOT/bin/package-lib.sh"

validate_plugin_version "$ROOT/teksttv.php" >/dev/null
printf 'PASS: matching production versions\n'

FIXTURE="$(mktemp "${TMPDIR:-/tmp}/teksttv-version-test.XXXXXX")"
trap 'rm -f -- "$FIXTURE"' EXIT

sed 's/^\( \* Version:\).*/\1 9.9.9/' "$ROOT/teksttv.php" > "$FIXTURE"
if (validate_plugin_version "$FIXTURE" >/dev/null 2>&1); then
    echo "FAIL: mismatched production versions were accepted" >&2
    exit 1
fi
printf 'PASS: mismatched production versions are rejected\n'
