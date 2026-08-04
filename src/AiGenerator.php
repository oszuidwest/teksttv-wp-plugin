<?php

namespace TekstTV;

/**
 * AI content generation for TekstTV: prompt construction, WP AI Client calls,
 * output validation, rate limiting, audit-trail meta persistence
 * (_teksttv_ai_title / _teksttv_ai_body), and region prefixing.
 *
 * @phpstan-import-type AiConfig from Helpers
 */
class AiGenerator
{
    private const REQUESTS_PER_MINUTE = 10;
    private const MAX_TOKENS = 2048;

    /**
     * Whether the current WordPress AI configuration can satisfy the same
     * requirements used for TekstTV generation requests.
     *
     * wp_supports_ai() is only an environment-level switch and defaults to
     * true even when no provider is configured. The prompt-level support
     * check also verifies the registered providers, credentials, models, and
     * configured generation parameters.
     *
     * @param AiConfig $config Config from Helpers::get_ai_prompts().
     */
    public static function supports_text_generation(array $config): bool
    {
        if (!function_exists('wp_supports_ai') || !wp_supports_ai()) {
            return false;
        }

        try {
            $builder = self::configure_prompt_builder('Capability check', $config['system'], $config);

            return (bool) $builder->is_supported_for_text_generation();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Count one request against the per-user, per-minute AI generation limit.
     *
     * With a persistent object cache, wp_cache_incr() is atomic and avoids the
     * read-then-write race where concurrent requests both pass the check before
     * either writes back. Without one, fall back to a transient-backed counter
     * (persistent but not atomic - acceptable for editor cost control).
     *
     * Counter persistence failures fail closed so uncounted requests cannot
     * bypass the cost-control boundary.
     *
     * @return bool True when the request is allowed and has been counted.
     */
    public static function within_rate_limit(int $user_id, int $rate_limit = self::REQUESTS_PER_MINUTE): bool
    {
        // Fixed calendar-minute buckets: rewrites can touch an entry's TTL but
        // never the active window, because the next minute uses a new key.
        // TTLs run to the end of the bucket's own minute.
        $now = time();
        $key = 'teksttv_ai_rate_' . $user_id . '_' . intdiv($now, MINUTE_IN_SECONDS);
        $ttl = MINUTE_IN_SECONDS - ($now % MINUTE_IN_SECONDS);

        if (wp_using_ext_object_cache()) {
            $group = 'teksttv_ai_rate';
            // add() seeds the counter only if absent; incr() then bumps it atomically.
            wp_cache_add($key, 0, $group, $ttl);
            $count = wp_cache_incr($key, 1, $group);
            if ($count === false) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('TekstTV AI rate limiter: wp_cache_incr() failed, rejecting uncounted request.');
                return false;
            }
            return $count <= $rate_limit;
        }

        $count = (int) get_transient($key);
        if ($count >= $rate_limit) {
            return false;
        }
        if (!set_transient($key, $count + 1, $ttl)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV AI rate limiter: set_transient() failed, rejecting uncounted request.');
            return false;
        }
        return true;
    }

    /**
     * Generate the requested field(s) for a post, apply the region prefix to
     * the body, and persist the audit-trail meta.
     *
     * Validation errors and provider failures are returned as WP_Error with a
     * `status` entry in the error data for HTTP mapping.
     *
     * @param string $field 'title', 'body', or 'both'.
     * @param AiConfig $config Config from Helpers::get_ai_prompts().
     * @return array{fields: array<string, string>, warning: string}|\WP_Error
     */
    public static function generate_for_post(\WP_Post $post, string $field, array $config, bool $has_photo = false)
    {
        if (!in_array($field, ['title', 'body', 'both'], true)) {
            return new \WP_Error(
                'teksttv_invalid_field',
                'Ongeldig veld voor AI-generatie.',
                ['status' => 400]
            );
        }

        $post_text = self::prepare_content($post->post_content);
        $post_title = $post->post_title;

        if (empty($post_text) && empty($post_title)) {
            return new \WP_Error(
                'teksttv_no_content',
                'Post heeft geen content om samen te vatten.',
                ['status' => 422]
            );
        }

        $min_words = $config['min_input_words'];
        if ($min_words > 0) {
            $word_count = Helpers::count_words($post_text);
            if ($word_count < $min_words) {
                return new \WP_Error(
                    'teksttv_input_too_short',
                    sprintf('Artikel bevat te weinig tekst (%1$d woorden, minimaal %2$d vereist).', $word_count, $min_words),
                    ['status' => 422]
                );
            }
        }

        $fields = [];
        $warnings = [];
        foreach ($field === 'both' ? ['title', 'body'] : [$field] as $current_field) {
            $result = self::generate_single_field($current_field, $post_title, $post_text, $config, $has_photo);
            if (is_wp_error($result)) {
                return new \WP_Error(
                    'teksttv_generation_failed',
                    sprintf('AI-generatie mislukt: %s', $result->get_error_message()),
                    ['status' => 500]
                );
            }
            $fields[$current_field] = $result['content'];
            if (!empty($result['warning'])) {
                $warnings[] = $result['warning'];
            }
        }

        if (isset($fields['body'])) {
            $region_prefix = self::get_region_prefix($post->ID, $config['region_taxonomy']);
            if (!empty($region_prefix)) {
                $fields['body'] = '<p>' . esc_html($region_prefix) . ' - ' . ltrim(preg_replace('/^<p>/', '', $fields['body']));
            }
        }

        // Persist after all transforms: the audit baseline must match exactly
        // what the editor received.
        foreach ($fields as $key => $value) {
            update_post_meta($post->ID, '_teksttv_ai_' . $key, wp_slash($value));
        }

        return ['fields' => $fields, 'warning' => implode(' ', $warnings)];
    }

    /**
     * Generate a single field (title or body) using the WP AI Client.
     *
     * @param AiConfig $config Config from Helpers::get_ai_prompts().
     * @return array{content: string, warning?: string}|\WP_Error
     */
    public static function generate_single_field(string $field, string $post_title, string $post_text, array $config, bool $has_photo = false)
    {
        [$user_prompt, $system] = self::build_ai_prompt($field, $post_title, $post_text, $config, $has_photo);

        $result = self::call_ai($user_prompt, $system, $config);
        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('TekstTV AI generation error (field: %s): %s', $field, $result->get_error_message()));
            return $result;
        }

