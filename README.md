# TekstTV (WordPress plugin)

WordPress plugin to manage text-TV slides and serve them as JSON to the [TekstTV playout app](https://github.com/oszuidwest/teksttv-frontend). In the Tekst TV admin menu you set up channels, build the broadcast loop from blocks (posts, images, iframes, campaigns, weather, ticker items), and manage settings, campaigns and optional AI-assisted content.

## How it fits with the frontend

The playout in [oszuidwest/teksttv-frontend](https://github.com/oszuidwest/teksttv-frontend) is a thin client: it polls a JSON playlist on a timer and renders the slides plus the ticker bar. This plugin is the usual content source: editing in WordPress, slide and ticker assembly in PHP, delivery via `GET /wp-json/teksttv/v1/slides`. A different CMS can fill the same role, provided it returns the same payload shape (see `src/types.ts` and the schema in the frontend README).

## Requirements

- WordPress 7.0 or newer
- PHP 8.3 or newer

For development from a Git checkout you also need [Composer](https://getcomposer.org/) and [Bun](https://bun.sh/).

## Installation

### Pre-built zip (recommended)

The manual Release workflow publishes `teksttv-<version>.zip`. It uses the
same canonical packager as the E2E suite: tracked production source, the exact
built asset set, and a fresh `composer install --no-dev` inside the staged
plugin. Upload the ZIP under Plugins → Add New → Upload Plugin and activate.

The workflow reads the release version from the plugin header, which is also the
runtime source for `TEKSTTV_VERSION`. The version must be newer than the latest
published release. GitHub creates the version tag and publishes the ZIP using
repository-native immutable releases, so published tags and assets cannot be
replaced. An existing unpublished tag is accepted only when it points to the
commit being released.

### Build from source

1. Drop the folder in `wp-content/plugins/` (any folder name, `teksttv` is fine).
2. Install PHP dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Install JS/CSS dependencies and compile:

   ```bash
   bun install
   bun run build
   ```

4. Activate TekstTV under Plugins.

Without `vendor/` and a built `assets/` the plugin won't load: `vendor/autoload.php` and the admin assets are missing.

## Capabilities after activation

| Role          | Capabilities |
|---------------|--------------|
| Administrator | `manage_teksttv`, `manage_teksttv_campaigns`, `manage_teksttv_content`, `edit_teksttv` |
| Editor        | `edit_teksttv` (TekstTV fields on posts) |

If you need a different distribution, use a capability plugin.

## Usage

- Tekst TV → Loop (per configured channel): the order and composition of the broadcast loop. Block types include posts, image, campaign and weather. The ticker is configured separately.
- Settings: channel slugs (`tv1`, `tv2`, …), display duration for text and images, OpenWeather API key, feature toggles (TinyMCE, AI, scheduling), preview URL.
- Campaigns: campaign blocks and groups used in the loop.
- Content & AI / AI Audit, when AI generation is enabled: prompts and audit log. Uses WordPress AI when available (`wp_supports_ai()`).

If no channels are stored, `tv1` is assumed.

## REST API

Public endpoint, no login required:

```http
GET /wp-json/teksttv/v1/slides?channel=<channel-slug>
```

The payload contains `slides` (the loop) and `ticker` entries. `channel` must match a configured slug (`validate_channel`). Responses are built fresh on every request; add edge caching at the hosting layer (e.g. Cloudflare) if needed. The [playout app](https://github.com/oszuidwest/teksttv-frontend) consumes this shape on a timer (see Auto-Refresh in its README).

Editor-only endpoints (image metadata, generation, …) need a user with `edit_teksttv`. See `TekstTV\RestApi::register_routes()` in [`src/RestApi.php`](src/RestApi.php), namespace `teksttv/v1`.

## Development scripts

From [`package.json`](package.json):

| Command            | Purpose |
|--------------------|---------|
| `bun run build`    | Bundle and minify JS/CSS to `assets/`, copy TinyMCE support files |
| `bun run build:package` | Build assets and create the validated `release/teksttv/` directory plus versioned ZIP |
| `bun run dev`      | Watch JS and CSS |
| `bun run check`    | PHPCS plus all frontend checks |
| `bun run check:frontend` | Biome, TypeScript and Bun unit tests |
| `bun run lint:js`  | Biome on maintained JS, TS and CSS sources/tests |
| `bun run lint:fix` | PHPCBF + Biome `--write` |
| `bun run typecheck`| TypeScript type checking without emitting files |
| `bun run analyse`  | PHPStan |
| `bun run test`     | PHPUnit (unit) |
| `bun run env:start`| Build + package the artifact and boot WordPress via [`wp-env`](https://www.npmjs.com/package/@wordpress/env) (needs Docker) |
| `bun run test:e2e:fixtures` | Seed the running site with channels, a post, a loop/ticker config and a custom-role user |
| `bun run test:e2e` | Playwright smoke suite against the running site |

### End-to-end smoke suite

The e2e suite installs the **built plugin artifact** (not the raw checkout)
into a real WordPress and checks activation, administrator and custom-role
settings saves, admin screen rendering, and the `/slides` REST shape. Locally:

```bash
bun run env:start            # Docker required
bun run test:e2e:fixtures
bun run test:e2e
bun run env:stop
```

CI runs lint, the plugin artifact build, and the e2e suite; see [`.github/workflows/`](.github/workflows/).
