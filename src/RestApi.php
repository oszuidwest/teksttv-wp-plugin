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
                'source_title' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'source_content' => [
                    'required' => true,
                    'type' => 'string',
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
            return new WP_Error('teksttv_not_found', 'Bijlage niet gevonden.', ['status' => 404]);
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
                'AI-generatie is uitgeschakeld.',
                ['status' => 403]
            );
        }

        if (!Helpers::ai_supported()) {
            return new WP_Error(
                'teksttv_ai_unavailable',
                'AI is niet beschikbaar. Configureer een AI-provider in WordPress instellingen.',
                ['status' => 503]
            );
        }

        $post_id = $request->get_param('post_id');
        $field = $request->get_param('field');

        // The view only offers body-only generation then, but stale editor tabs
        // can still send title/both; without this gate that would persist an
        // orphaned _teksttv_ai_title that skews the AI-audit page.
        if ($field !== 'body' && !Helpers::has_feature('custom_title')) {
            return new WP_Error(
                'teksttv_custom_title_disabled',
                'Kop-generatie is uitgeschakeld.',
                ['status' => 403]
            );
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('teksttv_post_not_found', 'Post niet gevonden.', ['status' => 404]);
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('teksttv_forbidden', 'Onvoldoende rechten.', ['status' => 403]);
        }

        $source_title = $request->get_param('source_title');
        $source_content = $request->get_param('source_content');
        if (!is_string($source_title) || !is_string($source_content)) {
            return new WP_Error(
                'teksttv_editor_state_unavailable',
                'De actuele editorinhoud ontbreekt. Genereren is gestopt.',
                ['status' => 400]
            );
        }

        $source_post = clone $post;
        $source_post->post_title = sanitize_text_field($source_title);
        $source_post->post_content = wp_kses_post($source_content);

        // Counted last so requests that can only 403/404 do not consume quota.
        $provider_requests = $field === 'both' ? 2 : 1;
        if (!AiGenerator::within_rate_limit(get_current_user_id(), $provider_requests)) {
            return new WP_Error(
                'teksttv_rate_limited',
                sprintf('Te veel verzoeken (maximaal %d per minuut). Probeer het over een minuut opnieuw.', AiGenerator::REQUESTS_PER_MINUTE),
                ['status' => 429]
            );
        }

        $result = AiGenerator::generate_for_post(
            $source_post,
            $field,
            Helpers::get_ai_prompts(),
            (bool) $request->get_param('has_photo')
        );
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

    public static function get_slides(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');

        return new WP_REST_Response([
            'slides' => SlidesBuilder::build($channel),
            'ticker' => SlidesBuilder::build_ticker($channel),
        ], 200);
    }
}
