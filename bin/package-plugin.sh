#!/usr/bin/env bash
#
# Package the plugin exactly as the release ZIP does, into release/teksttv/.
# Used by the e2e smoke suite so tests run against the built artifact (vendor +
# compiled assets present, dev files excluded) rather than the raw checkout.
#
# Assumes production composer deps and built assets already exist. Run:
#   composer install --no-dev --optimize-autoloader
#   bun install --frozen-lockfile && bun run build
# before calling this script.
set -euo pipefail

SLUG="teksttv"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="$ROOT/release/$SLUG"

rm -rf "$ROOT/release"
mkdir -p "$DEST"

rsync -a "$ROOT/" "$DEST/" \
    --exclude='/release/' \
    --exclude='/.git/' \
    --exclude='/.github/' \
    --exclude='/.claude/' \
    --exclude='/node_modules/' \
    --exclude='/resources/' \
    --exclude='/tests/' \
    --exclude='/bin/' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='bun.lock' \
    --exclude='biome.json' \
    --exclude='tsconfig.json' \
    --exclude='phpunit.xml' \
    --exclude='patchwork.json' \
    --exclude='phpcs.xml' \
    --exclude='phpcs.xml.dist' \
    --exclude='phpstan.neon' \
    --exclude='phpstan-bootstrap.php' \
    --exclude='phpstan-bootstrap.stub' \
    --exclude='stubs/' \
    --exclude='.wp-env.json' \
    --exclude='playwright.config.ts' \
    --exclude='*.log' \
    --exclude='*.zip' \
    --exclude='.gitignore' \
    --exclude='CLAUDE.md' \
    --exclude='AGENTS.md' \
    --exclude='.DS_Store'

echo "Packaged plugin into $DEST"
