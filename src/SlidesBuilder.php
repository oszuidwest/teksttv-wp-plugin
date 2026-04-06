<?php

namespace TekstTV;

use WP_Query;

class SlidesBuilder
{
    /**
     * Get text slide duration in ms (block override or global default).
     *
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
     * Get image slide duration in ms (block override or global default).
     *
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
     * Build image data object for an attachment.
     *
     * @param int    $attachment_id The attachment post ID.
     * @param string $size          Image size to retrieve.
     * @return array<string, string>|null Image data array or null if attachment not found.
     */
    private static function get_image_data(int $attachment_id, string $size = 'large'): ?array
    {
        return Helpers::get_image_data($attachment_id, $size);
    }

    /**
     * Build ticker messages for a channel.
     *
     * @return list<array<string, string>>
     */
    public static function build_ticker(string $channel_slug): array
    {
        $items = get_option('teksttv_ticker_' . sanitize_key($channel_slug), []);
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $messages = [];
        foreach ($items as $item) {
            if (!Helpers::is_block_scheduled($item)) {
                continue;
            }

            $type = $item['type'] ?? '';
            $built = BlockRegistry::build($type, $item, $channel_slug);
            $messages = array_merge($messages, $built);
        }

        return $messages;
    }

    /**
     * Build all slides for a channel based on its loop configuration.
     *
     * @return list<array<string, mixed>>
     */
    public static function build(string $channel_slug): array
    {
        $blocks = Helpers::get_loop_config($channel_slug);
        if (empty($blocks)) {
            return [];
        }

        $slides = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $built = BlockRegistry::build($type, $block, $channel_slug);
            $slides = array_merge($slides, $built);
        }

