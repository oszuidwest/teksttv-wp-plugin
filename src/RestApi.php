<?php

namespace TekstTV;

use WP_Error;
use WP_REST_Response;
use WP_REST_Request;

class RestApi
{
    private const NAMESPACE = 'teksttv/v1';

    private const CHANNEL_SLIDE_OPTION_PREFIXES = [
        'teksttv_loop_',
        'teksttv_ticker_',
    ];

    /**
     * Non-duration options read while building slide or ticker payloads.
     *
     * Loop and ticker options are handled separately because their suffix
     * identifies the only channel whose cache needs invalidating. Duration
     * option names come from Helpers::DURATION_DEFAULTS.
     */
    private const OTHER_GLOBAL_SLIDE_OPTIONS = [
        'teksttv_campaigns',
        'teksttv_enabled_taxonomies',
        'teksttv_features',
        'teksttv_max_post_age',
        'teksttv_openweather_api_key',
    ];

    /** @var array<string, true> Channels already auto-invalidated in this request. */
    private static array $automatically_invalidated_channels = [];

    /** @var array<string, mixed> Old values for supported options awaiting deletion. */
    private static array $pending_option_deletions = [];

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('added_option', [self::class, 'invalidate_on_option_added'], 10, 2);
        add_action('updated_option', [self::class, 'invalidate_on_option_updated'], 10, 3);
        add_action('delete_option', [self::class, 'capture_option_before_delete']);
        add_action('deleted_option', [self::class, 'invalidate_after_option_delete']);
        add_action('added_term_meta', [self::class, 'invalidate_on_term_meta_change'], 10, 3);
        add_action('updated_term_meta', [self::class, 'invalidate_on_term_meta_change'], 10, 3);
        add_action('deleted_term_meta', [self::class, 'invalidate_on_term_meta_change'], 10, 3);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/image-data', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_image_data'],
            'permission_callback' => function () {
                return current_user_can('edit_teksttv');
            },
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'slot' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/generate', [
            'methods' => 'POST',
            'callback' => [self::class, 'generate_content'],
            'permission_callback' => function () {
                return current_user_can('edit_teksttv');
            },
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'field' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['title', 'body', 'both'],
                ],
                'has_photo' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/slides', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_slides'],
            'permission_callback' => '__return_true',
            'args' => [
                'channel' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Channel slug (e.g., tv1, tv2)',
                    'validate_callback' => [self::class, 'validate_channel'],
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public static function validate_channel(string $value): bool
    {
        return in_array($value, Helpers::channel_slugs(), true);
    }

    public static function get_image_data(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $slot = $request->get_param('slot') ?: null;
        $data = Helpers::get_image_data($id, 'large', $slot);
        if (!$data) {
            return new WP_Error('teksttv_not_found', __('Bijlage niet gevonden.', 'teksttv-wp-plugin'), ['status' => 404]);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Errors are returned as WP_Error so core serializes every failure - ours
     * and its own (expired nonce, invalid param) - into the one {code, message}
     * shape that resources/ts/alpine/postMeta/aiGeneration.ts consumes.
     */
    public static function generate_content(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!Helpers::has_feature('ai_generate')) {
            return new WP_Error(
                'teksttv_ai_disabled',
                __('AI-generatie is uitgeschakeld.', 'teksttv-wp-plugin'),
                ['status' => 403]
            );
        }

        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return new WP_Error(
                'teksttv_ai_unavailable',
                __('AI is niet beschikbaar. Configureer een AI-provider in WordPress instellingen.', 'teksttv-wp-plugin'),
                ['status' => 503]
            );
        }

        $post_id = $request->get_param('post_id');
        $field = $request->get_param('field');

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('teksttv_post_not_found', __('Post niet gevonden.', 'teksttv-wp-plugin'), ['status' => 404]);
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('teksttv_forbidden', __('Onvoldoende rechten.', 'teksttv-wp-plugin'), ['status' => 403]);
        }

        $config = Helpers::get_ai_prompts();

        // Counted last so requests that can only 403/404 do not consume quota.
        if (!AiGenerator::within_rate_limit(get_current_user_id(), $config['rate_limit'])) {
            return new WP_Error(
                'teksttv_rate_limited',
                __('Te veel verzoeken. Probeer het over een minuut opnieuw.', 'teksttv-wp-plugin'),
                ['status' => 429]
            );
        }

        $result = AiGenerator::generate_for_post($post, $field, $config, (bool) $request->get_param('has_photo'));
        if (is_wp_error($result)) {
            return $result;
        }

        // Single-field requests return {content}; 'both' returns {title, body}.
        // Shape consumed by resources/ts/alpine/postMeta/aiGeneration.ts.
        $response = $field !== 'both' ? ['content' => $result['fields'][$field]] : $result['fields'];

        if ($result['warning'] !== '') {
            $response['warning'] = $result['warning'];
        }

        return new WP_REST_Response($response, 200);
    }

    private const SLIDES_CACHE_TTL = 180; // 3 minutes

    public static function get_slides(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $cache_key = 'teksttv_slides_' . $channel;

        $data = get_transient($cache_key);
        if ($data === false) {
            $data = [
                'slides' => SlidesBuilder::build($channel),
                'ticker' => SlidesBuilder::build_ticker($channel),
            ];
            set_transient($cache_key, $data, self::SLIDES_CACHE_TTL);

            // A later supported mutation in this request must be able to
            // invalidate data that was re-cached after an earlier mutation.
            unset(self::$automatically_invalidated_channels[$channel]);
        }

        $response = new WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'public, max-age=' . self::SLIDES_CACHE_TTL);

        return $response;
    }

    /**
     * Invalidate the slides cache for one or all channels.
     */
    public static function invalidate_slides_cache(string $channel = ''): void
    {
        if (!empty($channel)) {
            delete_transient('teksttv_slides_' . $channel);
            return;
        }

        foreach (Helpers::get_channels() as $ch) {
            delete_transient('teksttv_slides_' . $ch['slug']);
        }
    }

    /**
     * Invalidate when a slide-input option is first created.
     *
     * @param mixed $value
     */
    public static function invalidate_on_option_added(string $option, mixed $value): void
    {
        self::invalidate_for_option($option, null, $value);
    }

    /**
     * Invalidate when a stored slide-input option changes.
     *
     * @param mixed $old_value
     * @param mixed $value
     */
    public static function invalidate_on_option_updated(string $option, mixed $old_value, mixed $value): void
    {
        self::invalidate_for_option($option, $old_value, $value);
    }

    /**
     * Record a supported option before WordPress deletes it.
     *
     * The old channel list is needed after deletion to invalidate channels
     * that are no longer configured.
     */
    public static function capture_option_before_delete(string $option): void
    {
        if (!self::supports_option_invalidation($option)) {
            return;
        }

        self::$pending_option_deletions[$option] = $option === 'teksttv_channels' ? get_option($option, []) : null;
    }

    /**
     * Invalidate a supported option only after WordPress has deleted it.
     */
    public static function invalidate_after_option_delete(string $option): void
    {
        if (!array_key_exists($option, self::$pending_option_deletions)) {
            return;
        }

        $old_value = self::$pending_option_deletions[$option];
        unset(self::$pending_option_deletions[$option]);
        self::invalidate_for_option($option, $old_value, null);
    }

    /**
     * Invalidate when the category image is changed through the Term Meta API.
     *
     * @param mixed $meta_id
     */
    public static function invalidate_on_term_meta_change(
        mixed $meta_id,
        int $term_id,
        string $meta_key
    ): void {
        if ($meta_key === CategoryMeta::META_KEY) {
            self::invalidate_automatically();
        }
    }

    /**
     * Route an option mutation to the smallest affected cache scope.
     *
     * @param mixed $old_value
     * @param mixed $value
     */
    private static function invalidate_for_option(string $option, mixed $old_value, mixed $value): void
    {
        $channel = self::channel_from_option($option);
        if ($channel !== null) {
            self::invalidate_automatically($channel);
            return;
        }

        if ($option === 'teksttv_channels') {
            foreach (self::channel_slugs_from_values($old_value, $value) as $channel) {
                self::invalidate_automatically($channel);
            }
            return;
        }

        if (
            isset(Helpers::DURATION_DEFAULTS[$option]) ||
            in_array($option, self::OTHER_GLOBAL_SLIDE_OPTIONS, true)
        ) {
            self::invalidate_automatically();
        }
    }

    /**
     * Whether an option is a supported slide input.
     */
    private static function supports_option_invalidation(string $option): bool
    {
        return self::channel_from_option($option) !== null ||
            $option === 'teksttv_channels' ||
            isset(Helpers::DURATION_DEFAULTS[$option]) ||
            in_array($option, self::OTHER_GLOBAL_SLIDE_OPTIONS, true);
    }

    /**
     * Return the affected channel for a channel-scoped option.
     */
    private static function channel_from_option(string $option): ?string
    {
        foreach (self::CHANNEL_SLIDE_OPTION_PREFIXES as $prefix) {
            if (!str_starts_with($option, $prefix)) {
                continue;
            }

            $channel = sanitize_key(substr($option, strlen($prefix)));
            return $channel !== '' ? $channel : null;
        }

        return null;
    }

    /**
     * Delete each automatically affected channel at most once per request.
     *
     * Plugin form handlers often update multiple related options in one
     * logical save. A cache rebuilt between mutations clears this marker in
     * get_slides(), so a later mutation still invalidates the rebuilt value.
     */
    private static function invalidate_automatically(string $channel = ''): void
    {
        if ($channel === '') {
            foreach (Helpers::channel_slugs() as $configured_channel_slug) {
                self::invalidate_automatically($configured_channel_slug);
            }
            return;
        }

        if (isset(self::$automatically_invalidated_channels[$channel])) {
            return;
        }

        self::$automatically_invalidated_channels[$channel] = true;
        self::invalidate_slides_cache($channel);
    }

    /**
     * Return the union of channel slugs represented before and after a change.
     *
     * An absent or empty channel option means the built-in tv1 fallback.
     *
     * @return list<string>
     */
    private static function channel_slugs_from_values(mixed ...$values): array
    {
        $slugs = [];
        foreach ($values as $channels) {
            $value_slugs = [];
            if (is_array($channels)) {
                foreach ($channels as $channel) {
                    if (!is_array($channel)) {
                        continue;
                    }
                    $slug = sanitize_key($channel['slug'] ?? '');
                    if ($slug !== '') {
                        $value_slugs[] = $slug;
                    }
                }
            }
            $slugs = array_merge($slugs, $value_slugs ?: ['tv1']);
        }

        return array_values(array_unique($slugs));
    }
}
