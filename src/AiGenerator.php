<?php

namespace TekstTV;

/**
 * AI content generation for TekstTV: prompt construction, WP AI Client calls,
 * output validation, and rate limiting.
 */
class AiGenerator
{
    /**
     * Count one request against the per-user, per-minute AI generation limit.
     *
     * With a persistent object cache, wp_cache_incr() is atomic and avoids the
     * read-then-write race where concurrent requests both pass the check before
     * either writes back. Without one, fall back to a transient-backed counter
     * (persistent but not atomic — acceptable for editor cost control).
     *
     * @return bool True when the request is allowed (and has been counted).
     */
    public static function within_rate_limit(int $user_id, int $rate_limit): bool
    {
        $key = 'teksttv_ai_rate_' . $user_id;

        if (wp_using_ext_object_cache()) {
            $group = 'teksttv_ai_rate';
            // add() seeds the counter only if absent, so the TTL is set once per
            // window; incr() then bumps it atomically.
            wp_cache_add($key, 0, $group, MINUTE_IN_SECONDS);
            $count = wp_cache_incr($key, 1, $group);
            if ($count === false) {
                // Cache backend hiccup: fail open rather than block the editor.
                return true;
            }
            return $count <= $rate_limit;
        }

        $count = (int) get_transient($key);
        if ($count >= $rate_limit) {
            return false;
        }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Generate a single field (title or body) using the WP AI Client.
     *
     * @return array{content: string, warning?: string}|\WP_Error
     */
    public static function generate_single_field(string $field, string $post_title, string $post_text, bool $has_photo = false)
    {
        $prompts = Helpers::get_ai_prompts();
        [$user_prompt, $system] = self::build_ai_prompt($field, $post_title, $post_text, $prompts, $has_photo);

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
            $warning = self::validate_ai_output($field, $last_content, $prompts, $attempt === $prompts['max_retries'], $has_photo);

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
    public static function build_ai_prompt(string $field, string $post_title, string $post_text, array $prompts, bool $has_photo = false): array
    {
        if ($field === 'title') {
            $tokens = ['{{chars}}' => (string) $prompts['title_char_limit']];
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                strtr($prompts['prompt_title'], $tokens),
                $post_title,
                mb_substr($post_text, 0, 2000)
            );
            $system = strtr($prompts['system'], $tokens) . sprintf(
                ' De kop mag maximaal %d tekens lang zijn.',
                $prompts['title_char_limit']
            );
        } else {
            $word_limit = self::effective_word_limit($prompts, $has_photo);
            $tokens = ['{{words}}' => (string) $word_limit];
            $user_prompt = sprintf(
                "%s\n\nTitel: %s\n\n%s",
                strtr($prompts['prompt_body'], $tokens),
                $post_title,
                mb_substr($post_text, 0, 4000)
            );
            $system = strtr($prompts['system'], $tokens) . sprintf(
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
     * @param array<string, mixed> $prompts
     */
    private static function effective_word_limit(array $prompts, bool $has_photo): int
    {
        return $has_photo ? (int) $prompts['word_limit_photo'] : (int) $prompts['word_limit'];
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
    public static function validate_ai_output(string $field, string $content, array $prompts, bool $is_last_attempt, bool $has_photo = false): string
    {
        if ($field === 'title') {
            if (mb_strlen($content) <= $prompts['title_char_limit']) {
                return '';
            }
            if ($is_last_attempt) {
                return sprintf(
                    /* translators: %1$d: actual character count, %2$d: maximum allowed characters */
                    __('Kop is %1$d tekens (limiet: %2$d). Controleer en kort eventueel handmatig in.', 'teksttv-wp-plugin'),
                    mb_strlen($content),
                    $prompts['title_char_limit']
                );
            }
        } else {
            $word_limit = self::effective_word_limit($prompts, $has_photo);
            $count = Helpers::count_words($content);
            $min_words = (int) ceil($word_limit * 0.2);
            if ($count >= $min_words && $count <= $word_limit) {
                return '';
            }
            if ($is_last_attempt) {
                return sprintf(
                    /* translators: %1$d: actual word count, %2$d: minimum words, %3$d: maximum words */
                    __('Tekst bevat %1$d woorden (limiet: %2$d-%3$d). Controleer en pas eventueel handmatig aan.', 'teksttv-wp-plugin'),
                    $count,
                    $min_words,
                    $word_limit
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
}
