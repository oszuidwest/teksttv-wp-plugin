#!/usr/bin/env bash
#
# Print the validated plugin version from the main file's Version header.
# Single source for CI and the packager, so the two can never disagree.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
# shellcheck source=bin/package-lib.sh
source "$ROOT/bin/package-lib.sh"

read_plugin_version "$ROOT/$SLUG.php"
