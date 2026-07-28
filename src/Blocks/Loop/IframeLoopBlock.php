<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Contracts\BlockType;
use TekstTV\Helpers;

final class IframeLoopBlock implements BlockType
{
    public static function register(): void
    {
        BlockRegistry::register('iframe', [
            'label' => __('Iframe', 'teksttv-wp-plugin'),
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
        $duration = $block['duration'] ?? '';
        $default_duration = Helpers::duration_seconds('iframe');

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field teksttv-block-field-wide">
                <label><?php esc_html_e('Naam', 'teksttv-wp-plugin'); ?></label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][name]" value="<?php echo esc_attr((string) $name); ?>" class="regular-text" placeholder="<?php esc_attr_e('bijv. Weerdashboard', 'teksttv-wp-plugin'); ?>" data-summary />
                <p class="description"><?php esc_html_e('Alleen ter herkenning in dit beheerscherm. Wordt niet uitgezonden.', 'teksttv-wp-plugin'); ?></p>
            </div>
            <div class="teksttv-block-field teksttv-block-field-wide">
                <label><?php esc_html_e('URL', 'teksttv-wp-plugin'); ?></label>
                <input type="url" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr((string) $url); ?>" class="regular-text" placeholder="https://" inputmode="url" />
                <p class="description"><?php esc_html_e('De pagina moet ingesloten (embedded) mogen worden. Gebruik voor dashboards de embed-URL.', 'teksttv-wp-plugin'); ?></p>
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Duur', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration]" value="<?php echo esc_attr((string) $duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_duration); ?>" /> <span class="teksttv-unit">sec</span>
            </div>
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
            'url' => esc_url_raw(trim((string) ($raw['url'] ?? ''))),
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
        $url = trim((string) ($block['url'] ?? ''));
        if ($url === '') {
            return [];
        }

        return [[
            'type' => 'iframe',
            'url' => $url,
            'duration' => Helpers::duration_ms($block['duration'] ?? null, 'iframe'),
        ]
        ];
    }
}
