<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\AdminPage;
use TekstTV\BlockRegistry;
use TekstTV\Blocks\BuildContext;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Blocks\Contracts\LoopBlock;
use TekstTV\Helpers;
use WP_Query;

final class ArticlesLoopBlock implements LoopBlock
{
    public static function register(): void
    {
        BlockRegistry::register('articles', [
            'label' => __('Artikelen', 'teksttv-wp-plugin'),
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
        $filters = $block['taxonomy_filters'] ?? [];
        $dur_text = $block['duration_text'] ?? '';
        $dur_image = $block['duration_image'] ?? '';
        $default_text = (int) get_option('teksttv_duration_text', 20);
        $default_image = (int) get_option('teksttv_duration_image', 7);

        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = AdminPage::get_post_taxonomies_static();
        $taxonomies = array_filter($all_taxonomies, fn ($t) => in_array($t['name'], $enabled_tax, true));

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Aantal', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][count]" value="<?php echo esc_attr((string) $count); ?>" min="1" max="50" class="small-text" />
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
        <div class="teksttv-block-fields teksttv-block-fields--duration">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Duur tekst', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration_text]" value="<?php echo esc_attr((string) $dur_text); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_text); ?>" /> <span class="teksttv-unit">sec</span>
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Duur afbeelding', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration_image]" value="<?php echo esc_attr((string) $dur_image); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_image); ?>" /> <span class="teksttv-unit">sec</span>
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
            'count' => absint($raw['count'] ?? 3),
            'taxonomy_filters' => TaxonomyFilters::sanitize_from_post($raw),
        ];

        $dt = $raw['duration_text'] ?? '';
        $di = $raw['duration_image'] ?? '';
        if ($dt !== '') {
            $saved['duration_text'] = absint($dt);
        }
        if ($di !== '') {
            $saved['duration_image'] = absint($di);
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

        $slides = [];
        $count = $block['count'] ?? 3;
        $taxonomy_filters = $block['taxonomy_filters'] ?? [];

        $args = [
            'post_type' => 'post',
            'posts_per_page' => $count,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_teksttv_active', 'value' => '1', 'compare' => '='],
                Helpers::get_date_end_meta_query(),
            ],
        ];

        $exclude = BuildContext::get_seen_post_ids();
        if (!empty($exclude)) {
            $args['post__not_in'] = $exclude;
        }

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

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $days = get_post_meta($post_id, '_teksttv_days', true);
            if (!empty($days) && is_array($days)) {
                if (!Helpers::is_allowed_on_day($days)) {
                    continue;
                }
            }

            $date_start = get_post_meta($post_id, '_teksttv_date_start', true);
            $date_end = get_post_meta($post_id, '_teksttv_date_end', true);
            if (!Helpers::is_within_date_range($date_start, $date_end)) {
                continue;
            }

            BuildContext::mark_post_seen((int) $post_id);

            $custom_title = get_post_meta($post_id, '_teksttv_title', true);
            $title = !empty($custom_title) ? $custom_title : get_the_title();
            $content = get_post_meta($post_id, '_teksttv_content', true);
            $sidebar_image = self::get_sidebar_image_data($post_id);

            if (!empty($content)) {
                $pages = self::split_pages($content);
                foreach ($pages as $page_content) {
                    $slide = [
                        'type' => 'text',
                        'duration' => self::get_duration_text($block),
                        'title' => $title,
                        'body' => wpautop($page_content),
                    ];

                    if (!empty($sidebar_image)) {
                        $slide['image'] = $sidebar_image;
                    }

                    $slides[] = $slide;
                }
            }

            $images = get_post_meta($post_id, '_teksttv_images', true);
            if (!empty($images) && is_array($images)) {
                foreach ($images as $attachment_id) {
                    $image_data = Helpers::get_image_data((int) $attachment_id, 'large', 'image_slide');
                    if ($image_data) {
                        $slides[] = array_merge([
                            'type' => 'image',
                            'duration' => self::get_duration_image($block),
                        ], $image_data);
                    }
                }
            }
        }

        wp_reset_postdata();

        return $slides;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function get_duration_text(array $block): int
    {
        if (!empty($block['duration_text'])) {
            return (int) $block['duration_text'] * 1000;
        }
        return (int) get_option('teksttv_duration_text', 20) * 1000;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function get_duration_image(array $block): int
    {
        if (!empty($block['duration_image'])) {
            return (int) $block['duration_image'] * 1000;
        }
        return (int) get_option('teksttv_duration_image', 7) * 1000;
    }

    /**
     * Split content into pages using the page separator pattern.
     * Returns non-empty trimmed page strings.
     *
     * @return list<string>
     */
    public static function split_pages(string $content): array
    {
        if (!Helpers::has_feature('page_separator')) {
            $trimmed = trim($content);
            return $trimmed !== '' ? [$trimmed] : [];
        }

        $parts = preg_split('/<p[^>]*>\s*-{3,}\s*<\/p>|\n*-{3,}\n*/i', $content);
        $pages = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $pages[] = $part;
            }
        }
        return $pages;
    }

    /**
     * Get sidebar image data for a post.
     *
     * @return array<string, string>|null
     */
    public static function get_sidebar_image_data(int $post_id): ?array
    {
        $override_id = get_post_meta($post_id, '_teksttv_sidebar_image', true);
        if ($override_id === '0' || $override_id === 0) {
            return null;
        }
        if ($override_id) {
            $data = Helpers::get_image_data((int) $override_id, 'large', 'text_sidebar');
            if ($data) {
                return $data;
            }
        }

        /** @var int|string|false $primary_term_id */
        $primary_term_id = apply_filters('teksttv_primary_category', get_post_meta($post_id, '_yoast_wpseo_primary_category', true), $post_id);
        if ($primary_term_id) {
            $data = self::get_category_image_data((int) $primary_term_id);
            if ($data) {
                return $data;
            }
        }

        $categories = wp_get_post_categories($post_id);
        foreach ($categories as $cat_id) {
            $data = self::get_category_image_data($cat_id);
            if ($data) {
                return $data;
            }
        }

        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            return Helpers::get_image_data((int) $thumb_id, 'large', 'text_sidebar');
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private static function get_category_image_data(int $term_id): ?array
    {
        $image_id = get_term_meta($term_id, '_teksttv_category_image', true);
        if (!$image_id) {
            return null;
        }

        return Helpers::get_image_data((int) $image_id, 'large', 'text_sidebar');
    }
}
