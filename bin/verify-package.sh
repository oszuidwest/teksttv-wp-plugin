#!/usr/bin/env bash
#
# Validate a staged TekstTV plugin directory and, optionally, prove that a ZIP
# contains the exact same files and bytes.
set -euo pipefail

PACKAGE_DIR="${1:-}"
ZIP_PATH="${2:-}"

fail() {
    echo "Package validation error: $*" >&2
    exit 1
}

if [[ -z "$PACKAGE_DIR" || ! -d "$PACKAGE_DIR" ]]; then
    fail "Usage: $0 <plugin-directory> [plugin-zip]"
fi
PACKAGE_DIR="$(cd "$PACKAGE_DIR" && pwd -P)"
SLUG="$(basename "$PACKAGE_DIR")"

REQUIRED_FILES=(
    "teksttv.php"
    "README.md"
    "EXTENDING.md"
    "vendor/autoload.php"
    "assets/admin.css"
    "assets/admin.js"
    "assets/tinymce-content.css"
    "assets/tinymce-separator.js"
    "assets/tom-select.complete.min.js"
    "assets/tom-select.default.min.css"
)

FORBIDDEN_PATHS=(
    ".git"
    ".github"
    ".gitignore"
    ".wp-env.json"
    "bin"
    "biome.json"
    "bun.lock"
    "composer.json"
    "composer.lock"
    "node_modules"
    "package.json"
    "patchwork.json"
    "phpcs.xml"
    "phpstan-bootstrap.php"
    "phpstan.neon"
    "phpunit.xml"
    "playwright.config.ts"
    "resources"
    "stubs"
    "tests"
    "tsconfig.json"
)

EXPECTED_ASSETS=(
    "admin.css"
    "admin.js"
    "tinymce-content.css"
    "tinymce-separator.js"
    "tom-select.complete.min.js"
    "tom-select.default.min.css"
)

for required_file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$PACKAGE_DIR/$required_file" ]]; then
        fail "Required file '$required_file' is missing."
    fi
done

for forbidden_path in "${FORBIDDEN_PATHS[@]}"; do
    if [[ -e "$PACKAGE_DIR/$forbidden_path" ]]; then
        fail "Development-only path '$forbidden_path' is present."
    fi
done

while IFS= read -r top_level_path; do
    top_level_name="$(basename "$top_level_path")"
    case "$top_level_name" in
        teksttv.php|README.md|EXTENDING.md|assets|src|vendor)
            ;;
        *)
            fail "Unexpected top-level package entry '$top_level_name'."
            ;;
    esac
done < <(find "$PACKAGE_DIR" -mindepth 1 -maxdepth 1 -print)

while IFS= read -r asset_path; do
    asset_name="${asset_path#"$PACKAGE_DIR/assets/"}"
    expected=false
    for expected_asset in "${EXPECTED_ASSETS[@]}"; do
        if [[ "$asset_name" == "$expected_asset" ]]; then
            expected=true
            break
        fi
    done
    if [[ "$expected" != true ]]; then
        fail "Unexpected generated asset 'assets/$asset_name'."
    fi
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
    if ! diff -qr "$PACKAGE_DIR" "$VERIFY_TMP/$SLUG" >/dev/null; then
        diff -qr "$PACKAGE_DIR" "$VERIFY_TMP/$SLUG" >&2 || true
        fail "ZIP contents differ from the validated plugin directory."
    fi
fi

echo "Package validation passed: $PACKAGE_DIR"
if [[ -n "$ZIP_PATH" ]]; then
    echo "ZIP parity validation passed: $ZIP_PATH"
fi
