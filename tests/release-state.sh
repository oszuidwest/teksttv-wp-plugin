#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
SCRIPT="$ROOT/bin/release-state.sh"

assert_state() {
    local name="$1" expected_release="$2" expected_tag="$3"
    shift 3
    local output
    output="$(bash "$SCRIPT" "$@")"
    grep -Fxq "release_needed=$expected_release" <<< "$output"
    grep -Fxq "tag_exists=$expected_tag" <<< "$output"
    printf 'PASS: %s\n' "$name"
}

assert_failure() {
    local name="$1"
    shift
    if bash "$SCRIPT" "$@" >/dev/null 2>&1; then
        printf 'FAIL: %s unexpectedly succeeded\n' "$name" >&2
        exit 1
    fi
    printf 'PASS: %s\n' "$name"
}

assert_state "first release" true false 0.0.3 current "" "" false
assert_state "new version" true false 0.0.4 current "" 0.0.3 false
assert_state "stable follows release candidate" true false 0.0.4 current "" 0.0.4-rc.1 false
assert_state "release candidate increments" true false 0.0.4-rc.2 current "" 0.0.4-rc.1 false
assert_state "repair missing release" true true 0.0.3 current current 0.0.3 false
assert_state "already published" false true 0.0.3 current current 0.0.3 true
assert_failure "immutable tag on another commit" 0.0.3 current previous 0.0.3 true
assert_failure "version must increase" 0.0.3 current "" 0.0.3 false
assert_failure "prerelease cannot replace stable" 0.0.4-rc.1 current "" 0.0.4 false