        $content = trim($result);
        if ($content === '') {
            return new \WP_Error(
                'teksttv_empty_output',
                'AI gaf een leeg antwoord terug. Probeer het opnieuw.'
            );
        }

        $warning = self::validate_ai_output($field, $content, $config, $has_photo);

        if ($field === 'body') {
            $content = wpautop($content);
        }

        $response = ['content' => $content];
        if (!empty($warning)) {
            $response['warning'] = $warning;
        }

        return $response;
    }

    /**
     * Build the system instruction and user prompt for AI generation.
     *
     * @param AiConfig $config
     * @return array{0: string, 1: string} [user_prompt, system]
     */
    public static function build_ai_prompt(string $field, string $post_title, string $post_text, array $config, bool $has_photo = false): array
    {
        if ($field === 'title') {
            $tokens = ['{{chars}}' => (string) $config['title_char_limit']];
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                strtr($config['prompt_title'], $tokens),
                $post_title,
                mb_substr($post_text, 0, 2000)
            );
            $system = strtr($config['system'], $tokens) . sprintf(
                ' De kop mag maximaal %d tekens lang zijn.',
                $config['title_char_limit']
            );
        } else {
            $word_limit = self::effective_word_limit($config, $has_photo);
            $tokens = ['{{words}}' => (string) $word_limit];
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                strtr($config['prompt_body'], $tokens),
                $post_title,
                mb_substr($post_text, 0, 4000)
            );
            $system = strtr($config['system'], $tokens) . sprintf(
                ' De samenvatting moet tussen de %d en %d woorden zijn.',
                (int) ceil($word_limit * 0.2),
                $word_limit
            );
        }

        return [$user_prompt, $system];
    }

    /**
     * Resolve the applicable word limit, using the photo-specific limit when a
     * photo accompanies the text.
     *
     * @param AiConfig $config
     */
    private static function effective_word_limit(array $config, bool $has_photo): int
    {
        return $has_photo ? $config['word_limit_photo'] : $config['word_limit'];
    }

    /**
     * Call the WP AI Client with configured model/provider settings.
     *
     * @param AiConfig $config
     * @return string|\WP_Error
     */
    private static function call_ai(string $user_prompt, string $system, array $config)
    {
        return self::configure_prompt_builder($user_prompt, $system, $config)->generate_text();
    }

    /**
     * Build the prompt builder with the generation requirements shared by
     * capability checks and real requests.
     *
     * @param AiConfig $config
     * @return object
     */
    private static function configure_prompt_builder(string $user_prompt, string $system, array $config)
    {
        $builder = wp_ai_client_prompt($user_prompt)
            ->using_system_instruction($system)
            ->using_max_tokens(self::MAX_TOKENS);

        $model_setting = $config['model'];
        $provider_setting = $config['provider'];
        if (!empty($model_setting) && str_contains($model_setting, '/')) {
            [$provider_id, $model_id] = explode('/', $model_setting, 2);
            $builder = $builder->using_model_preference([$provider_id, $model_id]);
        } elseif (!empty($provider_setting)) {
            $builder = $builder->using_provider($provider_setting);
        }

        return $builder;
    }

    /**
     * Validate AI output against length constraints.
     *
     * @param AiConfig $config
     * @return string '' when valid, otherwise a user-facing warning. Retry
     *                policy belongs to the caller, not here.
     */
    public static function validate_ai_output(string $field, string $content, array $config, bool $has_photo = false): string
    {
        if ($field === 'title') {
            if (mb_strlen($content) <= $config['title_char_limit']) {
                return '';
            }

            return sprintf(
                'Kop is %1$d tekens (limiet: %2$d). Controleer en kort eventueel handmatig in.',
                mb_strlen($content),
                $config['title_char_limit']
            );
        }

        $word_limit = self::effective_word_limit($config, $has_photo);
        $count = Helpers::count_words($content);
        $min_words = (int) ceil($word_limit * 0.2);
        if ($count >= $min_words && $count <= $word_limit) {
            return '';
        }

        return sprintf(
            'Tekst bevat %1$d woorden (limiet: %2$d-%3$d). Controleer en pas eventueel handmatig aan.',
            $count,
            $min_words,
            $word_limit
        );
    }

    /**
     * Prepare post content for AI input by cleaning HTML structure.
     */
    public static function prepare_content(string $html): string
    {
        // wp_strip_all_tags() below drops script/style bodies but not noscript,
        // whose fallback text would otherwise reach the model as article prose.
        $text = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/si', '', $html);

        // Keep block boundaries as newlines so paragraphs do not run together.
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Build a region prefix from the post's terms in the configured taxonomy.
     */
    public static function get_region_prefix(int $post_id, string $taxonomy): string
    {
        if (empty($taxonomy)) {
            return '';
        }

        if (!taxonomy_exists($taxonomy)) {
            // A configured but missing taxonomy is a config error (e.g. the
            // plugin registering it was deactivated); without a log the prefix
            // just silently stops appearing.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('TekstTV region prefix: configured taxonomy "%s" does not exist.', $taxonomy));
            return '';
        }

        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
        if (is_wp_error($terms)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('TekstTV region prefix: term lookup failed for post %d: %s', $post_id, $terms->get_error_message()));
            return '';
        }
        if (empty($terms)) {
            return '';
        }

        return implode(' / ', array_map('mb_strtoupper', $terms));
    }
}
