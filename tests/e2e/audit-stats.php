<?php
/**
 * Seed more than one AI audit page with a deliberately uneven edit ratio.
 */

defined('ABSPATH') || exit;

for ($teksttv_i = 0; $teksttv_i < 51; $teksttv_i++) {
    $post_data = [
        'post_title' => 'TekstTV Audit Statistiek ' . ($teksttv_i + 1),
        'post_name' => 'teksttv-audit-stat-' . ($teksttv_i + 1),
        'post_content' => '<p>Bronartikel voor de auditstatistiek.</p>',
        'post_status' => 'publish',
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
    update_post_meta(
        $post_id,
        '_teksttv_content',
        $teksttv_i === 0 ? '<p>Handmatig bewerkt</p>' : $ai_body
    );
}

echo "audit-stats-ok count=51\n";
