#!/usr/bin/env bash
#
# End-to-end audit of the packaging pipeline, runnable locally and in CI:
#   1. the packager must refuse a checkout without built assets, and
#   2. untracked canary files planted in the source tree must never reach
#      the packaged artifact.
# Requires JS dependencies (bun install) for the build:package run.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
# shellcheck source=bin/package-lib.sh
source "$ROOT/bin/package-lib.sh"
FAIL_PREFIX="Packaging self-test error"

command -v bun >/dev/null 2>&1 \
    || fail "Required command 'bun' is not available."

CANARIES=(
    "PACKAGE_AUDIT_CANARY.md"
    "src/package-audit-canary.php"
    "assets/package-audit-canary.js"
)

# Refuse before the cleanup trap is armed: an existing file with a canary name
# is not ours to overwrite or delete.
for canary in "${CANARIES[@]}"; do
    if [[ -e "$ROOT/$canary" ]]; then
        fail "Canary path '$canary' already exists; remove it before running this test."
    fi
done

ASSETS_STASH=""
cleanup() {
    local canary
    for canary in "${CANARIES[@]}"; do
        rm -f -- "$ROOT/$canary"
    done
    if [[ -n "$ASSETS_STASH" ]]; then
        if [[ -d "$ASSETS_STASH/assets" ]]; then
            rm -rf -- "$ROOT/assets"
            mv "$ASSETS_STASH/assets" "$ROOT/assets"
        fi
        rm -rf -- "$ASSETS_STASH"
    fi
}
trap cleanup EXIT

# 1. Preflight: with assets/ absent the packager must fail on the asset check.
if [[ -d "$ROOT/assets" ]]; then
    ASSETS_STASH="$(mktemp -d "${TMPDIR:-/tmp}/teksttv-assets-stash.XXXXXX")"
    mv "$ROOT/assets" "$ASSETS_STASH/assets"
fi
if OUTPUT=$(bash "$ROOT/bin/package-plugin.sh" 2>&1); then
    echo "$OUTPUT"
    fail "Packager accepted a checkout without built assets."
fi
if ! grep -Fq "Required built asset 'assets/${ASSET_FILES[0]}' is missing" <<< "$OUTPUT"; then
    echo "$OUTPUT"
    fail "Packager failed for an unexpected reason."
fi
if [[ -n "$ASSETS_STASH" ]]; then
    mv "$ASSETS_STASH/assets" "$ROOT/assets"
    rm -rf -- "$ASSETS_STASH"
    ASSETS_STASH=""
fi

# 2. Canary audit: plant untracked files at every copy surface (repo root,
#    tracked src/ tree, generated assets/) and prove the package excludes them.
mkdir -p "$ROOT/assets"
printf 'root canary\n' > "$ROOT/PACKAGE_AUDIT_CANARY.md"
printf '<?php // source canary\n' > "$ROOT/src/package-audit-canary.php"
printf '// asset canary\n' > "$ROOT/assets/package-audit-canary.js"

(cd "$ROOT" && bun run build:package)

for canary in "${CANARIES[@]}"; do
    if [[ -e "$ROOT/release/$SLUG/$canary" ]]; then
        fail "Canary '$canary' leaked into the packaged artifact."
    fi
done

echo "Packaging self-test passed."
