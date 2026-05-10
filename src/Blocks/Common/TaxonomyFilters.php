<?php

namespace TekstTV\Blocks\Common;

/**
 * Shared sanitization for taxonomy_filters in block POST data.
 */
final class TaxonomyFilters
{
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