        return array_values(array_filter($slides));
    }

    /**
     * Build a single image slide from an image block.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build_image_slide(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

        $image_id = (int) ($block['image_id'] ?? 0);
        if (!$image_id) {
            return [];
        }

        $image_data = self::get_image_data($image_id);
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

    /**
     * Build slides from an articles block.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build_article_slides(array $block, string $channel = ''): array
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

        // Limit by post age
        $max_age = (int) get_option('teksttv_max_post_age', 30);
        if ($max_age > 0) {
            $args['date_query'] = [
                ['after' => $max_age . ' days ago'],
            ];
        }

        // Apply taxonomy filters (each value is an array of term IDs)
        $tax_query = Helpers::build_tax_query($taxonomy_filters);
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Check day restrictions
            $days = get_post_meta($post_id, '_teksttv_days', true);
            if (!empty($days) && is_array($days)) {
                if (!Helpers::is_allowed_on_day($days)) {
                    continue;
                }
            }

            // Check date range
            $date_start = get_post_meta($post_id, '_teksttv_date_start', true);
            $date_end = get_post_meta($post_id, '_teksttv_date_end', true);
            if (!Helpers::is_within_date_range($date_start, $date_end)) {
                continue;
            }

            // Get title, content and sidebar image
            $custom_title = get_post_meta($post_id, '_teksttv_title', true);
            $title = !empty($custom_title) ? $custom_title : get_the_title();
            $content = get_post_meta($post_id, '_teksttv_content', true);
            $sidebar_image = self::get_sidebar_image_data($post_id);

            // Build text slides from content
            if (!empty($content)) {
                $pages = Helpers::has_feature('page_separator') ? preg_split('/<p[^>]*>\s*-{3,}\s*<\/p>|\n*-{3,}\n*/i', $content) : [$content];
                foreach ($pages as $page_content) {
                    $page_content = trim($page_content);
                    if (empty($page_content)) {
                        continue;
                    }

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

            // Extra images as separate fullscreen slides
            $images = get_post_meta($post_id, '_teksttv_images', true);
            if (!empty($images) && is_array($images)) {
                foreach ($images as $attachment_id) {
                    $image_data = self::get_image_data((int) $attachment_id);
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

    private const WEATHER_DURATION = 15000;
    private const TRANSITION_DURATION = 5000;

    private static ?WeatherProvider $weather_provider = null;
    private static bool $weather_provider_resolved = false;

    /**
     * Get the weather provider instance.
     * Filterable via 'teksttv_weather_provider' for custom implementations.
     */
    private static function get_weather_provider(): ?WeatherProvider
    {
        if (self::$weather_provider_resolved) {
            return self::$weather_provider;
        }

        $api_key = get_option('teksttv_openweather_api_key', '');

        $provider = !empty($api_key) ? new OpenWeatherProvider($api_key) : null;

        /**
         * Filter the weather provider.
         *
         * @param WeatherProvider|null $provider The provider instance.
         */
        self::$weather_provider = apply_filters('teksttv_weather_provider', $provider);
        self::$weather_provider_resolved = true;

        return self::$weather_provider;
    }

    private static function wind_deg_to_direction(float $deg): string
    {
        $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
        $index = (int) round($deg / 22.5) % 16;
        return $directions[$index];
    }

    private static function wind_speed_to_beaufort(float $speed): int
    {
        $scale = [0.3, 1.6, 3.4, 5.5, 8.0, 10.8, 13.9, 17.2, 20.8, 24.5, 28.5, 32.7];
        foreach ($scale as $bft => $threshold) {
            if ($speed < $threshold) {
                return $bft;
            }
        }
        return 12;
    }

    /**
     * Build a weather slide from a weather block.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build_weather_slide(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

        $location = $block['location'] ?? '';
        $title = $block['title'] ?? '';
        if (empty($location)) {
            return [];
        }

        $provider = self::get_weather_provider();
        if (!$provider) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV: No weather provider configured. Check OpenWeather API key in settings.');
            return [];
        }

        $weather = $provider->fetch($location);
        if (!$weather || empty($weather['days'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('TekstTV: Weather fetch failed for location "%s".', $location));
            return [];
        }

        $duration = !empty($block['duration']) ? (int) $block['duration'] * 1000 : self::WEATHER_DURATION;

        $days_output = [];
        foreach ($weather['days'] as $index => $day) {
            $date = $day['date'];
            $days_output[] = [
                'date' => date_i18n('l j M', $date->getTimestamp()),
                'day_short' => $index === 0 ? 'vandaag' : date_i18n('D', $date->getTimestamp()),
                'temp_min' => (int) round($day['temp_min']),
                'temp_max' => (int) round($day['temp_max']),
                'weather_id' => $day['weather_id'],
                'description' => $day['description'],
                'icon' => $day['icon'],
                'wind_direction' => self::wind_deg_to_direction($day['wind_deg'] ?? 0),
                'wind_beaufort' => self::wind_speed_to_beaufort($day['wind_speed'] ?? 0),
            ];
        }

        return [[
            'type' => 'weather',
            'duration' => $duration,
            'title' => $title,
            'location' => $weather['city'],
            'days' => $days_output,
        ]
        ];
    }

    /**
     * Build commercial slides from a commercial block.
     *
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build_commercial_slides(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

        $groups = (array) ($block['groups'] ?? []);
        if (empty($groups)) {
            return [];
        }

        // Get active campaigns for this channel and filter by group
        $campaigns = Helpers::get_active_campaigns($channel);
        $slides = [];

        foreach ($campaigns as $campaign) {
            $campaign_group = (string) ($campaign['group'] ?? '');
            if (!in_array($campaign_group, $groups, true)) {
                continue;
            }

            $duration = !empty($campaign['duration']) ? (int) $campaign['duration'] * 1000 : (int) get_option('teksttv_duration_image', 7) * 1000;

            foreach ($campaign['slides'] ?? [] as $attachment_id) {
                $url = wp_get_attachment_url((int) $attachment_id);
                if ($url) {
                    $slides[] = [
                        'type' => 'commercial',
                        'duration' => $duration,
                        'url' => $url,
                    ];
                }
            }
        }

        // Apply rotation limit: show only N slides, rotating based on time
        $limit = !empty($block['limit']) ? (int) $block['limit'] : 0;
        if ($limit > 0 && count($slides) > $limit) {
            $offset = (int) floor(time() / 180) % count($slides);
            $rotated = [];
            for ($i = 0; $i < $limit; $i++) {
                $rotated[] = $slides[($offset + $i) % count($slides)];
            }
            $slides = $rotated;
        }

        // Add intro/outro transitions if there are commercial slides
        if (!empty($slides)) {
            $intro_id = (int) ($block['intro_image_id'] ?? 0);
            if ($intro_id) {
                $intro_url = wp_get_attachment_url($intro_id);
                if ($intro_url) {
                    array_unshift($slides, [
                        'type' => 'commercial_transition',
                        'duration' => self::TRANSITION_DURATION,
                        'url' => $intro_url,
                    ]);
                }
            }

            $outro_id = (int) ($block['outro_image_id'] ?? 0);
            if ($outro_id) {
                $outro_url = wp_get_attachment_url($outro_id);
                if ($outro_url) {
                    $slides[] = [
                        'type' => 'commercial_transition',
                        'duration' => self::TRANSITION_DURATION,
                        'url' => $outro_url,
                    ];
                }
            }
        }

        return $slides;
    }

    /**
     * Get sidebar image data for a post.
     *
     * Priority: per-post override > category image > post thumbnail.
     * Returns image data array (url + caption + attribution) or null.
     *
     * @return array<string, string>|null Image data array or null.
     */
    private static function get_sidebar_image_data(int $post_id): ?array
    {
        // 1. Per-post override (0 = explicitly no image)
        $override_id = get_post_meta($post_id, '_teksttv_sidebar_image', true);
        if ($override_id === '0' || $override_id === 0) {
            return null;
        }
        if ($override_id) {
            $data = self::get_image_data((int) $override_id);
            if ($data) {
                return $data;
            }
        }

        // 2. Primary category then first category with TekstTV image
        /** @var int|string|false $primary_term_id Filterable primary category term ID. */
        $primary_term_id = apply_filters('teksttv_primary_category', get_post_meta($post_id, '_yoast_wpseo_primary_category', true), $post_id);
        if ($primary_term_id) {
            $data = self::get_category_image_data((int) $primary_term_id);
            if ($data) {
                return $data;
            }
        }

        // Fallback: first category with a TekstTV image
        $categories = wp_get_post_categories($post_id);
        foreach ($categories as $cat_id) {
            $data = self::get_category_image_data($cat_id);
            if ($data) {
                return $data;
            }
        }

        // Final fallback: post thumbnail
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            return self::get_image_data((int) $thumb_id);
        }

        return null;
    }

    /**
     * Get image data for a category's TekstTV image.
     *
     * @return array<string, string>|null
     */
    private static function get_category_image_data(int $term_id): ?array
    {
        $image_id = get_term_meta($term_id, '_teksttv_category_image', true);
        if (!$image_id) {
            return null;
        }

        return self::get_image_data((int) $image_id);
    }
}
