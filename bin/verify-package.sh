#!/usr/bin/env bash
#
# Validate a staged TekstTV plugin directory and, optionally, prove that a ZIP
# contains the exact same files and bytes.
set -euo pipefail

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=bin/package-lib.sh
source "$BIN_DIR/package-lib.sh"
FAIL_PREFIX="Package validation error"

PACKAGE_DIR="${1:-}"
ZIP_PATH="${2:-}"

if [[ -z "$PACKAGE_DIR" || ! -d "$PACKAGE_DIR" ]]; then
    fail "Usage: $0 <plugin-directory> [plugin-zip]"
fi
PACKAGE_DIR="$(cd "$PACKAGE_DIR" && pwd -P)"

REQUIRED_FILES=(
    "$SLUG.php"
    "README.md"
    "EXTENDING.md"
    "vendor/autoload.php"
    "${ASSET_FILES[@]/#/assets/}"
)

for required_file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$PACKAGE_DIR/$required_file" ]]; then
        fail "Required file '$required_file' is missing."
    fi
done

# Closed-world checks: the package may contain only the manifest's sources,
# the declared assets, and the Composer vendor directory - nothing else.
while IFS= read -r top_level_path; do
    top_level_name="$(basename "$top_level_path")"
    in_list "$top_level_name" "${TRACKED_PATHS[@]}" assets vendor \
        || fail "Unexpected top-level package entry '$top_level_name'."
done < <(find "$PACKAGE_DIR" -mindepth 1 -maxdepth 1 -print)

while IFS= read -r asset_path; do
    asset_name="${asset_path#"$PACKAGE_DIR/assets/"}"
    in_list "$asset_name" "${ASSET_FILES[@]}" \
        || fail "Unexpected generated asset 'assets/$asset_name'."
done < <(find "$PACKAGE_DIR/assets" -type f -print)

if find "$PACKAGE_DIR" -type l -print -quit | grep -q .; then
    fail "Symbolic links are not allowed in the production package."
fi

# The single-quoted snippet is evaluated by PHP.
# shellcheck disable=SC2016
PACKAGE_DIR_ENV="$PACKAGE_DIR" php -r '
    $packageDir = getenv("PACKAGE_DIR_ENV");
    require $packageDir . "/vendor/autoload.php";
    if (!class_exists("YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory")) {
        fwrite(STDERR, "Production updater dependency is not autoloadable.\n");
        exit(1);
    }
    $installed = require $packageDir . "/vendor/composer/installed.php";
    foreach ($installed["versions"] ?? [] as $name => $package) {
        if (($package["dev_requirement"] ?? false) === true) {
            fwrite(STDERR, "Development Composer package present: " . $name . "\n");
            exit(1);
        }
    }
' || fail "Composer production dependency validation failed."

if [[ -n "$ZIP_PATH" ]]; then
    if [[ ! -f "$ZIP_PATH" ]]; then
        fail "ZIP '$ZIP_PATH' does not exist."
    fi
    command -v unzip >/dev/null 2>&1 \
        || fail "Required command 'unzip' is not available."

    unzip -tq "$ZIP_PATH" >/dev/null \
        || fail "ZIP '$ZIP_PATH' failed its integrity check."

    VERIFY_TMP="$(mktemp -d "${TMPDIR:-/tmp}/teksttv-package-verify.XXXXXX")"
    cleanup() {
        rm -rf -- "$VERIFY_TMP"
    }
    trap cleanup EXIT

    unzip -q "$ZIP_PATH" -d "$VERIFY_TMP"
    if [[ ! -d "$VERIFY_TMP/$SLUG" ]]; then
        fail "ZIP does not contain the expected '$SLUG/' plugin directory."
    fi
    while IFS= read -r -d '' zip_top_level; do
        if [[ "$zip_top_level" != "$VERIFY_TMP/$SLUG" ]]; then
            fail "ZIP contains unexpected top-level entry '$(basename "$zip_top_level")'."
        fi
    done < <(find "$VERIFY_TMP" -mindepth 1 -maxdepth 1 -print0)

    if ! diff_report="$(diff -qr "$PACKAGE_DIR" "$VERIFY_TMP/$SLUG")"; then
        printf '%s\n' "$diff_report" >&2
        fail "ZIP contents differ from the validated plugin directory."
    fi
fi

echo "Package validation passed: $PACKAGE_DIR"
if [[ -n "$ZIP_PATH" ]]; then
    echo "ZIP parity validation passed: $ZIP_PATH"
fi
