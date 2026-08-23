# TekstTV (WordPress plugin)

WordPress plugin to manage text-TV slides and serve them as JSON to the [TekstTV playout app](https://github.com/oszuidwest/teksttv-frontend). In the Tekst TV admin menu you set up channels, build the broadcast loop from blocks (posts, images, iframes, commercials, weather, ticker items), and manage settings, commercials and optional AI-assisted content.

## How it fits with the frontend

The playout in [oszuidwest/teksttv-frontend](https://github.com/oszuidwest/teksttv-frontend) is a thin client: it polls a JSON playlist on a timer and renders the slides plus the ticker bar. This plugin is the usual content source: editing in WordPress, slide and ticker assembly in PHP, delivery via `GET /wp-json/teksttv/v1/slides`. A different CMS can fill the same role, provided it returns the same payload shape (see `src/types.ts` and the schema in the frontend README).

## Requirements

- WordPress 7.0 or newer
- PHP 8.3 or newer

For development from a Git checkout you also need [Composer](https://getcomposer.org/) and [Bun](https://bun.sh/).
The Playground E2E suite additionally uses Node.js 24 LTS.

## Installation

### Pre-built zip (recommended)

Pushing a version tag starts the Release workflow and publishes
`teksttv-<version>.zip`. It uses the
same canonical packager as the E2E suite: tracked production source, the exact
built asset set, and a fresh `composer install --no-dev` inside the staged
plugin. Upload the ZIP under Plugins → Add New → Upload Plugin and activate.

The tag must exactly match the version in the plugin header, which is also the
runtime source for `TEKSTTV_VERSION`. For example, release version `0.5.0` with
`git tag 0.5.0 && git push origin 0.5.0`. The workflow validates and packages
that tagged commit, then publishes the ZIP using repository-native immutable
releases, so published tags and assets cannot be replaced.

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
| Administrator | `manage_teksttv`, `manage_teksttv_commercials`, `manage_teksttv_content`, `edit_teksttv` |
| Editor        | `edit_teksttv` (TekstTV fields on posts) |

If you need a different distribution, use a capability plugin.

## Usage

- Tekst TV → Loop (per configured channel): the order and composition of the broadcast loop. Block types include posts, image, commercial and weather. The ticker is configured separately.
- Settings: channel slugs (`tv1`, `tv2`, …), display duration for text and images, OpenWeather API key, feature toggles (TinyMCE, AI, scheduling), preview URL.
- Commercials (`Reclame`): commercial blocks and their campaigns used in the loop.
- Content & AI / AI Audit, when AI generation is enabled and a configured provider supports TekstTV's text-generation requirements: prompts and audit log.

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
| `bun run env:start`| Build + package the artifact and boot an interactive [WordPress Playground](https://wordpress.github.io/wordpress-playground/) on port 8888 |
| `bun run test:e2e` | Start a disposable Blueprint-configured Playground and run the Playwright smoke suite |

### End-to-end smoke suite

The e2e suite mounts the **built plugin artifact** (not the raw checkout) into
a disposable WordPress Playground and checks activation, administrator and
custom-role settings saves, admin screen rendering, and the `/slides` REST
shape. [`blueprint.json`](blueprint.json) pins WordPress and PHP, activates and
validates the artifact, and loads the deterministic fixtures. Locally:

```bash
bun run build:package
bun run test:e2e
```

For interactive inspection, run `bun run env:start` and sign in with
`admin` / `password`. Stop the ephemeral server with Ctrl-C. Playground uses
SQLite, while the plugin itself relies on WordPress database APIs rather than
database-specific queries.

CI runs lint, the plugin artifact build, and the e2e suite; see [`.github/workflows/`](.github/workflows/).
