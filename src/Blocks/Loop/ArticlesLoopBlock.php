<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\BuildContext;
use TekstTV\Blocks\Common\RecentPostsQuery;
use TekstTV\Blocks\Common\TaxonomyFilters;
use TekstTV\Blocks\Contracts\BlockType;
use TekstTV\Helpers;
use WP_Query;

final class ArticlesLoopBlock implements BlockType
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
        $dur_text = $block['duration_text'] ?? '';
        $dur_image = $block['duration_image'] ?? '';
        $default_text = (int) get_option('teksttv_duration_text', Helpers::DURATION_DEFAULTS['text']);
        $default_image = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['image']);

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Aantal', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][count]" value="<?php echo esc_attr((string) $count); ?>" min="1" max="50" class="small-text" data-summary="%sx" />
            </div>
            <?php TaxonomyFilters::render_selects($index, (array) ($block['taxonomy_filters'] ?? []), $prefix); ?>
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
            'count' => Helpers::clamp_int($raw['count'] ?? 3, 1, 50),
            'taxonomy_filters' => TaxonomyFilters::sanitize_from_post($raw),
        ];

        $dt = $raw['duration_text'] ?? '';
        $di = $raw['duration_image'] ?? '';
        if ($dt !== '') {
            $saved['duration_text'] = Helpers::clamp_int($dt, 1, 120);
        }
        if ($di !== '') {
            $saved['duration_image'] = Helpers::clamp_int($di, 1, 120);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        $slides = [];
        // Clamp at runtime too: values stored before the save-time clamp existed
        // could still be unbounded and would otherwise size the WP_Query.
        $count = Helpers::clamp_int($block['count'] ?? 3, 1, 50);
        $taxonomy_filters = $block['taxonomy_filters'] ?? [];

        // Features are runtime-authoritative: disabling one must stop its stored
        // meta from acting, even though the values remain in the database.
        $scheduling = Helpers::has_feature('scheduling');
        $custom_title = Helpers::has_feature('custom_title');
        $extra_images = Helpers::has_feature('extra_images');

        $meta_query = [
            'relation' => 'AND',
            ['key' => '_teksttv_active', 'value' => '1', 'compare' => '='],
        ];
        if ($scheduling) {
            $meta_query[] = Helpers::get_date_end_meta_query();
        }

        $query = new WP_Query(RecentPostsQuery::args($count, $taxonomy_filters, [
            'meta_query' => $meta_query,
        ]));

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            if ($scheduling) {
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
            }

            BuildContext::mark_post_seen((int) $post_id);

            $title_override = $custom_title ? get_post_meta($post_id, '_teksttv_title', true) : '';
            $title = !empty($title_override) ? $title_override : get_the_title();
            $content = get_post_meta($post_id, '_teksttv_content', true);
            $sidebar_image = self::get_sidebar_image_data($post_id);

            if (!empty($content)) {
                $pages = self::split_pages($content);
                foreach ($pages as $page_content) {
                    $slide = [
                        'type' => 'text',
                        'duration' => Helpers::duration_ms($block['duration_text'] ?? null, 'teksttv_duration_text', Helpers::DURATION_DEFAULTS['text']),
                        'title' => $title,
                        'body' => wpautop($page_content),
                    ];

                    if (!empty($sidebar_image)) {
                        $slide['image'] = $sidebar_image;
                    }

                    $slides[] = $slide;
                }
            }

            $images = $extra_images ? get_post_meta($post_id, '_teksttv_images', true) : [];
            if (!empty($images) && is_array($images)) {
                foreach ($images as $attachment_id) {
                    $image_data = Helpers::get_image_data((int) $attachment_id, 'large', 'image_slide');
                    if ($image_data) {
                        $slides[] = array_merge([
                            'type' => 'image',
                            'duration' => Helpers::duration_ms($block['duration_image'] ?? null, 'teksttv_duration_image', Helpers::DURATION_DEFAULTS['image']),
                        ], $image_data);
                    }
                }
            }
        }

        wp_reset_postdata();

        return $slides;
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
        // The sidebar_image feature owns the per-post override (including the
        // '0' suppression). When it is disabled, ignore the stored override and
        // fall through to the automatic category/thumbnail resolution.
        if (Helpers::has_feature('sidebar_image')) {
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
        }

        /** @var int|string|false $primary_term_id */
        $primary_term_id = apply_filters('teksttv_primary_category', get_post_meta($post_id, '_yoast_wpseo_primary_category', true), $post_id);
        if ($primary_term_id) {
            $data = self::get_category_image_data((int) $primary_term_id);
            if ($data) {
                return $data;
            }
        }

        // get_the_terms() reads the object-term cache primed by the articles
        // WP_Query; wp_get_post_categories() would issue a fresh query per post.
        $categories = get_the_terms($post_id, 'category');
        foreach (is_array($categories) ? $categories : [] as $cat) {
            $data = self::get_category_image_data((int) $cat->term_id);
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
