#!/usr/bin/env bash
#
# Evaluate an immutable release from already-resolved GitHub and Git state.
set -euo pipefail

VERSION="${1:-}"
CURRENT_COMMIT="${2:-}"
TAG_COMMIT="${3:-}"
LATEST_TAG="${4:-}"
RELEASE_EXISTS="${5:-false}"

if [[ -z "$VERSION" || -z "$CURRENT_COMMIT" ]]; then
    echo "Usage: $0 <version> <current-commit> [tag-commit] [latest-tag] [release-exists]" >&2
    exit 1
fi
if [[ "$RELEASE_EXISTS" != "true" && "$RELEASE_EXISTS" != "false" ]]; then
    echo "release-exists must be 'true' or 'false'." >&2
    exit 1
fi

version_gt() {
    # The single-quoted snippet is evaluated by PHP.
    # shellcheck disable=SC2016
    php -r 'exit(version_compare($argv[1], $argv[2], ">") ? 0 : 1);' "$1" "$2"
}

if [[ -n "$TAG_COMMIT" ]]; then
    if [[ "$TAG_COMMIT" != "$CURRENT_COMMIT" ]]; then
        echo "Tag $VERSION already points to $TAG_COMMIT; bump the plugin version instead of moving an immutable tag." >&2
        exit 1
    fi
    if [[ "$RELEASE_EXISTS" == "true" ]]; then
        RELEASE_NEEDED=false
        REASON="tag and release already exist for this commit"
    else
        RELEASE_NEEDED=true
        REASON="repair missing release for existing immutable tag"
    fi
    TAG_EXISTS=true
elif [[ -z "$LATEST_TAG" ]]; then
    RELEASE_NEEDED=true
    REASON="first release"
    TAG_EXISTS=false
elif version_gt "$VERSION" "${LATEST_TAG#v}"; then
    RELEASE_NEEDED=true
    REASON="version bumped from $LATEST_TAG to $VERSION"
    TAG_EXISTS=false
else
    echo "Plugin version $VERSION is not newer than latest tag $LATEST_TAG." >&2
    exit 1
fi

printf 'release_needed=%s\ntag_exists=%s\nreason=%s\n' \
    "$RELEASE_NEEDED" \
    "$TAG_EXISTS" \
    "$REASON"
