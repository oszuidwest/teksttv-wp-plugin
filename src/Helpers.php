<?php

namespace TekstTV;

use DateTime;
use DateTimeInterface;

/**
 * @phpstan-type AiConfig array{system: string, prompt_title: string, prompt_body: string, word_limit: int, word_limit_photo: int, title_char_limit: int, min_input_words: int, max_retries: int, rate_limit: int, region_taxonomy: string, provider: string, model: string, temperature: string|float, top_p: string|float, max_tokens: int}
 */
class Helpers
{
    /** @var list<array{name: string, label: string, terms: array<int, string>}>|null */
    private static ?array $post_taxonomies_cache = null;

    /** Memoized provider capability probe; it runs model discovery, so at most once per request. */
    private static ?bool $ai_supported_cache = null;

    /**
     * Build a stable, reindexable id for a repeated admin form field. The
     * prefix mirrors the list root's DOM id, and the scheme must match the
     * JS reindexer (`${root.id}-${index}-${key}` in workbench.ts).
     */
    public static function field_id(string $prefix, int|string $index, string $field): string
    {
        return str_replace('_', '-', $prefix . '-' . (string) $index . '-' . $field);
    }

    /**
     * Echo the escaped `for` attribute matching field_attrs() for the same
     * field. `$field` is the form key (underscores allowed); the id variant
     * is derived.
     */
    public static function field_for(string $prefix, int|string $index, string $field): void
    {
        echo 'for="' . esc_attr(self::field_id($prefix, $index, $field)) . '"';
    }

    /** Echo the escaped `id` + `name` attribute pair for a repeated admin form field. */
    public static function field_attrs(string $prefix, int|string $index, string $field): void
    {
        echo 'id="' . esc_attr(self::field_id($prefix, $index, $field)) . '"'
            . ' name="' . esc_attr($prefix . '[' . (string) $index . '][' . $field . ']') . '"';
    }

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
            '1' => 'Ma',
            '2' => 'Di',
            '3' => 'Wo',
            '4' => 'Do',
            '5' => 'Vr',
            '6' => 'Za',
            '7' => 'Zo',
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

        // Missing checkbox groups mean "none selected"; explicit null remains unrestricted.
        $raw_days = array_key_exists('days', $raw) ? $raw['days'] : [];
        $sanitized_days = self::sanitize_days_input($raw_days);
        if ($sanitized_days !== null) {
            $fields['days'] = $sanitized_days;
        }

