#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
# ROOT is resolved from this script's location.
# shellcheck disable=SC1091
source "$ROOT/bin/package-lib.sh"

FIXTURE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/teksttv-version-test.XXXXXX")"
cleanup() {
    rm -rf -- "$FIXTURE_DIR"
}
trap cleanup EXIT

FIXTURE="$FIXTURE_DIR/teksttv.php"
cp "$ROOT/teksttv.php" "$FIXTURE"

EXPECTED="$(read_plugin_version "$FIXTURE")"
ACTUAL="$(validate_plugin_version "$FIXTURE")"
[[ "$ACTUAL" == "$EXPECTED" ]]
printf 'PASS: matching production versions\n'

sed -i.bak 's/^\( \* Version:\).*/\1 9.9.9/' "$FIXTURE"
rm -f -- "$FIXTURE.bak"
if (validate_plugin_version "$FIXTURE" >/dev/null 2>&1); then
    echo "FAIL: mismatched production versions were accepted" >&2
    exit 1
fi
printf 'PASS: mismatched production versions are rejected\n'
