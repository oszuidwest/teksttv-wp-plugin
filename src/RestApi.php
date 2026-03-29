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
        $channels = Helpers::get_channels();
        foreach ($channels as $channel) {
            if ($channel['slug'] === $value) {
                return true;
            }
        }
        return false;
    }

    public static function get_image_data(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $url = wp_get_attachment_image_url($id, 'large');
        if (!$url) {
            return new WP_REST_Response(['error' => 'Attachment not found'], 404);
        }

        $data = ['url' => $url];

        $caption = wp_get_attachment_caption($id);
        if ($caption) {
            $data['caption'] = $caption;
        }

        $attribution = apply_filters('teksttv_image_attribution', '', $id);
        if ($attribution) {
            $data['attribution'] = $attribution;
        }

        return new WP_REST_Response($data, 200);
    }

    public static function get_slides(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');

        $response = new WP_REST_Response([
            'slides' => SlidesBuilder::build($channel),
            'ticker' => SlidesBuilder::build_ticker($channel),
        ], 200);

        // Cache for 3 minutes
        $response->header('Cache-Control', 'public, max-age=180');

        return $response;
    }
}
