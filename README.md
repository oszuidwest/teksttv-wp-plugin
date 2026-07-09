# TekstTV (WordPress plugin)

WordPress plugin to manage text-TV slides and serve them as JSON to the [TekstTV playout app](https://github.com/oszuidwest/teksttv-frontend). In the Tekst TV admin menu you set up channels, build the broadcast loop from blocks (posts, images, iframes, campaigns, weather, ticker items), and manage settings, campaigns and optional AI-assisted content.

## How it fits with the frontend

The playout in [oszuidwest/teksttv-frontend](https://github.com/oszuidwest/teksttv-frontend) is a thin client: it polls a JSON playlist on a timer and renders the slides plus the ticker bar. This plugin is the usual content source: editing in WordPress, slide and ticker assembly in PHP, delivery via `GET /wp-json/teksttv/v1/slides`. A different CMS can fill the same role, provided it returns the same payload shape (see `src/types.ts` and the schema in the frontend README).

## Requirements

- WordPress 7.0 or newer
- PHP 8.1 or newer

For development from a Git checkout you also need [Composer](https://getcomposer.org/) and [Bun](https://bun.sh/).

## Installation

### Pre-built zip (recommended)

GitHub Actions builds `teksttv.zip` on `main` and on version tags: `composer install --no-dev`, asset build, zip with `src/`, `assets/`, `vendor/` and the bootstrap files. Upload it under Plugins → Add New → Upload Plugin and activate.

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

The payload contains `slides` (the loop) and `ticker` entries. `channel` must match a configured slug (`validate_channel`). Responses carry short `Cache-Control` headers. The [playout app](https://github.com/oszuidwest/teksttv-frontend) consumes this shape on a timer (see Auto-Refresh in its README).

Editor-only endpoints (image metadata, generation, …) need a user with `edit_teksttv`. See `TekstTV\RestApi::register_routes()` in [`src/RestApi.php`](src/RestApi.php), namespace `teksttv/v1`.

## Development scripts

From [`package.json`](package.json):

| Command            | Purpose |
|--------------------|---------|
| `bun run build`    | Minify JS/CSS to `assets/`, copy TinyMCE and tom-select vendor files |
| `bun run dev`      | Watch JS and CSS |
| `bun run lint`     | PHPCS + Biome on `resources/` |
| `bun run lint:fix` | PHPCBF + Biome `--write` |
| `bun run analyse`  | PHPStan |
| `bun run test`     | PHPUnit |

CI runs lint and the plugin artifact build; see [`.github/workflows/`](.github/workflows/).