        return $fields;
    }

    /**
     * Normalize a stored days value to its tri-state form: null (or any
     * non-array, e.g. the '' that get_post_meta returns for missing meta)
     * means "all days"; an empty list means "no days"; a list restricts.
     *
     * @return list<string>|null
     */
    public static function normalize_days(mixed $days): ?array
    {
        return is_array($days) ? $days : null;
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
        if (empty($start_date) && empty($end_date)) {
            return true;
        }

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
        if (!is_array($channels) || empty($channels)) {
            return [['slug' => 'tv1', 'label' => 'TV 1']];
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
        $features = get_option('teksttv_features', self::DEFAULT_FEATURES);
        return is_array($features) ? $features : self::DEFAULT_FEATURES;
    }

    /**
     * Whether AI generation is enabled and a provider supports the configured
     * TekstTV text-generation requirements.
     */
    public static function ai_supported(): bool
    {
        if (!self::has_feature('ai_generate')) {
            return false;
        }

        return self::$ai_supported_cache ??= AiGenerator::supports_text_generation(self::get_ai_prompts());
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

    public const DURATION_MIN_SECONDS = 1;
    public const DURATION_MAX_SECONDS = 120;

    /**
     * Default seconds for the per-type duration options. Single source for the
     * registered setting defaults, the admin placeholders, and build-time
     * fallbacks — keep all reads on this map.
     */
    public const DURATION_DEFAULTS = [
        'teksttv_duration_text' => 20,
        'teksttv_duration_image' => 7,
        'teksttv_duration_iframe' => 30,
    ];

    /**
     * Resolve a slide duration in milliseconds from an optional per-block
     * override (in seconds), falling back to a duration option (in seconds)
     * whose default comes from {@see self::DURATION_DEFAULTS}.
     *
     * @param mixed $override_seconds Stored block value; empty means "use the option".
     * @param key-of<self::DURATION_DEFAULTS> $option_name Duration option to read.
     */
    public static function duration_ms(mixed $override_seconds, string $option_name): int
    {
        $default = self::DURATION_DEFAULTS[$option_name];
        return self::fixed_duration_ms(
            $override_seconds,
            !empty($override_seconds) ? $default : (int) get_option($option_name, $default)
        );
    }

    /**
     * Like {@see self::duration_ms()} for blocks without a duration option
     * (weather): an optional per-block override with a fixed default.
     */
    public static function fixed_duration_ms(mixed $override_seconds, int $default_seconds): int
    {
        $seconds = !empty($override_seconds) ? (int) $override_seconds : $default_seconds;
        return self::clamp_int($seconds, self::DURATION_MIN_SECONDS, self::DURATION_MAX_SECONDS) * 1000;
    }

    /**
     * Normalize the bounded numeric AI prompt settings used by both storage
     * sanitization and runtime reads.
     *
     * A zero word_limit_photo remains the stored "inherit word_limit" marker;
     * get_ai_prompts() resolves it to the effective word limit at read time.
     *
     * @param array<string, mixed> $settings
     * @return array{word_limit: int, word_limit_photo: int, title_char_limit: int, min_input_words: int, max_retries: int, rate_limit: int, temperature: string|float, top_p: string|float, max_tokens: int}
     */
    public static function normalize_ai_prompt_limits(array $settings): array
    {
        $temperature = $settings['temperature'] ?? '';
        $top_p = $settings['top_p'] ?? '';
        $photo_word_limit = self::clamp_int($settings['word_limit_photo'] ?? 0, 0, 500);
        if ($photo_word_limit > 0) {
            $photo_word_limit = max(10, $photo_word_limit);
        }

        return [
            'word_limit' => self::clamp_int($settings['word_limit'] ?? 100, 10, 500),
            'word_limit_photo' => $photo_word_limit,
            'title_char_limit' => self::clamp_int($settings['title_char_limit'] ?? 40, 10, 100),
            'min_input_words' => self::clamp_int($settings['min_input_words'] ?? 50, 0, 500),
            'max_retries' => self::clamp_int($settings['max_retries'] ?? 3, 1, 5),
            'rate_limit' => self::clamp_int($settings['rate_limit'] ?? 10, 1, 60),
            'temperature' => $temperature !== '' ? max(0, min(2, (float) $temperature)) : '',
            'top_p' => $top_p !== '' ? max(0, min(1, (float) $top_p)) : '',
            'max_tokens' => self::clamp_int($settings['max_tokens'] ?? 2048, 64, 8192),
        ];
    }

    /**
     * Get the AI prompt configuration with defaults.
     *
     * @return AiConfig
     */
    public static function get_ai_prompts(): array
    {
        $saved = get_option('teksttv_ai_prompts', []);
        $saved = is_array($saved) ? $saved : [];
        $limits = self::normalize_ai_prompt_limits($saved);
        // Resolve the stored "inherit word_limit" marker (0) at read time.
        if ($limits['word_limit_photo'] < 1) {
            $limits['word_limit_photo'] = $limits['word_limit'];
        }

        $defaults = [
            'system' => 'Je bent een eindredacteur voor tekst-tv. Schrijf in natuurlijk, vloeiend Nederlands voor een breed publiek. Gebruik korte, heldere zinnen. Schrijf alleen in het Nederlands en gebruik geen gedachtestreepjes.',
            'prompt_title' => 'Schrijf een korte, pakkende kop voor tekst-tv (maximaal {{chars}} tekens) gebaseerd op dit artikel. Geef alleen de kop terug, zonder aanhalingstekens.',
            'prompt_body' => 'Vat dit nieuwsartikel samen voor tekst-tv in maximaal {{words}} woorden. Schrijf in vloeiende, korte zinnen zonder HTML-opmaak.',
        ];

        return array_merge([
            'system' => !empty($saved['system']) ? $saved['system'] : $defaults['system'],
            'prompt_title' => !empty($saved['prompt_title']) ? $saved['prompt_title'] : $defaults['prompt_title'],
            'prompt_body' => !empty($saved['prompt_body']) ? $saved['prompt_body'] : $defaults['prompt_body'],
            'region_taxonomy' => $saved['region_taxonomy'] ?? '',
            'provider' => $saved['provider'] ?? '',
            'model' => $saved['model'] ?? '',
        ], $limits);
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
     * Get configured commercial blocks as id/label pairs.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function get_commercial_blocks(): array
    {
        $commercial_blocks = get_option('teksttv_commercial_blocks', []);
        if (empty($commercial_blocks) || !is_array($commercial_blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($commercial_blocks as $commercial_block) {
            if (!is_array($commercial_block)) {
                continue;
            }
            $label = (string) ($commercial_block['label'] ?? '');
            $id = (string) ($commercial_block['id'] ?? '');
            if ($label === '' || $id === '') {
                continue;
            }
            $normalized[] = ['id' => $id, 'label' => $label];
        }

        return $normalized;
    }

    /**
     * Derive a stable id from a commercial block label, used when a newly added block has
     * no id yet.
     */
    public static function commercial_block_id(string $label): string
    {
        return 'cblock_' . substr(md5($label), 0, 12);
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
        return self::is_allowed_on_day(self::normalize_days($block['days'] ?? null));
    }

    /**
     * Get all public taxonomies that apply to posts. Cached per request.
     *
     * @return list<array{name: string, label: string, terms: array<int, string>}>
     */
    public static function get_post_taxonomies(): array
    {
        if (self::$post_taxonomies_cache !== null) {
            return self::$post_taxonomies_cache;
        }

        $result = [];
        foreach (get_object_taxonomies('post', 'objects') as $tax) {
            if (!$tax->public || $tax->name === 'post_format') {
                continue;
            }

            // Only id => name is needed; skips hydrating full WP_Term objects
            // (post_tag alone can be thousands of terms on a news site).
            $terms = get_terms([
                'taxonomy' => $tax->name,
                'hide_empty' => false,
                'fields' => 'id=>name',
            ]);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $result[] = [
                'name' => $tax->name,
                'label' => $tax->labels->singular_name,
                'terms' => $terms,
            ];
        }

        self::$post_taxonomies_cache = $result;
        return $result;
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
        return ['jquery', 'underscore', 'media-editor', 'wp-api-fetch'];
    }

    /**
     * Enqueue admin.js and its styles.
     */
    public static function enqueue_admin_script(): void
    {
        wp_enqueue_media();

        wp_enqueue_script(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.js',
            self::admin_script_dependencies(),
            TEKSTTV_VERSION,
            true
        );

        wp_enqueue_style(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.css',
            [],
            TEKSTTV_VERSION
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
            '(function(){var u=window.wpUnderscore;if(u&&(!window._||typeof window._.defaults!=="function")){window._=u;}})();'
        );
    }

    /**
     * Build a meta_query fragment for posts whose optional date range includes today.
     *
     * @return array<int|string, mixed>
     */
    public static function get_date_range_meta_query(): array
    {
        $today = current_datetime()->format('Y-m-d');
        return [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_teksttv_date_start', 'compare' => 'NOT EXISTS'],
                ['key' => '_teksttv_date_start', 'value' => '', 'compare' => '='],
                ['key' => '_teksttv_date_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE'],
            ],
            [
                'relation' => 'OR',
                ['key' => '_teksttv_date_end', 'compare' => 'NOT EXISTS'],
                ['key' => '_teksttv_date_end', 'value' => '', 'compare' => '='],
                ['key' => '_teksttv_date_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
            ],
        ];
    }
}
