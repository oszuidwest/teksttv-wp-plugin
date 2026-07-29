#!/usr/bin/env bash
#
# Build the canonical production plugin artifact.
#
# Input is deliberately limited to tracked production source files, the exact
# generated asset set, and a fresh Composer --no-dev install. The resulting
# release/teksttv/ directory is used by wp-env and is also zipped for releases.
set -euo pipefail

SLUG="teksttv"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
RELEASE_DIR="$ROOT/release"
DEST="$RELEASE_DIR/$SLUG"

TRACKED_PATHS=(
    "$SLUG.php"
    "src"
    "README.md"
    "EXTENDING.md"
)

ASSET_FILES=(
    "admin.css"
    "admin.js"
    "tinymce-content.css"
    "tinymce-separator.js"
    "tom-select.complete.min.js"
    "tom-select.default.min.css"
)

fail() {
    echo "Packaging error: $*" >&2
    exit 1
}

for command_name in composer git php rsync zip; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required command '$command_name' is not available."
done

GIT_ROOT="$(git -C "$ROOT" rev-parse --show-toplevel 2>/dev/null)" \
    || fail "The plugin source must be inside a Git worktree."
GIT_ROOT="$(cd "$GIT_ROOT" && pwd -P)"
if [[ "$GIT_ROOT" != "$ROOT" ]]; then
    fail "Expected repository root '$ROOT', found '$GIT_ROOT'."
fi

VERSION="$(
    sed -n \
        's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' \
        "$ROOT/$SLUG.php" \
        | head -n 1
)"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[0-9]+)?$ ]]; then
    fail "Invalid or missing Version header in $SLUG.php: '$VERSION'."
fi
ZIP_PATH="$RELEASE_DIR/$SLUG-$VERSION.zip"

for tracked_path in "${TRACKED_PATHS[@]}"; do
    if ! git -C "$ROOT" ls-files --error-unmatch -- "$tracked_path" >/dev/null 2>&1; then
        fail "Required production source '$tracked_path' is not tracked by Git."
    fi
done

for asset_file in "${ASSET_FILES[@]}"; do
    if [[ ! -f "$ROOT/assets/$asset_file" ]]; then
        fail "Required built asset 'assets/$asset_file' is missing; run 'bun run build' first."
    fi
done

if [[ "$DEST" != "$ROOT/release/$SLUG" || "$ZIP_PATH" != "$ROOT/release/$SLUG-$VERSION.zip" ]]; then
    fail "Refusing to clean an unexpected output path."
fi

rm -rf -- "$DEST"
rm -f -- "$ZIP_PATH"
mkdir -p "$DEST/assets"

# Copy only tracked files from the explicit production allowlist. This reads
# the working-tree versions (useful for local development) while excluding
# every untracked or ignored file, including files nested under src/.
git -C "$ROOT" ls-files -z -- "${TRACKED_PATHS[@]}" \
    | rsync -a --from0 --files-from=- "$ROOT/" "$DEST/"

# assets/ is generated and ignored, so copy only the outputs declared by the
# current Bun build instead of trusting everything left in that directory.
for asset_file in "${ASSET_FILES[@]}"; do
    cp "$ROOT/assets/$asset_file" "$DEST/assets/$asset_file"
done

# Install the locked production dependencies directly into the clean artifact.
# The Composer manifests are build inputs only and are removed afterwards.
cp "$ROOT/composer.json" "$ROOT/composer.lock" "$DEST/"
COMPOSER_ROOT_VERSION="$VERSION" composer install \
    --working-dir="$DEST" \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader
rm -f -- "$DEST/composer.json" "$DEST/composer.lock"

"$ROOT/bin/verify-package.sh" "$DEST"

# Normalize metadata and feed zip a stable file order so repeated builds from
# the same source produce the same archive bytes, not just equivalent contents.
find "$DEST" -exec touch -t 198001010000 {} +
(
    cd "$RELEASE_DIR"
    find "$SLUG" -type f -print \
        | LC_ALL=C sort \
        | zip -q -X "$ZIP_PATH" -@
)

"$ROOT/bin/verify-package.sh" "$DEST" "$ZIP_PATH"

echo "Packaged plugin directory: $DEST"
echo "Packaged plugin ZIP: $ZIP_PATH"
