<?php

namespace TekstTV\Blocks\Ticker;

use TekstTV\AdminPage;
use TekstTV\BlockRegistry;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Blocks\Contracts\TickerBlock;
use TekstTV\Helpers;
use WP_Query;

final class TickerHeadlinesBlock implements TickerBlock
{
    public static function register(): void
    {
        BlockRegistry::register('ticker_headlines', [
            'label' => __('Koppen', 'teksttv'),
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
        $filters = $item['taxonomy_filters'] ?? [];

        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = AdminPage::get_post_taxonomies_static();
        $taxonomies = array_filter($all_taxonomies, fn ($t) => in_array($t['name'], $enabled_tax, true));

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Aantal', 'teksttv'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][count]" value="<?php echo esc_attr((string) $count); ?>" min="1" max="20" class="small-text" />
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Prefix', 'teksttv'); ?></label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][prefix]" value="<?php echo esc_attr((string) $item_prefix); ?>" class="regular-text" placeholder="<?php echo esc_attr__('bijv. Nieuws:', 'teksttv'); ?>" />
            </div>
            <?php foreach ($taxonomies as $tax) :
                $selected_terms = array_map('intval', (array) ($filters[$tax['name']] ?? []));
                ?>
            <div class="teksttv-block-field">
                <label><?php echo esc_html($tax['label']); ?></label>
                <select name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][taxonomy_filters][<?php echo esc_attr($tax['name']); ?>][]" class="teksttv-tomselect" data-placeholder="Filter..." multiple>
                    <?php foreach ($tax['terms'] as $term_id => $term_name) : ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php echo in_array($term_id, $selected_terms, true) ? 'selected' : ''; ?>><?php echo esc_html($term_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
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
            'count' => max(1, min(20, absint($raw['count'] ?? 5))),
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

        $args = [
            'post_type' => 'post',
            'posts_per_page' => $count,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'fields' => 'ids',
        ];

        $max_age = (int) get_option('teksttv_max_post_age', 30);
        if ($max_age > 0) {
            $args['date_query'] = [
                ['after' => $max_age . ' days ago'],
            ];
        }

        $tax_query = Helpers::build_tax_query($taxonomy_filters);
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $messages = [];

        foreach ($query->posts as $post_id) {
            $title = get_the_title($post_id);
            if (!empty($title)) {
                $message = !empty($item_prefix) ? $item_prefix . ' ' . $title : $title;
                $messages[] = ['message' => $message];
            }
        }

        return $messages;
    }
}
