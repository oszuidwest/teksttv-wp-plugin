# TekstTV (WordPress plugin)

WordPress plugin to manage **text-TV slides** and expose them through a **public REST API** for the **[TekstTV playout app](https://github.com/oszuidwest/teksttv)**. In the **Tekst TV** admin menu you configure **channels**, build each **loop** from blocks (posts, images, campaigns, weather, ticker items), and manage settings, campaigns, and optional AI-assisted content.

## How this fits with the frontend

The playout in [oszuidwest/teksttv](https://github.com/oszuidwest/teksttv) is intentionally a **thin client**: it periodically fetches a **JSON playlist** and renders slides plus the ticker bar. **This plugin** is the typical **content source**: editorial workflow in WordPress, slide/ticker assembly in PHP, delivery via `GET /wp-json/teksttv/v1/slides`. Another CMS can play the same role as long as the API and slide shape match what the frontend expects (see e.g. `src/types.ts` and the frontend README schema).

## Requirements

- **WordPress** 6.5 or newer  
- **PHP** 8.1 or newer  

For **development** from Git you also need [Composer](https://getcomposer.org/) and [Bun](https://bun.sh/) (admin assets and scripts).

## Installation

### Ready-made package (recommended)

On `main` / version tags, GitHub Actions builds **`teksttv.zip`** (`composer install --no-dev`, asset build, zip containing `src/`, `assets/`, `vendor/`, bootstrap files). Upload it under **Plugins → Add New → Upload Plugin**, then activate.

### Build from source

1. Place the folder under `wp-content/plugins/` (any folder name, e.g. `teksttv`).
2. Install PHP dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Install JS/CSS deps and compile assets:

   ```bash
   bun install
   bun run build
   ```

4. Activate **TekstTV** in **Plugins**.

Without `vendor/` and built `assets/` the plugin will not load correctly (`vendor/autoload.php` and admin assets will be missing).

## Capabilities after activation

Activation registers among others:

| Role            | Capabilities (summary) |
|----------------|-------------------------|
| **Administrator** | Full control (`manage_teksttv`), campaigns (`manage_teksttv_campaigns`), content/AI settings (`manage_teksttv_content`), post metabox (`edit_teksttv`) |
| **Editor**       | Only `edit_teksttv` (TekstTV fields on posts) |

Adjust roles via a capability plugin if you need a different setup.

## Usage (overview)

- **Tekst TV → Loop** (per configured channel): order and composition of the broadcast loop; block types include posts, image, campaign, weather; separate ticker configuration.  
- **Settings**: channel slugs (`tv1`, `tv2`, …), text/image display duration, OpenWeather API key, feature toggles (TinyMCE / AI / scheduling / etc.), preview URL, etc.  
- **Campaigns**: campaign blocks and groups used in the loop.  
- **Content & AI / AI Audit** (when *AI generation* is enabled): prompts and audit trail; integrates with WordPress AI when available (`wp_supports_ai()`).

If no channels are stored yet, at least **`tv1`** is assumed by default.

## REST API

Public endpoint (no login required for the playout frontend):

```http
GET /wp-json/teksttv/v1/slides?channel=<channel-slug>
```

The payload includes `slides` (main loop) and `ticker` entries. `channel` must match a configured channel slug (`validate_channel`). Responses are briefly **cached** with `Cache-Control` headers. The [TekstTV playout app](https://github.com/oszuidwest/teksttv) consumes this shape of data and refreshes on a timer (see *Auto-Refresh* in the frontend README).

Editor-only endpoints (e.g. image metadata, generation):

- Requires a user with **`edit_teksttv`**  
- See `TekstTV\RestApi::register_routes()` in [`src/RestApi.php`](src/RestApi.php) under the **`teksttv/v1`** namespace.

## Development scripts

From [`package.json`](package.json):

| Command           | Purpose |
|-------------------|---------|
| `bun run build`    | Minify JS/CSS to `assets/`, copy TinyMCE / tom-select vendor files |
| `bun run dev`      | Watch mode for JS and CSS |
| `bun run lint`     | PHPCS (`vendor/bin/phpcs`) + Biome (`resources/`) |
| `bun run lint:fix` | PHPCBF + Biome `--write` |
| `bun run analyse` | PHPStan |
| `bun run test`     | PHPUnit |

CI runs lint and the plugin artifact build; see [`.github/workflows/`](.github/workflows/).
