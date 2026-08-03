<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Common\DurationField;
use TekstTV\Helpers;

final class IframeLoopBlock
{
    public static function register(): void
    {
        BlockRegistry::register('iframe', [
            'label' => 'Iframe',
            'icon' => 'embed-generic',
            'color' => '#8c8f94',
            'context' => 'loop',
            'render' => [self::class, 'render_fields'],
            'save' => [self::class, 'save'],
            'build' => [self::class, 'build'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_fields(int|string $index, array $block, string $prefix): void
    {
        $name = $block['name'] ?? '';
        $url = $block['url'] ?? '';
        $default_duration = (int) get_option('teksttv_duration_iframe', Helpers::DURATION_DEFAULTS['teksttv_duration_iframe']);
        $name_id = Helpers::field_id($prefix, $index, 'name');
        $url_id = Helpers::field_id($prefix, $index, 'url');

        ?>
        <div class="teksttv-field-grid">
            <div class="teksttv-field teksttv-field--full">
                <label for="<?php echo esc_attr($name_id); ?>" data-teksttv-label="name"><?php echo esc_html('Naam'); ?></label>
                <input type="text" id="<?php echo esc_attr($name_id); ?>" data-teksttv-field="name" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr((string) $name); ?>" class="regular-text" placeholder="<?php echo esc_attr('bijv. Weerdashboard'); ?>" autocomplete="off" data-summary />
                <p class="description"><?php echo esc_html('Alleen ter herkenning in dit beheerscherm. Wordt niet uitgezonden.'); ?></p>
            </div>
            <div class="teksttv-field teksttv-field--full">
                <label for="<?php echo esc_attr($url_id); ?>" data-teksttv-label="url"><?php echo esc_html('URL'); ?></label>
                <input type="url" id="<?php echo esc_attr($url_id); ?>" data-teksttv-field="url" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr((string) $url); ?>" class="regular-text" placeholder="https://" inputmode="url" autocomplete="off" spellcheck="false" />
                <p class="description"><?php echo esc_html('De pagina moet ingesloten (embedded) mogen worden. Gebruik voor dashboards de embed-URL.'); ?></p>
            </div>
            <?php DurationField::render($prefix, $index, 'duration', 'Duur', (string) ($block['duration'] ?? ''), $default_duration); ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function save(array $raw): array
    {
        $saved = [
            'name' => sanitize_text_field((string) ($raw['name'] ?? '')),
            'url' => self::sanitizeUrl($raw['url'] ?? ''),
        ];

        $dur = $raw['duration'] ?? '';
        if ($dur !== '') {
            $saved['duration'] = Helpers::clamp_int($dur, 1, 120);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        $url = self::sanitizeUrl($block['url'] ?? '');
        if ($url === '') {
            return [];
        }

        return [[
            'type' => 'iframe',
            'url' => $url,
            'duration' => Helpers::duration_ms($block['duration'] ?? null, 'teksttv_duration_iframe'),
        ]
        ];
    }

    private static function sanitizeUrl(mixed $value): string
    {
        $url = esc_url_raw(trim((string) $value), ['http', 'https']);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return '';
        }

        return $url;
    }
}
