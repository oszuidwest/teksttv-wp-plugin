<?php

namespace TekstTV\Blocks\Common;

use TekstTV\Helpers;

/** The shared seconds-with-unit duration field used by every block type. */
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
        $field_key = str_replace('_', '-', $name_key);
        $field_id = Helpers::field_id($prefix, $index, $field_key);

        ?>
        <div class="teksttv-field teksttv-field--compact">
            <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?><span class="screen-reader-text"><?php echo esc_html(' (seconden)'); ?></span></label>
            <div class="teksttv-input-with-unit">
                <input type="number" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][<?php echo esc_attr($name_key); ?>]" value="<?php echo esc_attr((string) $value); ?>" min="<?php echo esc_attr((string) Helpers::DURATION_MIN_SECONDS); ?>" max="<?php echo esc_attr((string) Helpers::DURATION_MAX_SECONDS); ?>" class="small-text" placeholder="<?php echo esc_attr((string) $placeholder); ?>" />
                <span class="teksttv-unit">sec</span>
            </div>
        </div>
        <?php
    }
}
