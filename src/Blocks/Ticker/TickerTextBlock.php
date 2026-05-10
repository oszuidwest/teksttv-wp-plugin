<?php

namespace TekstTV\Blocks\Ticker;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Contracts\TickerBlock;

final class TickerTextBlock implements TickerBlock
{
    public static function register(): void
    {
        BlockRegistry::register('ticker_text', [
            'label' => __('Tekst', 'teksttv'),
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

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field" style="flex:1;">
                <label><?php esc_html_e('Bericht', 'teksttv'); ?></label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][message]" value="<?php echo esc_attr((string) $message); ?>" class="large-text" placeholder="<?php echo esc_attr__('Ticker tekst...', 'teksttv'); ?>" />
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
