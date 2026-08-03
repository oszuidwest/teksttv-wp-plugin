<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Common\DurationField;
use TekstTV\Helpers;

final class ImageLoopBlock
{
    public static function register(): void
    {
        BlockRegistry::register('image', [
            'label' => 'Afbeelding',
            'icon' => 'format-image',
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
        $image_id = $block['image_id'] ?? 0;
        $default_image = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image']);
        $image_url = $image_id ? wp_get_attachment_image_url((int) $image_id, 'medium') : '';

        ?>
        <div class="teksttv-block-image-row teksttv-image-picker">
            <div class="teksttv-block-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="" class="teksttv-block-image-thumb" width="120" height="80" loading="lazy" />
            </div>
            <div class="teksttv-block-image-fields">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][image_id]" value="<?php echo esc_attr($image_id ? (string) $image_id : ''); ?>" class="teksttv-block-image-id" data-summary data-summary-label="<?php echo esc_attr('Afbeelding'); ?>" data-summary-empty="<?php echo esc_attr('Geen afbeelding'); ?>" />
                <p>
                    <button type="button" class="button teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Afbeelding kiezen'); ?></button>
                    <button type="button" class="button-link teksttv-block-image-remove <?php echo $image_url ? '' : 'is-hidden'; ?>"><?php echo esc_html('Verwijderen'); ?></button>
                </p>
                <div class="teksttv-field-grid">
                    <?php DurationField::render($prefix, $index, 'duration', 'Duur', (string) ($block['duration'] ?? ''), $default_image); ?>
                </div>
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
            'image_id' => absint($raw['image_id'] ?? 0),
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
        $image_id = (int) ($block['image_id'] ?? 0);
        if (!$image_id) {
            return [];
        }

        $image_data = Helpers::get_image_data($image_id, 'large', 'image_slide');
        if (!$image_data) {
            return [];
        }

        return [array_merge([
            'type' => 'image',
            'duration' => Helpers::duration_ms($block['duration'] ?? null, 'teksttv_duration_image'),
        ], $image_data)
        ];
    }
}
