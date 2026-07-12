<?php

namespace TekstTV;

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

    public static function get_image_data(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $slot = $request->get_param('slot') ?: null;
        $data = Helpers::get_image_data($id, 'large', $slot);
        if (!$data) {
            return new WP_REST_Response(['error' => __('Bijlage niet gevonden.', 'teksttv-wp-plugin')], 404);
        }

        return new WP_REST_Response($data, 200);
    }

    public static function generate_content(WP_REST_Request $request): WP_REST_Response
    {
        if (!Helpers::has_feature('ai_generate')) {
            return new WP_REST_Response(
                ['error' => __('AI-generatie is uitgeschakeld.', 'teksttv-wp-plugin')],
                403
            );
        }

        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return new WP_REST_Response(
                ['error' => __('AI is niet beschikbaar. Configureer een AI-provider in WordPress instellingen.', 'teksttv-wp-plugin')],
                503
            );
        }

        $post_id = $request->get_param('post_id');
        $field = $request->get_param('field');
        $has_photo = (bool) $request->get_param('has_photo');

        // Rate limiting per user
        $prompts = Helpers::get_ai_prompts();
        $user_id = get_current_user_id();
        if (!AiGenerator::within_rate_limit($user_id, $prompts['rate_limit'])) {
            return new WP_REST_Response(
                ['error' => __('Te veel verzoeken. Probeer het over een minuut opnieuw.', 'teksttv-wp-plugin')],
                429
            );
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_REST_Response(['error' => __('Onvoldoende rechten.', 'teksttv-wp-plugin')], 403);
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_REST_Response(['error' => __('Post niet gevonden.', 'teksttv-wp-plugin')], 404);
        }

        // Clean post content for AI input
        $post_text = AiGenerator::prepare_content($post->post_content);
        $post_title = $post->post_title;

        if (empty($post_text) && empty($post_title)) {
            return new WP_REST_Response(['error' => __('Post heeft geen content om samen te vatten.', 'teksttv-wp-plugin')], 422);
        }

        $min_words = $prompts['min_input_words'];
        if ($min_words > 0) {
            $word_count = Helpers::count_words($post_text);
            if ($word_count < $min_words) {
                return new WP_REST_Response(
                    // translators: %1$d: actual word count, %2$d: minimum required words
                    ['error' => sprintf(__('Artikel bevat te weinig tekst (%1$d woorden, minimaal %2$d vereist).', 'teksttv-wp-plugin'), $word_count, $min_words)],
                    422
                );
            }
        }

        $fields_to_generate = $field === 'both' ? ['title', 'body'] : [$field];
        $response_data = [];
        $warnings = [];

        foreach ($fields_to_generate as $current_field) {
            $result = AiGenerator::generate_single_field($current_field, $post_title, $post_text, $has_photo);
            if (is_wp_error($result)) {
                return new WP_REST_Response(
                    // translators: %s: error message from AI provider
                    ['error' => sprintf(__('AI-generatie mislukt: %s', 'teksttv-wp-plugin'), $result->get_error_message())],
                    500
                );
            }
            $response_data[$current_field] = $result['content'];
            if (!empty($result['warning'])) {
                $warnings[] = $result['warning'];
            }
        }

        // Save AI output as post meta for audit trail (before prefix, for clean comparison)
        if (isset($response_data['title'])) {
            update_post_meta($post_id, '_teksttv_ai_title', $response_data['title']);
        }
        if (isset($response_data['body'])) {
            update_post_meta($post_id, '_teksttv_ai_body', $response_data['body']);
        }

        // Apply region prefix to body (after save, so audit trail stays clean)
        if (isset($response_data['body'])) {
            $region_prefix = AiGenerator::get_region_prefix($post_id);
            if (!empty($region_prefix)) {
                $response_data['body'] = '<p>' . esc_html($region_prefix) . ' - ' . ltrim(preg_replace('/^<p>/', '', $response_data['body']));
            }
        }

        // For single field requests, keep backward-compatible response
        $response = $field !== 'both' ? ['content' => $response_data[$field]] : $response_data;

        if (!empty($warnings)) {
            $response['warning'] = implode(' ', $warnings);
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
