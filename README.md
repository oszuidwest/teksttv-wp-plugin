# TekstTV WordPress plugin

Manages TekstTV channels, slide loops and ticker messages in WordPress. The public REST endpoint is consumed by the [TekstTV playout app](https://github.com/oszuidwest/teksttv-frontend).

## Requirements

- WordPress 7.0 or newer
- PHP 8.3 or newer
- Composer and Bun when building from source

## Installation

Download a versioned ZIP from [GitHub Releases](https://github.com/oszuidwest/teksttv-wp-plugin/releases), upload it under Plugins → Add New → Upload Plugin, and activate it.

To build from source:

```bash
composer install --no-dev --optimize-autoloader
bun install --frozen-lockfile
bun run build
```

Place the resulting checkout in `wp-content/plugins/` and activate TekstTV. `vendor/` is required for the plugin to load; `assets/` is required for the admin interface.

## Configuration

Configure TekstTV under Tekst TV → Settings:

- Channels and their slugs; `tv1` is used when no channels are stored.
- Default durations for text, image and iframe slides.
- Preview URL and content feature toggles.
- Enabled taxonomies for article and headline filters.
- Optional OpenWeather API key for weather slides.

The loop screen is configured per channel. Available loop blocks are articles, images, iframes, campaigns and weather. The ticker supports manual text and recent headlines. Loop and ticker items can be restricted by date and weekday.

AI generation is optional. When enabled, it uses the WordPress AI API if `wp_supports_ai()` is available. Prompts are managed under Content & AI; generated content is recorded under AI Audit.

## REST API

The playout endpoint is public:

```http
GET /wp-json/teksttv/v1/slides?channel=<channel-slug>
```

`channel` must match a configured channel slug. A representative response is:

```json
{
    "slides": [
        {
            "type": "text",
            "duration": 20000,
            "title": "Example",
            "body": "Slide content"
        }
    ],
    "ticker": [
        {
            "message": "Ticker message"
        }
    ]
}
```

Responses are cached for 180 seconds. The response schema consumed by the playout is documented in the [frontend repository](https://github.com/oszuidwest/teksttv-frontend).

The `GET /image-data` and `POST /generate` endpoints require `edit_teksttv`. Generation also checks whether the user may edit the requested post.

## Extending blocks and ticker types

`TekstTV\BlockRegistry` is the extension point for loop and ticker types. Register add-on types on `init`; built-in types register at priority 5. A type defines admin rendering, sanitization and REST output through `render`, `save` and `build` callbacks.

This minimal add-on registers a ticker type without additional fields:

```php
<?php
/**
 * Plugin Name: TekstTV Example Ticker
 * Requires Plugins: teksttv
 */

add_action('init', static function (): void {
    if (!class_exists(\TekstTV\BlockRegistry::class)) {
        return;
    }

    \TekstTV\BlockRegistry::register('myplugin_ticker_example', [
        'label' => 'Example',
        'icon' => 'megaphone',
        'color' => '#2271b1',
        'context' => 'ticker',
        'render' => static function (): void {
        },
        'save' => static fn(array $raw): array => [],
        'build' => static fn(array $data, string $channel): array => [
            ['message' => 'Example ticker message'],
        ],
    ]);
}, 10);
```

The `Requires Plugins` header assumes the main plugin directory is `teksttv`; adjust it for source installations that use another directory name. Use a unique type slug. Ticker builders must return a list of `['message' => '...']` entries. Registered types automatically appear in the admin selector and use the existing scheduling, persistence and REST pipeline. The callback contract is documented in [`src/BlockRegistry.php`](src/BlockRegistry.php) and [`src/Blocks/Contracts/BlockType.php`](src/Blocks/Contracts/BlockType.php).

The registry is a direct PHP extension API; there is currently no dedicated registration action or final ticker-output filter. If an add-on type is unavailable, its stored row cannot be rendered and may be removed the next time that channel is saved.

## Capabilities

| Role | Capabilities after activation |
|---|---|
| Administrator | `manage_teksttv`, `manage_teksttv_campaigns`, `manage_teksttv_content`, `edit_teksttv` |
| Editor | `edit_teksttv` |

Use a capability-management plugin when a different role distribution is required.

## Development

Install dependencies with `composer install` and `bun install`.

| Command | Purpose |
|---|---|
| `bun run check` | Run PHPCS and Biome |
| `bunx tsc --noEmit` | Check TypeScript types |
| `bun run analyse` | Run PHPStan |
| `bun run test` | Run PHPUnit unit tests |
| `bun run build` | Build minified admin assets into `assets/` |
| `bun run dev` | Watch JavaScript and CSS entry points |
| `bun run build:package` | Build the installable artifact in `release/teksttv/` |

The end-to-end suite requires Docker and tests the packaged plugin in `wp-env`:

```bash
bun run env:start
bun run test:e2e:fixtures
bun run test:e2e
bun run env:stop
```

CI runs PHP linting and unit tests, Biome and TypeScript checks, the asset build, and the WordPress end-to-end suite. See [`.github/workflows/`](.github/workflows/).
