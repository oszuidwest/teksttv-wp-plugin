<?php

namespace TekstTV;

/** Safe, opt-in structured diagnostics for AI generation. */
class AiDiagnostics
{
    private const OPERATIONAL_KEYS = [
        'provider',
        'model',
        'field',
        'word_limit',
        'title_char_limit',
        'min_input_words',
        'max_retries',
        'attempt',
        'duration_ms',
        'response_status',
        'validation',
    ];

    private const CONTENT_KEYS = ['article_text', 'prompt', 'generated_output'];

    /** @param array<string, mixed> $config */
    public static function enabled(array $config): bool
    {
        return !empty($config['diagnostics']);
    }

    public static function correlation_id(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     */
    public static function log(array $config, string $event, string $correlation_id, array $context = []): void
    {
        if (!self::enabled($config)) {
            return;
        }

        $record = [
            'component' => 'teksttv_ai',
            'event' => $event,
            'correlation_id' => $correlation_id,
        ];
        foreach (self::OPERATIONAL_KEYS as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                $record[$key] = $context[$key];
            }
        }
        foreach (self::CONTENT_KEYS as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $record[$key] = !empty($config['diagnostics_content']) && is_scalar($context[$key]) ? self::safe_content((string) $context[$key]) : '[redacted]';
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- explicit opt-in diagnostic sink.
        error_log('TekstTV AI diagnostic ' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $config */
    public static function reference_message(array $config, string $message, string $correlation_id): string
    {
        return self::enabled($config) ? $message . ' Referentie: ' . $correlation_id . '.' : $message;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{provider: string, model: string}
     */
    public static function selected_model(array $config): array
    {
        if (!empty($config['model']) && str_contains((string) $config['model'], '/')) {
            [$provider, $model] = explode('/', (string) $config['model'], 2);
            return ['provider' => $provider, 'model' => $model];
        }

        return [
            'provider' => !empty($config['provider']) ? (string) $config['provider'] : 'auto',
            'model' => 'auto',
        ];
    }

    private static function safe_content(string $value): string
    {
        $value = (string) preg_replace('/\bsk-[A-Za-z0-9_-]{8,}\b/', '[credential-redacted]', $value);
        $value = (string) preg_replace(
            '/\b(api[_-]?key|authorization|cookie|nonce|password|secret)\b\s*[:=]\s*(?:Bearer\s+)?\S+/i',
            '$1=[credential-redacted]',
            $value
        );

        return mb_substr($value, 0, 2000);
    }
}
