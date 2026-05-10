<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Contracts\LoopBlock;
use TekstTV\Helpers;

final class ImageLoopBlock implements LoopBlock
{
    public static function register(): void
    {
        BlockRegistry::register('image', [
            'label' => __('Afbeelding', 'teksttv'),
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
        $duration = $block['duration'] ?? '';
        $default_image = (int) get_option('teksttv_duration_image', 7);
        $image_url = $image_id ? wp_get_attachment_image_url((int) $image_id, 'medium') : '';

        ?>
        <div class="teksttv-block-image-row">
            <div class="teksttv-block-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="" class="teksttv-block-image-thumb" />
            </div>
            <div class="teksttv-block-image-fields">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][image_id]" value="<?php echo esc_attr((string) $image_id); ?>" class="teksttv-block-image-id" />
                <p>
                    <button type="button" class="button teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> <?php esc_html_e('Afbeelding kiezen', 'teksttv'); ?></button>
                    <button type="button" class="button-link teksttv-block-image-remove <?php echo $image_url ? '' : 'is-hidden'; ?>"><?php esc_html_e('Verwijderen', 'teksttv'); ?></button>
                </p>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Duur', 'teksttv'); ?></label>
                        <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration]" value="<?php echo esc_attr((string) $duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_image); ?>" /> <span class="teksttv-unit">sec</span>
                    </div>
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
            $saved['duration'] = absint($dur);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

        $image_id = (int) ($block['image_id'] ?? 0);
        if (!$image_id) {
            return [];
        }

        $image_data = Helpers::get_image_data($image_id);
        if (!$image_data) {
            return [];
        }

        $duration = !empty($block['duration']) ? (int) $block['duration'] * 1000 : (int) get_option('teksttv_duration_image', 7) * 1000;

        return [array_merge([
            'type' => 'image',
            'duration' => $duration,
        ], $image_data)
        ];
    }
}
