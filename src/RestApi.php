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
        $config = Helpers::get_ai_prompts();
        $correlation_id = wp_generate_uuid4();

        if (!Helpers::has_feature('ai_generate')) {
            return self::generation_error(
                'teksttv_ai_disabled',
                'AI-generatie is uitgeschakeld.',
                403,
                $config,
                $correlation_id
            );
        }

        if (!Helpers::ai_supported()) {
            return self::generation_error(
                'teksttv_ai_unavailable',
                'AI is niet beschikbaar. Configureer een AI-provider in WordPress instellingen.',
                503,
                $config,
                $correlation_id
            );
        }

        $post_id = $request->get_param('post_id');
        $field = $request->get_param('field');

        // The view only offers body-only generation then, but stale editor tabs
        // can still send title/both; without this gate that would persist an
        // orphaned _teksttv_ai_title that skews the AI-audit page.
        if ($field !== 'body' && !Helpers::has_feature('custom_title')) {
            return self::generation_error(
                'teksttv_custom_title_disabled',
                'Kop-generatie is uitgeschakeld.',
                403,
                $config,
                $correlation_id,
                ['field' => $field]
            );
        }

        $post = get_post($post_id);
        if (!$post) {
            return self::generation_error(
                'teksttv_post_not_found',
                'Post niet gevonden.',
                404,
                $config,
                $correlation_id,
                ['field' => $field]
            );
        }

        if (!current_user_can('edit_post', $post_id)) {
            return self::generation_error(
                'teksttv_forbidden',
                'Onvoldoende rechten.',
                403,
                $config,
                $correlation_id,
                ['field' => $field]
            );
        }

        AiDiagnostics::log($config, 'request_started', $correlation_id, array_merge(
            AiDiagnostics::model_preference($config),
            [
                'field' => $field,
                'word_limit' => $config['word_limit'],
                'title_char_limit' => $config['title_char_limit'],
                'min_input_words' => $config['min_input_words'],
                'max_retries' => $config['max_retries'],
                'article_text' => $post->post_content,
            ]
        ));

        // Counted last so requests that can only 403/404 do not consume quota.
        if (!AiGenerator::within_rate_limit(get_current_user_id(), $config['rate_limit'])) {
            return self::generation_error(
                'teksttv_rate_limited',
                'Te veel verzoeken. Probeer het over een minuut opnieuw.',
                429,
                $config,
                $correlation_id,
                ['field' => $field]
            );
        }

        $result = AiGenerator::generate_for_post(
            $post,
            $field,
            $config,
            (bool) $request->get_param('has_photo'),
            $correlation_id
        );
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            return self::generation_error(
                $result->get_error_code(),
                $result->get_error_message(),
                is_array($error_data) ? (int) ($error_data['status'] ?? 500) : 500,
                $config,
                $correlation_id,
                ['field' => $field]
            );
        }

        // Single-field requests return {content}; 'both' returns {title, body}.
        // Shape consumed by resources/ts/alpine/postMeta/aiGeneration.ts.
        $response = $field !== 'both' ? ['content' => $result['fields'][$field]] : $result['fields'];

        if ($result['warning'] !== '') {
            $response['warning'] = $result['warning'];
        }

        AiDiagnostics::log($config, 'request_finished', $correlation_id, [
            'field' => $field,
            'response_status' => 200,
            'validation' => $result['warning'] === '' ? 'valid' : 'warning',
        ]);

        return new WP_REST_Response($response, 200);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     */
    private static function generation_error(
        string $code,
        string $message,
        int $status,
        array $config,
        string $correlation_id,
        array $context = []
    ): WP_Error {
        AiDiagnostics::log($config, 'request_failed', $correlation_id, array_merge($context, [
            'response_status' => $status,
            'validation' => $code,
        ]));

        return new WP_Error(
            $code,
            AiDiagnostics::reference_message($config, $message, $correlation_id),
            ['status' => $status]
        );
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
