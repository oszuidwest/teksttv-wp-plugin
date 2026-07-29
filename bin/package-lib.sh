#!/usr/bin/env bash
#
# Shared manifest and helpers for the packaging scripts. Source this file;
# it is the single source of truth for what a production package contains.
# shellcheck disable=SC2034  # the arrays are consumed by sourcing scripts

SLUG="teksttv"

# Tracked production sources copied into the package.
TRACKED_PATHS=(
    "$SLUG.php"
    "src"
    "README.md"
    "EXTENDING.md"
)

# Generated files the Bun build must produce; the package ships exactly these.
ASSET_FILES=(
    "admin.css"
    "admin.js"
    "tinymce-content.css"
    "tinymce-separator.js"
    "tom-select.complete.min.js"
    "tom-select.default.min.css"
)

fail() {
    echo "${FAIL_PREFIX:-Packaging error}: $*" >&2
    exit 1
}

in_list() {
    local needle="$1" candidate
    shift
    for candidate in "$@"; do
        if [[ "$candidate" == "$needle" ]]; then
            return 0
        fi
    done
    return 1
}

read_plugin_version() {
    local main_file="$1" version
    version="$(
        sed -n \
            's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' \
            "$main_file" \
            | head -n 1
    )"
    if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[0-9]+)?$ ]]; then
        fail "Invalid or missing Version header in $main_file: '$version'."
    fi
    echo "$version"
}
