<?php

namespace TekstTV;

use DateTime;
use DateTimeInterface;

class Helpers
{
    /**
     * Translated short labels for the ISO-8601 days of the week (1=Mon..7=Sun).
     *
     * Keys are PHP-normalised to ints; callers that need string ISO day
     * identifiers should cast with `(string) $num`.
     *
     * @return array<int, string>
     */
    public static function get_day_labels(): array
    {
        return [
            '1' => __('Ma', 'teksttv-wp-plugin'),
            '2' => __('Di', 'teksttv-wp-plugin'),
            '3' => __('Wo', 'teksttv-wp-plugin'),
            '4' => __('Do', 'teksttv-wp-plugin'),
            '5' => __('Vr', 'teksttv-wp-plugin'),
            '6' => __('Za', 'teksttv-wp-plugin'),
            '7' => __('Zo', 'teksttv-wp-plugin'),
        ];
    }

    /**
     * Sanitize a raw days-of-week input (typically from a $_POST checkbox array)
     * into a list of valid ISO-8601 day strings.
     *
     * Returns null when no restriction should be saved (all 7 days checked or
     * non-array input). An empty array means "no days selected".
     *
     * @param mixed $raw
     * @return list<string>|null
     */
    public static function sanitize_days_input(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $valid = ['1', '2', '3', '4', '5', '6', '7'];
        $days = array_values(array_unique(array_intersect(array_map('sanitize_text_field', $raw), $valid)));
        return count($days) < 7 ? $days : null;
    }

    /**
     * Extract sanitized scheduling fields (date_start, date_end, days) from a
     * raw POST payload. Empty date values are omitted; an empty days list is
     * retained to represent "no days selected".
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function extract_scheduling_fields(array $raw): array
    {
        $fields = [];

        $ds = self::sanitize_date_input($raw['date_start'] ?? '');
        $de = self::sanitize_date_input($raw['date_end'] ?? '');
        if ($ds !== '') {
            $fields['date_start'] = $ds;
        }
        if ($de !== '') {
            $fields['date_end'] = $de;
        }

        // Checkbox groups are absent from POST when every box is unchecked.
        $sanitized_days = self::sanitize_days_input($raw['days'] ?? []);
        if ($sanitized_days !== null) {
            $fields['days'] = $sanitized_days;
        }

        return $fields;
    }

    /**
     * Check if content should be displayed on the given day.
     *
     * @param list<string>|null $allowed_days ISO-8601 day numbers (1=Mon, 7=Sun); null means all days, [] means none.
     * @param DateTimeInterface|null $date Date to check, defaults to current date
     */
    public static function is_allowed_on_day(?array $allowed_days, ?DateTimeInterface $date = null): bool
    {
        if ($allowed_days === null) {
            return true;
        }
        if ($allowed_days === []) {
            return false;
        }

        $date = $date ?? current_datetime();
        $current_day = $date->format('N');

        return in_array((string) $current_day, array_map('strval', $allowed_days), true);
    }

    /**
     * Sanitize and strictly validate a Y-m-d date input.
     */
    public static function sanitize_date_input(mixed $raw): string
    {
        $date = sanitize_text_field((string) $raw);
        if ($date === '') {
            return '';
        }

        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }

