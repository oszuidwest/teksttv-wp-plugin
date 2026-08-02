<?php
/**
 * Seed more than one AI audit page with a deliberately uneven edit ratio.
 */

defined('ABSPATH') || exit;

$teksttv_admin = get_user_by('login', 'admin');
$teksttv_admin_id = $teksttv_admin ? (int) $teksttv_admin->ID : 0;

// The last post is private: the audit page must count it for a viewer with
// read_private_posts, exactly like the WP_Query behind the results table does.
for ($teksttv_i = 0; $teksttv_i < 52; $teksttv_i++) {
    $post_data = [
        'post_title' => 'TekstTV Audit Statistiek ' . ($teksttv_i + 1),
        'post_name' => 'teksttv-audit-stat-' . ($teksttv_i + 1),
        'post_content' => '<p>Bronartikel voor de auditstatistiek.</p>',
        'post_status' => $teksttv_i === 51 ? 'private' : 'publish',
        'post_author' => $teksttv_admin_id,
        'post_date' => '2026-08-02 12:00:00',
        'post_date_gmt' => '2026-08-02 12:00:00',
        'post_modified' => '2026-08-02 12:00:00',
        'post_modified_gmt' => '2026-08-02 12:00:00',
    ];
    $existing = get_page_by_path($post_data['post_name'], OBJECT, 'post');
    if ($existing) {
        $post_data['ID'] = $existing->ID;
    }

    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        throw new RuntimeException('Could not seed an audit statistics post: ' . $post_id->get_error_message());
    }

    $ai_body = '<p>AI-baseline ' . ($teksttv_i + 1) . '</p>';
    update_post_meta($post_id, '_teksttv_ai_body', $ai_body);
    update_post_meta($post_id, '_edit_last', $teksttv_admin_id);
    update_post_meta(
        $post_id,
        '_teksttv_content',
        $teksttv_i === 0 ? '<p>Handmatig bewerkt</p>' : $ai_body
    );
}

echo "audit-stats-ok count=52\n";
