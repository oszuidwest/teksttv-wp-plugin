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
        $data = Helpers::get_image_data($id);
        if (!$data) {
            return new WP_REST_Response(['error' => __('Bijlage niet gevonden.', 'teksttv')], 404);
        }

        return new WP_REST_Response($data, 200);
    }

    public static function generate_content(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return new WP_REST_Response(
                ['error' => __('AI is niet beschikbaar. Configureer een AI-provider in WordPress instellingen.', 'teksttv')],
                503
            );
        }

        $post_id = $request->get_param('post_id');
        $field = $request->get_param('field');

        // Rate limiting per user
        $prompts_config = Helpers::get_ai_prompts();
        $rate_limit = $prompts_config['rate_limit'];
        $user_id = get_current_user_id();
        $rate_key = 'teksttv_ai_rate_' . $user_id;
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= $rate_limit) {
            return new WP_REST_Response(
                ['error' => __('Te veel verzoeken. Probeer het over een minuut opnieuw.', 'teksttv')],
                429
            );
        }
        set_transient($rate_key, $rate_count + 1, 60);

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_REST_Response(['error' => __('Onvoldoende rechten.', 'teksttv')], 403);
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_REST_Response(['error' => __('Post niet gevonden.', 'teksttv')], 404);
        }

        // Clean post content for AI input
        $post_text = self::prepare_content($post->post_content);
        $post_title = $post->post_title;

        if (empty($post_text) && empty($post_title)) {
            return new WP_REST_Response(['error' => __('Post heeft geen content om samen te vatten.', 'teksttv')], 422);
        }

        $prompts = Helpers::get_ai_prompts();
        $min_words = $prompts['min_input_words'];
        if ($min_words > 0) {
            $word_count = Helpers::count_words($post_text);
            if ($word_count < $min_words) {
                return new WP_REST_Response(
                    // translators: %1$d: actual word count, %2$d: minimum required words
                    ['error' => sprintf(__('Artikel bevat te weinig tekst (%1$d woorden, minimaal %2$d vereist).', 'teksttv'), $word_count, $min_words)],
                    422
                );
            }
        }

        $fields_to_generate = $field === 'both' ? ['title', 'body'] : [$field];
        $response_data = [];
        $warnings = [];

        foreach ($fields_to_generate as $current_field) {
            $result = self::generate_single_field($current_field, $post_title, $post_text);
            if (is_wp_error($result)) {
                return new WP_REST_Response(
                    // translators: %s: error message from AI provider
                    ['error' => sprintf(__('AI-generatie mislukt: %s', 'teksttv'), $result->get_error_message())],
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
            $region_prefix = self::get_region_prefix($post_id);
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

    /**
     * Generate a single field (title or body) using the WP AI Client.
     *
     * @return array{content: string, warning?: string}|\WP_Error
     */
    public static function generate_single_field(string $field, string $post_title, string $post_text)
    {
        $prompts = Helpers::get_ai_prompts();
        [$user_prompt, $system] = self::build_ai_prompt($field, $post_title, $post_text, $prompts);

        $last_content = '';
        $warning = '';

        for ($attempt = 1; $attempt <= $prompts['max_retries']; $attempt++) {
            $result = self::call_ai($user_prompt, $system, $prompts);

            if (is_wp_error($result)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('TekstTV AI generation error: ' . $result->get_error_message());
                return $result;
            }

            $last_content = trim($result);
            $warning = self::validate_ai_output($field, $last_content, $prompts, $attempt === $prompts['max_retries']);

            if (empty($warning)) {
                break;
            }
        }

        if ($field === 'body') {
            $last_content = wpautop($last_content);
        }

        $response = ['content' => $last_content];
        if (!empty($warning)) {
            $response['warning'] = $warning;
        }

        return $response;
    }

    /**
     * Build the system instruction and user prompt for AI generation.
     *
     * @param array<string, mixed> $prompts
     * @return array{0: string, 1: string} [user_prompt, system]
     */
    public static function build_ai_prompt(string $field, string $post_title, string $post_text, array $prompts): array
    {
        if ($field === 'title') {
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                $prompts['prompt_title'],
                $post_title,
                mb_substr($post_text, 0, 2000)
            );
            $system = $prompts['system'] . sprintf(
                ' De kop mag maximaal %d tekens lang zijn.',
                $prompts['title_char_limit']
            );
        } else {
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                $prompts['prompt_body'],
                $post_title,
                mb_substr($post_text, 0, 4000)
            );
            $system = $prompts['system'] . sprintf(
                ' De samenvatting moet tussen de %d en %d woorden zijn.',
                (int) ceil($prompts['word_limit'] * 0.2),
                $prompts['word_limit']
            );
        }

        return [$user_prompt, $system];
    }

    /**
     * Call the WP AI Client with configured model/provider settings.
     *
     * @param array<string, mixed> $prompts
     * @return string|\WP_Error
     */
    public static function call_ai(string $user_prompt, string $system, array $prompts)
    {
        $builder = wp_ai_client_prompt($user_prompt)
            ->using_system_instruction($system)
            ->using_max_tokens($prompts['max_tokens']);

        if ($prompts['temperature'] !== '') {
            $builder = $builder->using_temperature((float) $prompts['temperature']);
        }
        if ($prompts['top_p'] !== '') {
            $builder = $builder->using_top_p((float) $prompts['top_p']);
        }

        $model_setting = $prompts['model'];
        $provider_setting = $prompts['provider'];
        if (!empty($model_setting) && str_contains($model_setting, '/')) {
            [$provider_id, $model_id] = explode('/', $model_setting, 2);
            $builder = $builder->using_model_preference([$provider_id, $model_id]);
        } elseif (!empty($provider_setting)) {
            $builder = $builder->using_provider($provider_setting);
        }

        return $builder->generate_text();
    }

    /**
     * Validate AI output against length constraints.
     *
     * @param array<string, mixed> $prompts
     * @return string Warning message if invalid, empty string if valid.
     */
    public static function validate_ai_output(string $field, string $content, array $prompts, bool $is_last_attempt): string
    {
        if ($field === 'title') {
            if (mb_strlen($content) <= $prompts['title_char_limit']) {
                return '';
            }
            if ($is_last_attempt) {
                // translators: %1$d: actual character count, %2$d: maximum allowed characters
                return sprintf(
                    __('Kop is %1$d tekens (limiet: %2$d). Controleer en kort eventueel handmatig in.', 'teksttv'),
                    mb_strlen($content),
                    $prompts['title_char_limit']
                );
            }
        } else {
            $count = Helpers::count_words($content);
            $min_words = (int) ceil($prompts['word_limit'] * 0.2);
            if ($count >= $min_words && $count <= $prompts['word_limit']) {
                return '';
            }
            if ($is_last_attempt) {
                // translators: %1$d: actual word count, %2$d: minimum words, %3$d: maximum words
                return sprintf(
                    __('Tekst bevat %1$d woorden (limiet: %2$d-%3$d). Controleer en pas eventueel handmatig aan.', 'teksttv'),
                    $count,
                    $min_words,
                    $prompts['word_limit']
                );
            }
        }

        return 'retry';
    }

    /**
     * Prepare post content for AI input by cleaning HTML structure.
     */
    public static function prepare_content(string $html): string
    {
        // Remove script, style, and noscript tags with their content
        $text = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/si', '', $html);

        // Convert block elements to newlines for readability
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Strip remaining HTML tags
        $text = wp_strip_all_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Build a region prefix from the post's taxonomy terms.
     */
    public static function get_region_prefix(int $post_id): string
    {
        $prompts = Helpers::get_ai_prompts();
        $taxonomy = $prompts['region_taxonomy'];

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return '';
        }

        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        return implode(' / ', array_map('mb_strtoupper', $terms));
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