    /**
     * Check if current date falls within an optional date range.
     * Empty values mean no restriction on that side.
     *
     * @param string|null $start_date Y-m-d format
     * @param string|null $end_date Y-m-d format
     */
    public static function is_within_date_range(?string $start_date, ?string $end_date): bool
    {
        $now = current_datetime();
        $timezone = wp_timezone();

        if (!empty($start_date)) {
            $start = DateTime::createFromFormat('Y-m-d', trim($start_date), $timezone);
            if ($start && $now < $start->setTime(0, 0, 0)) {
                return false;
            }
        }

        if (!empty($end_date)) {
            $end = DateTime::createFromFormat('Y-m-d', trim($end_date), $timezone);
            if ($end && $now > $end->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the stored channels list.
     *
     * @return list<array{slug: string, label: string}>
     */
    public static function get_channels(): array
    {
        $channels = get_option('teksttv_channels', []);
        if (empty($channels)) {
            return [['slug' => 'tv1', 'label' => __('TV 1', 'teksttv-wp-plugin')]];
        }
        return $channels;
    }

    /**
     * Get the configured channel slugs.
     *
     * @return list<string>
     */
    public static function channel_slugs(): array
    {
        return array_column(self::get_channels(), 'slug');
    }

    /**
     * Features enabled when the option has never been saved. Also the default
     * for the registered setting — keep both reads on this single list.
     */
    public const DEFAULT_FEATURES = [
        'custom_title', 'sidebar_image', 'extra_images',
        'scheduling', 'page_separator',
        'bold', 'italic', 'underline', 'lists',
        'ai_generate',
    ];

    /**
     * Get the enabled features.
     *
     * @return list<string> Array of feature slugs
     */
    public static function get_features(): array
    {
        return get_option('teksttv_features', self::DEFAULT_FEATURES);
    }

    /**
     * Whether AI generation is enabled and the WP AI Client is available.
     */
    public static function ai_supported(): bool
    {
        return self::has_feature('ai_generate') && function_exists('wp_supports_ai') && wp_supports_ai();
    }

    /**
     * Check if a feature is enabled.
     */
    public static function has_feature(string $feature): bool
    {
        return in_array($feature, self::get_features(), true);
    }

    /**
     * Clamp a raw numeric input into an inclusive integer range.
     *
     * The UI enforces min/max via input attributes, but a crafted POST can
     * bypass those; this is the authoritative server-side clamp.
     *
     * @param mixed $value
     */
    public static function clamp_int(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, absint($value)));
    }

    /**
     * Resolve a slide duration in milliseconds from an optional per-block
     * override (in seconds), falling back to a duration option (in seconds).
     *
     * @param mixed $override_seconds Stored block value; empty means "use the option".
     * @param string $option_name Duration option to read, or '' to use $default_seconds directly.
     */
    public static function duration_ms(mixed $override_seconds, string $option_name, int $default_seconds): int
    {
        if (!empty($override_seconds)) {
            return (int) $override_seconds * 1000;
        }

        $seconds = $option_name !== '' ? (int) get_option($option_name, $default_seconds) : $default_seconds;
        return $seconds * 1000;
    }

    /**
     * Get the AI prompt configuration with defaults.
     *
     * @return array{system: string, prompt_title: string, prompt_body: string, word_limit: int, word_limit_photo: int, title_char_limit: int, min_input_words: int, max_retries: int, rate_limit: int, region_taxonomy: string, provider: string, model: string, temperature: string|float, top_p: string|float, max_tokens: int}
     */
    public static function get_ai_prompts(): array
    {
        $saved = get_option('teksttv_ai_prompts', []);
        $word_limit = max(10, (int) ($saved['word_limit'] ?? 100));
        $word_limit_photo = (int) ($saved['word_limit_photo'] ?? 0);
        $word_limit_photo = $word_limit_photo >= 1 ? $word_limit_photo : $word_limit;
        $title_char_limit = max(10, (int) ($saved['title_char_limit'] ?? 40));
        $min_input = max(0, (int) ($saved['min_input_words'] ?? 50));
        $max_retries = self::clamp_int($saved['max_retries'] ?? 3, 1, 5);
        $rate_limit = self::clamp_int($saved['rate_limit'] ?? 10, 1, 60);

        $defaults = [
            'system' => 'Je bent een eindredacteur voor tekst-tv. Schrijf in natuurlijk, vloeiend Nederlands voor een breed publiek. Gebruik korte, heldere zinnen. Schrijf alleen in het Nederlands en gebruik geen gedachtestreepjes.',
            'prompt_title' => 'Schrijf een korte, pakkende kop voor tekst-tv (maximaal {{chars}} tekens) gebaseerd op dit artikel. Geef alleen de kop terug, zonder aanhalingstekens.',
            'prompt_body' => 'Vat dit nieuwsartikel samen voor tekst-tv in maximaal {{words}} woorden. Schrijf in vloeiende, korte zinnen zonder HTML-opmaak.',
        ];

        return [
            'system' => !empty($saved['system']) ? $saved['system'] : $defaults['system'],
            'prompt_title' => !empty($saved['prompt_title']) ? $saved['prompt_title'] : $defaults['prompt_title'],
            'prompt_body' => !empty($saved['prompt_body']) ? $saved['prompt_body'] : $defaults['prompt_body'],
            'word_limit' => $word_limit,
            'word_limit_photo' => $word_limit_photo,
            'title_char_limit' => $title_char_limit,
            'min_input_words' => $min_input,
            'max_retries' => $max_retries,
            'rate_limit' => $rate_limit,
            'region_taxonomy' => $saved['region_taxonomy'] ?? '',
            'provider' => $saved['provider'] ?? '',
            'model' => $saved['model'] ?? '',
            'temperature' => $saved['temperature'] ?? '',
            'top_p' => $saved['top_p'] ?? '',
            'max_tokens' => max(64, (int) ($saved['max_tokens'] ?? 2048)),
        ];
    }

    /**
     * Get available AI models grouped by provider.
     *
     * @return array<string, array{label: string, models: array<string, string>}>
     */
    public static function get_ai_models(): array
    {
        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return [];
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()],
                []
            );

