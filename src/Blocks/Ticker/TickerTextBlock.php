<?php

namespace TekstTV\Blocks\Ticker;

use TekstTV\BlockRegistry;
use TekstTV\Helpers;

final class TickerTextBlock
{
    public static function register(): void
    {
        BlockRegistry::register('ticker_text', [
            'label' => 'Tekst',
            'icon' => 'editor-textcolor',
            'color' => '#e65100',
            'context' => 'ticker',
            'render' => [self::class, 'render_fields'],
            'save' => [self::class, 'save'],
            'build' => [self::class, 'build'],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function render_fields(int|string $index, array $item, string $prefix): void
    {
        $message = $item['message'] ?? '';
        $message_id = Helpers::field_id($prefix, $index, 'message');

        ?>
        <div class="teksttv-field-grid">
            <div class="teksttv-field teksttv-field--full">
                <label for="<?php echo esc_attr($message_id); ?>"><?php echo esc_html('Bericht'); ?></label>
                <input type="text" id="<?php echo esc_attr($message_id); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][message]" value="<?php echo esc_attr((string) $message); ?>" class="large-text" placeholder="<?php echo esc_attr('Tickertekst…'); ?>" autocomplete="off" data-summary />
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save(array $raw): ?array
    {
        $message = sanitize_text_field($raw['message'] ?? '');
        if (empty($message)) {
            return null;
        }

        return ['message' => $message];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{message: string}>
     */
    public static function build(array $item, string $channel): array
    {
        $text = $item['message'] ?? '';
        if (empty($text)) {
            return [];
        }

        return [['message' => $text]];
    }
}
