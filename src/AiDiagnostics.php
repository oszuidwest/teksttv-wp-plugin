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
                $value = $context[$key];
                $record[$key] = is_string($value) ? self::redact_credentials($value) : $value;
            }
        }
        foreach (self::CONTENT_KEYS as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $record[$key] = '[redacted]';
            if (!empty($config['diagnostics_content']) && is_scalar($context[$key])) {
                $record[$key] = mb_substr(self::redact_credentials((string) $context[$key]), 0, 2000);
            }
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- explicit opt-in diagnostic sink.
        error_log('TekstTV AI diagnostic ' . wp_json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
    public static function model_preference(array $config): array
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

    private static function redact_credentials(string $value): string
    {
        $value = (string) preg_replace(
            '/\b(authorization|proxy-authorization|cookie|set-cookie|x-wp-nonce)\b\s*:\s*[^\r\n]*/i',
            '$1=[credential-redacted]',
            $value
        );
        $value = (string) preg_replace(
            '/\b(api[_-]?key|access[_-]?token|refresh[_-]?token|client[_-]?secret|nonce|password|passphrase|private[_-]?key|secret|token)\b\s*[:=]\s*(?:Bearer\s+)?(?:"[^"]*"|\'[^\']*\'|\S+)/i',
            '$1=[credential-redacted]',
            $value
        );
        $value = (string) preg_replace('/\bBearer\s+\S+/i', 'Bearer [credential-redacted]', $value);
        $value = (string) preg_replace(
            '/\b(?:sk-[A-Za-z0-9_-]{8,}|AIza[A-Za-z0-9_-]{20,}|AKIA[A-Z0-9]{16}|eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\b/',
            '[credential-redacted]',
            $value
        );

        return $value;
    }
}