            $result = [];
            foreach ($registry->findModelsMetadataForSupport($requirements) as $provider_models) {
                $provider = $provider_models->getProvider();
                $provider_id = $provider->getId();
                $models = [];
                foreach ($provider_models->getModels() as $model) {
                    $models[$model->getId()] = $model->getName();
                }
                if (!empty($models)) {
                    $result[$provider_id] = [
                        'label' => $provider->getName(),
                        'models' => $models,
                    ];
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the loop configuration for a channel.
     *
     * @return list<array<string, mixed>> Array of block definitions
     */
    public static function get_loop_config(string $channel_slug): array
    {
        return get_option('teksttv_loop_' . sanitize_key($channel_slug), []);
    }

    /**
     * Get the ticker configuration for a channel.
     *
     * @return list<array<string, mixed>> Array of ticker item definitions
     */
    public static function get_ticker_config(string $channel_slug): array
    {
        $items = get_option('teksttv_ticker_' . sanitize_key($channel_slug), []);
        return is_array($items) ? $items : [];
    }

    /**
     * Get configured campaign groups as id/label pairs.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function get_campaign_groups(): array
    {
        $groups = get_option('teksttv_campaign_groups', []);
        if (empty($groups) || !is_array($groups)) {
            return [];
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $label = (string) ($group['label'] ?? '');
            $id = (string) ($group['id'] ?? '');
            if ($label === '' || $id === '') {
                continue;
            }
            $normalized[] = ['id' => $id, 'label' => $label];
        }

        return $normalized;
    }

    /**
     * Derive a stable id from a group label, used when a newly added group has
     * no id yet.
     */
    public static function campaign_group_id(string $label): string
    {
        return 'grp_' . substr(md5($label), 0, 12);
    }

    /**
     * Get all saved campaigns (sponsor slots, etc.).
     *
     * @return list<array<string, mixed>>
     */
    public static function get_campaigns(): array
    {
        return get_option('teksttv_campaigns', []);
    }

    /**
     * Get campaigns active for a specific channel (assignment + date range).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_active_campaigns(string $channel): array
    {
        $campaigns = self::get_campaigns();

        return array_filter($campaigns, function ($campaign) use ($channel) {
            // Must be assigned to this channel
            $channels = $campaign['channels'] ?? [];
            if (!in_array($channel, $channels, true)) {
                return false;
            }

            // Must pass date range + day-of-week scheduling
            return self::is_block_scheduled($campaign);
        });
    }

    /**
     * Get the preview base URL from settings.
     */
    public static function get_preview_url(): string
    {
        return get_option('teksttv_preview_url', '');
    }

    /**
     * Check if a block/item passes its scheduling constraints (date range + weekdays).
     *
     * @param array<string, mixed> $block Block or ticker item data.
     */
    public static function is_block_scheduled(array $block): bool
    {
        if (!self::is_within_date_range($block['date_start'] ?? null, $block['date_end'] ?? null)) {
            return false;
        }
        if (array_key_exists('days', $block)) {
            $days = is_array($block['days']) ? $block['days'] : [];
            if (!self::is_allowed_on_day($days)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build a tax_query array from taxonomy filters.
     *
     * @param array<string, mixed> $taxonomy_filters Keyed by taxonomy name, values are term ID arrays.
     * @return list<array{taxonomy: string, field: string, terms: list<int>}>
     */
    public static function build_tax_query(array $taxonomy_filters): array
    {
        $tax_query = [];
        foreach ($taxonomy_filters as $taxonomy => $term_ids) {
            $term_ids = (array) $term_ids;
            $term_ids = array_filter(array_map('intval', $term_ids));
            if (!empty($term_ids)) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_ids,
                ];
            }
        }
        return $tax_query;
    }

    /**
     * Build image data for an attachment (url, caption, attribution).
     *
     * When the caller passes a `$slot` identifying which template slot the
     * image will fill, the `teksttv_image_url` filter is applied so the
     * active theme can return a slot-appropriate (e.g. focal-point-aware,
     * pre-cropped) variant. The plugin stays template-agnostic — pixel
     * dimensions live in the theme that owns the layout.
     *
     * Known slots:
     *   - `image_slide`   full-screen image slide
     *   - `text_sidebar`  sidebar image on a text slide
     *
     * @param int         $attachment_id The attachment post ID.
     * @param string      $size          Fallback WP image size used when no theme handles the slot.
     * @param string|null $slot          Optional semantic slot identifier (see list above).
     * @return array<string, string>|null Image data array or null if not found.
     */
    public static function get_image_data(
        int $attachment_id,
        string $size = 'large',
        ?string $slot = null
    ): ?array {
        $url = wp_get_attachment_image_url($attachment_id, $size);
        if (!$url) {
            return null;
        }

        $url = (string) apply_filters('teksttv_image_url', $url, $attachment_id, $slot);

        $data = ['url' => $url];

        $caption = wp_get_attachment_caption($attachment_id) ?: '';
        if (!empty($caption)) {
            $data['caption'] = $caption;
        }

        $attribution = apply_filters('teksttv_image_attribution', '', $attachment_id);
        if (!empty($attribution)) {
            $data['attribution'] = $attribution;
        }

        return $data;
    }

    /**
     * Count words in a string. More reliable than str_word_count() for Dutch and non-ASCII text.
     */
    public static function count_words(string $text): int
    {
        return (int) preg_match_all('/\S+/', $text);
    }

    /**
     * Script dependencies for admin.js (uses wp.media, which requires Underscore on `_`).
     *
     * @return list<string>
     */
    public static function admin_script_dependencies(): array
    {
        return ['jquery', 'underscore', 'media-editor', 'wp-i18n'];
    }

    /**
     * Enqueue admin.js and its styles. Call wp_enqueue_media() first.
     *
     * @param list<string> $extra_deps Additional script handles to load before admin.js.
     * @param list<string> $style_deps Additional style handles to load before admin.css.
     */
    public static function enqueue_admin_script(array $extra_deps = [], array $style_deps = []): void
    {
        wp_enqueue_media();

        $deps = array_merge(self::admin_script_dependencies(), $extra_deps);

        wp_enqueue_script(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.js',
            $deps,
            self::asset_version('assets/admin.js'),
            true
        );

        wp_enqueue_style(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.css',
            $style_deps,
            self::asset_version('assets/admin.css')
        );

        if (wp_script_is('underscore', 'registered')) {
            wp_add_inline_script(
                'underscore',
                'if(typeof window._!=="undefined"&&typeof window._.defaults==="function"){window.wpUnderscore=window._;}',
                'after'
            );
        }
    }

    /**
     * Restore Underscore on `_` after late-loading scripts (e.g. Yoast SEO) clobber it.
     */
    public static function print_underscore_restore(): void
    {
        if (!wp_script_is('teksttv-admin', 'enqueued')) {
            return;
        }

        wp_print_inline_script_tag(
            '(function(){var u=window.wpUnderscore;if(u&&typeof window._.defaults!=="function"){window._=u;}})();'
        );
    }

    /**
     * WordPress script/style version string from file mtime (cache bust on deploy/edit).
     *
     * @param string $relative_path Path under the plugin root, e.g. assets/admin.js
     */
    public static function asset_version(string $relative_path): string
    {
        $path = TEKSTTV_PLUGIN_DIR . ltrim($relative_path, '/');
        if (!is_readable($path)) {
            return TEKSTTV_VERSION;
        }

        return (string) filemtime($path);
    }

    /**
     * Build a meta_query fragment that pre-filters expired posts in SQL.
     *
     * Excludes posts whose _teksttv_date_end is set and is before today.
     * Posts without a date_end (or with empty value) pass through.
     *
     * @return array<int|string, mixed>
     */
    public static function get_date_end_meta_query(): array
    {
        $today = current_datetime()->format('Y-m-d');
        return [
            'relation' => 'OR',
            ['key' => '_teksttv_date_end', 'compare' => 'NOT EXISTS'],
            ['key' => '_teksttv_date_end', 'value' => '', 'compare' => '='],
            ['key' => '_teksttv_date_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
        ];
    }
}
