<?php

namespace TekstTV;

use WP_Error;
use WP_REST_Response;
use WP_REST_Request;

class RestApi
{
    private const NAMESPACE = 'teksttv/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
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
}
