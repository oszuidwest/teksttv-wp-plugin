<?php
/**
 * Seed AI audit posts in two months with deliberately different edit ratios.
 */

defined('ABSPATH') || exit;

$teksttv_audit_fixtures = [
    [
        'title' => 'TekstTV Audit Juli Bewerkt',
        'slug' => 'teksttv-audit-july-modified',
        'modified' => '2026-07-20 10:00:00',
        'meta' => [
            '_teksttv_ai_body' => '<p>AI-versie juli</p>',
            '_teksttv_content' => '<p>Handmatig bewerkt in juli</p>',
        ],
    ],
    [
        // Private: the audit page must count this post for a viewer with
        // read_private_posts, exactly like the WP_Query behind the table does.
        'title' => 'TekstTV Audit Juli Ongewijzigd',
        'slug' => 'teksttv-audit-july-unmodified',
        'status' => 'private',
        'modified' => '2026-07-10 10:00:00',
        'meta' => [
            '_teksttv_ai_title' => 'Ongewijzigde kop',
            '_teksttv_title' => 'Ongewijzigde kop',
            '_teksttv_ai_body' => '<p>Ongewijzigde tekst</p>',
            '_teksttv_content' => '<p>Ongewijzigde tekst</p>',
        ],
    ],
    [
        'title' => 'TekstTV Audit Augustus Buiten Selectie',
        'slug' => 'teksttv-audit-august-excluded',
        'modified' => '2026-08-05 10:00:00',
        'meta' => [
            '_teksttv_ai_body' => '<p>AI-versie augustus</p>',
            '_teksttv_content' => '<p>Handmatig bewerkt in augustus</p>',
        ],
    ],
];

// wp_insert_post() always overwrites post_modified with the current time on
// updates, so an idempotent reseed needs this filter to keep the fixture dates.
$teksttv_preserve_modified = static function (array $data, array $postarr): array {
    if (isset($postarr['post_modified'], $postarr['post_modified_gmt'])) {
        $data['post_modified'] = $postarr['post_modified'];
        $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
    }

    return $data;
};

add_filter('wp_insert_post_data', $teksttv_preserve_modified, PHP_INT_MAX, 2);
try {
    foreach ($teksttv_audit_fixtures as $teksttv_fixture) {
        $teksttv_existing = get_page_by_path($teksttv_fixture['slug'], OBJECT, 'post');
        $teksttv_post_data = [
            'post_title' => $teksttv_fixture['title'],
            'post_name' => $teksttv_fixture['slug'],
            'post_content' => '<p>Bronartikel voor de auditstatistiek.</p>',
            'post_status' => $teksttv_fixture['status'] ?? 'publish',
            'post_modified' => $teksttv_fixture['modified'],
            'post_modified_gmt' => get_gmt_from_date($teksttv_fixture['modified']),
        ];
        if ($teksttv_existing) {
            $teksttv_post_data['ID'] = $teksttv_existing->ID;
        }

        $teksttv_post_id = wp_insert_post($teksttv_post_data, true);
        if (is_wp_error($teksttv_post_id)) {
            throw new RuntimeException('Could not seed an audit statistics post: ' . $teksttv_post_id->get_error_message());
        }

        foreach ($teksttv_fixture['meta'] as $teksttv_meta_key => $teksttv_meta_value) {
            update_post_meta($teksttv_post_id, $teksttv_meta_key, $teksttv_meta_value);
        }
    }
} finally {
    remove_filter('wp_insert_post_data', $teksttv_preserve_modified, PHP_INT_MAX);
}

echo "audit-stats-ok count=3\n";
