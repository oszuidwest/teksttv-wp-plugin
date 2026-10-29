<?php

namespace TekstTV\Blocks\Common;

use TekstTV\AdminPage;
use TekstTV\Helpers;

final class DurationField
{
    /**
     * @param string $name_key The form field key, e.g. 'duration' or 'duration_text'.
     * @param string|int $value Stored value; empty string falls back to the placeholder default.
     * @param string|int $placeholder The effective default shown when no value is set.
     */
    public static function render(
        string $prefix,
        int|string $index,
        string $name_key,
        string $label,
        string|int $value,
        string|int $placeholder
    ): void {
        ?>
        <div class="teksttv-field teksttv-field--compact">
            <label <?php Helpers::field_for($prefix, $index, $name_key); ?>><?php echo esc_html($label); ?><span class="screen-reader-text"><?php echo esc_html(' (seconden)'); ?></span></label>
            <div class="teksttv-input-with-unit">
                <input type="number" <?php Helpers::field_attrs($prefix, $index, $name_key); ?> value="<?php echo esc_attr((string) $value); ?>" min="<?php echo esc_attr((string) Helpers::DURATION_MIN_SECONDS); ?>" max="<?php echo esc_attr((string) Helpers::DURATION_MAX_SECONDS); ?>" class="small-text" placeholder="<?php echo esc_attr((string) $placeholder); ?>" autocomplete="off" />
                <span class="teksttv-unit">sec</span>
            </div>
        </div>
        <?php
    }

    /**
     * Render the text and image duration section shared by post-backed loop blocks.
     *
     * @param array<string, mixed> $block
     */
    public static function render_text_image_section(string $prefix, int|string $index, array $block): void
    {
        $default_text = (int) get_option('teksttv_duration_text', Helpers::DURATION_DEFAULTS['teksttv_duration_text']);
        $default_image = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image']);

        AdminPage::render_block_section_start('Weergaveduur', 'Leeg laten gebruikt de standaardinstelling.', 'duration');
        ?>
        <div class="teksttv-field-grid teksttv-field-grid--duration">
            <?php
            self::render($prefix, $index, 'duration_text', 'Tekst', (string) ($block['duration_text'] ?? ''), $default_text);
            self::render($prefix, $index, 'duration_image', 'Afbeelding', (string) ($block['duration_image'] ?? ''), $default_image);
            ?>
        </div>
        <?php
        AdminPage::render_block_section_end();
    }

    /**
     * Clamp submitted text and image durations; empty fields are omitted so the defaults apply.
     *
     * @param array<string, mixed> $raw
     * @return array<string, int>
     */
    public static function sanitize_text_image(array $raw): array
    {
        $saved = [];
        foreach (['duration_text', 'duration_image'] as $key) {
            $value = $raw[$key] ?? '';
            if ($value !== '') {
                $saved[$key] = Helpers::clamp_int($value, Helpers::DURATION_MIN_SECONDS, Helpers::DURATION_MAX_SECONDS);
            }
        }

        return $saved;
    }
}
