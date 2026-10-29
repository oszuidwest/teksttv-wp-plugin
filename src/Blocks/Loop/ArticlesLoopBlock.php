<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\AdminPage;
use TekstTV\BlockRegistry;
use TekstTV\Blocks\Common\DurationField;
use TekstTV\Blocks\Common\PostSlides;
use TekstTV\Blocks\Common\RecentPostsQuery;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Helpers;

final class ArticlesLoopBlock
{
    public static function register(): void
    {
        BlockRegistry::register('articles', [
            'label' => 'Artikelen',
            'icon' => 'admin-post',
            'color' => '#2271b1',
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
        $count = $block['count'] ?? 3;

        ?>
        <?php AdminPage::render_block_section_start('Inhoud', 'Welke artikelen komen in de loop?', 'content'); ?>
        <div class="teksttv-field-grid teksttv-field-grid--selection">
            <div class="teksttv-field teksttv-field--compact">
                <label <?php Helpers::field_for($prefix, $index, 'count'); ?>><?php echo esc_html('Aantal'); ?></label>
                <input type="number" <?php Helpers::field_attrs($prefix, $index, 'count'); ?> value="<?php echo esc_attr((string) $count); ?>" min="1" max="50" class="small-text" data-summary="%sx" />
            </div>
            <?php TaxonomyFilters::render_selects($index, (array) ($block['taxonomy_filters'] ?? []), $prefix); ?>
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
        return [
            'count' => Helpers::clamp_int($raw['count'] ?? 3, 1, 50),
            'taxonomy_filters' => TaxonomyFilters::sanitize_from_post($raw),
        ] + DurationField::sanitize_text_image($raw);
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        // Clamp legacy values that predate save-time validation.
        $count = Helpers::clamp_int($block['count'] ?? 3, 1, 50);
        $max_age = RecentPostsQuery::max_age_days();

        // PostSlides::collect() overrides the page size, status and on-air filters set here.
        return PostSlides::collect($block, $count, RecentPostsQuery::args($count, $block['taxonomy_filters'] ?? [], [
            // A day wider than prepare()'s expires_at(), so a DST shift cannot hide an eligible post.
            'date_query' => $max_age > 0 ? [['after' => ($max_age + 1) . ' days ago']] : [],
        ]));
    }
}
