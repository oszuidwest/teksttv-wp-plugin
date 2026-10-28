<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\AdminPage;
use TekstTV\BlockRegistry;
use TekstTV\Blocks\Common\DurationField;
use TekstTV\Blocks\Common\PostSlides;
use TekstTV\Helpers;
use TekstTV\Messages;

final class MessagesLoopBlock
{
    public static function register(): void
    {
        if (!Messages::enabled()) {
            return;
        }

        BlockRegistry::register('messages', [
            'label' => 'Tekst TV-berichten',
            'icon' => 'format-aside',
            'color' => '#00a32a',
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
        $count = $block['count'] ?? 5;

        ?>
        <?php AdminPage::render_block_section_start('Inhoud', 'Losse berichten voor dit kanaal, nieuwste eerst.', 'content'); ?>
        <div class="teksttv-field-grid teksttv-field-grid--selection">
            <div class="teksttv-field teksttv-field--compact">
                <label <?php Helpers::field_for($prefix, $index, 'count'); ?>><?php echo esc_html('Maximaal'); ?></label>
                <input type="number" <?php Helpers::field_attrs($prefix, $index, 'count'); ?> value="<?php echo esc_attr((string) $count); ?>" min="1" max="50" class="small-text" data-summary="%sx" />
            </div>
        </div>
        <?php AdminPage::render_block_section_end(); ?>
        <?php
        DurationField::render_text_image_section($prefix, $index, $block);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function save(array $raw): array
    {
        return ['count' => Helpers::clamp_int($raw['count'] ?? 5, 1, 50)] + DurationField::sanitize_text_image($raw);
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        return PostSlides::collect(
            $block,
            Helpers::clamp_int($block['count'] ?? 5, 1, 50),
            ['post_type' => Messages::POST_TYPE],
            // Channel choice is stored as a list; filtering here avoids a LIKE on serialized meta.
            static fn(\WP_Post $post): bool => Messages::airs_on($post->ID, $channel)
        );
    }
}
