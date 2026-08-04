<?php

namespace TekstTV\Blocks\Ticker;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\BuildContext;
use TekstTV\Blocks\Common\RecentPostsQuery;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Helpers;
use WP_Query;

final class TickerHeadlinesBlock
{
    public static function register(): void
    {
        BlockRegistry::register('ticker_headlines', [
            'label' => 'Koppen',
            'icon' => 'list-view',
            'color' => '#2271b1',
            'context' => 'ticker',
            'render' => [self::class, 'render_fields'],
            'save' => [self::class, 'save'],
            'build' => [self::class, 'build'],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function render_fields(int|string $index, array $item, string $prefix): void
    {
        $count = $item['count'] ?? 5;
        $item_prefix = $item['prefix'] ?? '';

        ?>
        <div class="teksttv-field-grid">
            <div class="teksttv-field teksttv-field--compact">
                <label <?php Helpers::field_for($prefix, $index, 'count'); ?>><?php echo esc_html('Aantal'); ?></label>
                <input type="number" <?php Helpers::field_attrs($prefix, $index, 'count'); ?> value="<?php echo esc_attr((string) $count); ?>" min="1" max="20" class="small-text" data-summary="%sx" />
            </div>
            <div class="teksttv-field teksttv-field--text">
                <label <?php Helpers::field_for($prefix, $index, 'prefix'); ?>><?php echo esc_html('Voorvoegsel'); ?></label>
                <input type="text" <?php Helpers::field_attrs($prefix, $index, 'prefix'); ?> value="<?php echo esc_attr((string) $item_prefix); ?>" class="regular-text" placeholder="<?php echo esc_attr('bijv. Nieuws:'); ?>" autocomplete="off" />
            </div>
            <?php TaxonomyFilters::render_selects($index, (array) ($item['taxonomy_filters'] ?? []), $prefix); ?>
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
            'count' => Helpers::clamp_int($raw['count'] ?? 5, 1, 20),
        ];

        $item_prefix = sanitize_text_field($raw['prefix'] ?? '');
        if ($item_prefix !== '') {
            $saved['prefix'] = $item_prefix;
        }

        $tax_filters = TaxonomyFilters::sanitize_from_post($raw);
        if (!empty($tax_filters)) {
            $saved['taxonomy_filters'] = $tax_filters;
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{message: string}>
     */
    public static function build(array $item, string $channel): array
    {
        $count = $item['count'] ?? 5;
        $item_prefix = $item['prefix'] ?? '';
        $taxonomy_filters = $item['taxonomy_filters'] ?? [];

        // Full post objects (not fields => ids) so get_the_title() reads from
        // the primed cache instead of issuing one get_post() query per ID.
        $query = new WP_Query(RecentPostsQuery::args($count, $taxonomy_filters, [
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]));
        $messages = [];

        foreach ($query->posts as $post) {
            $title = get_the_title($post);
            if (!empty($title)) {
                $message = !empty($item_prefix) ? $item_prefix . ' ' . $title : $title;
                $messages[] = ['message' => $message];
                BuildContext::mark_post_seen((int) $post->ID);
            }
        }

        return $messages;
    }
}
