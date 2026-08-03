<?php

namespace TekstTV\Blocks\Common;

use TekstTV\Helpers;

/**
 * Shared render + sanitization for taxonomy_filters in block admin forms.
 */
final class TaxonomyFilters
{
    /**
     * Render one multi-select per enabled taxonomy for a block's admin form.
     *
     * @param array<string, mixed> $filters Stored taxonomy_filters (taxonomy => term IDs).
     */
    public static function render_selects(int|string $index, array $filters, string $prefix): void
    {
        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = Helpers::get_post_taxonomies();
        $taxonomies = array_filter($all_taxonomies, fn ($t) => in_array($t['name'], $enabled_tax, true));

        foreach ($taxonomies as $tax) :
            $selected_terms = array_map('intval', (array) ($filters[$tax['name']] ?? []));
            $field_key = 'taxonomy-' . $tax['name'];
            $field_id = Helpers::field_id($prefix, $index, $field_key);
            ?>
        <div class="teksttv-field teksttv-field--choice">
            <label for="<?php echo esc_attr($field_id); ?>" data-teksttv-label="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($tax['label']); ?></label>
            <select id="<?php echo esc_attr($field_id); ?>" data-teksttv-field="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][taxonomy_filters][<?php echo esc_attr($tax['name']); ?>][]" class="teksttv-tomselect" data-placeholder="<?php echo esc_attr('Filter…'); ?>" data-summary data-summary-empty="<?php echo esc_attr('alle'); ?>" multiple>
                <?php foreach ($tax['terms'] as $term_id => $term_name) : ?>
                <option value="<?php echo esc_attr((string) $term_id); ?>" <?php echo in_array($term_id, $selected_terms, true) ? 'selected' : ''; ?>><?php echo esc_html($term_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
            <?php
        endforeach;
    }

    /**
     * @param array<string, mixed> $raw Raw block payload (may contain taxonomy_filters).
     * @return array<string, list<int>> Keyed by taxonomy name.
     */
    public static function sanitize_from_post(array $raw): array
    {
        $tax_filters = [];
        if (!empty($raw['taxonomy_filters']) && is_array($raw['taxonomy_filters'])) {
            foreach ($raw['taxonomy_filters'] as $tax_name => $term_ids) {
                $tax_name = sanitize_key((string) $tax_name);
                if (!is_array($term_ids)) {
                    $term_ids = [$term_ids];
                }
                $term_ids = array_filter(array_map('absint', $term_ids));
                if (!empty($term_ids)) {
                    $tax_filters[$tax_name] = $term_ids;
                }
            }
        }

        return $tax_filters;
    }
}
