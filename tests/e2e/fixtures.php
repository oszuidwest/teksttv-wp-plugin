<?php
/**
 * E2E fixtures, loaded inside a real WordPress via `wp eval-file`.
 *
 * Seeds a channel, all features, a loop + ticker config, a published TekstTV
 * post, and a custom role/user with only the intended TekstTV capabilities so
 * the browser suite can exercise administrator and custom-role save paths.
 */

defined('ABSPATH') || exit;

update_option('teksttv_channels', [['slug' => 'tv1', 'label' => 'TV 1']]);

update_option('teksttv_features', [
    'custom_title',
    'sidebar_image',
    'extra_images',
    'scheduling',
    'page_separator',
    'bold',
    'italic',
    'underline',
    'lists',
    'ai_generate',
]);

update_option('teksttv_loop_tv1', [
    ['type' => 'articles', 'count' => 5, 'taxonomy_filters' => []],
]);

update_option('teksttv_ticker_tv1', [
    ['type' => 'ticker_text', 'message' => 'Smoke ticker bericht'],
]);

update_option('teksttv_campaign_groups', [
    ['id' => 'e2e-group-alpha', 'label' => 'E2E Seed Group Alpha'],
    ['id' => 'e2e-group-beta', 'label' => 'E2E Seed Group Beta'],
]);

update_option('teksttv_campaigns', [
    [
        'id' => 'e2e-campaign-alpha',
        'name' => 'E2E Seed Campaign Alpha',
        'group' => 'e2e-group-alpha',
        'channels' => ['tv1'],
        'duration' => 12,
        'slides' => [],
    ],
    [
        'id' => 'e2e-campaign-beta',
        'name' => 'E2E Seed Campaign Beta',
        'group' => 'e2e-group-beta',
        'channels' => ['tv1'],
        'duration' => 14,
        'slides' => [],
    ],
]);

// Real media-library attachment used by the isolated picker interaction spec.
$teksttv_attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'numberposts' => 1,
    'meta_key' => '_teksttv_e2e_fixture',
    'meta_value' => '1',
]);

if ($teksttv_attachments) {
    $teksttv_attachment_id = (int) $teksttv_attachments[0]->ID;
} else {
    $teksttv_png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if ($teksttv_png === false) {
        throw new RuntimeException('Could not decode the E2E image fixture.');
    }

    $teksttv_upload = wp_upload_bits('teksttv-e2e-image.png', null, $teksttv_png);
    if (!empty($teksttv_upload['error'])) {
        throw new RuntimeException('Could not upload the E2E image fixture: ' . $teksttv_upload['error']);
    }

    $teksttv_attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'TekstTV E2E Image',
        'post_status' => 'inherit',
    ], $teksttv_upload['file']);
    if (is_wp_error($teksttv_attachment_id)) {
        throw new RuntimeException('Could not create the E2E image attachment.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata(
        $teksttv_attachment_id,
        wp_generate_attachment_metadata($teksttv_attachment_id, $teksttv_upload['file'])
    );
    update_post_meta($teksttv_attachment_id, '_teksttv_e2e_fixture', '1');
}

// Custom role with exactly the intended TekstTV capabilities (no manage_options).
remove_role('teksttv_smoke_role');
add_role('teksttv_smoke_role', 'TekstTV Smoke Role', [
    'read' => true,
    'edit_posts' => true,
    'edit_published_posts' => true,
    'publish_posts' => true,
    'edit_teksttv' => true,
    'manage_teksttv' => true,
    'manage_teksttv_campaigns' => true,
    'manage_teksttv_content' => true,
]);

if (!get_user_by('login', 'teksttv_editor')) {
    $teksttv_uid = wp_create_user('teksttv_editor', 'password', 'teksttv_editor@example.test');
    (new WP_User($teksttv_uid))->set_role('teksttv_smoke_role');
}

// Published post that produces a text slide (active + content meta).
$teksttv_existing = get_posts([
    'name' => 'teksttv-smoke-post',
    'post_type' => 'post',
    'post_status' => 'any',
    'numberposts' => 1,
]);
$teksttv_post_id = $teksttv_existing ? $teksttv_existing[0]->ID : wp_insert_post([
    'post_title' => 'TekstTV Smoke Post',
    'post_name' => 'teksttv-smoke-post',
    'post_content' => '<p>Bronartikel voor de integratietest.</p>',
    'post_status' => 'publish',
]);
update_post_meta($teksttv_post_id, '_teksttv_active', '1');
update_post_meta($teksttv_post_id, '_teksttv_content', '<p>Slide-inhoud voor de smoke test.</p>');

// Clear any cached slides so the REST assertion sees the fixtures.
delete_transient('teksttv_slides_tv1');

echo 'fixtures-ok post_id=' . $teksttv_post_id . ' attachment_id=' . $teksttv_attachment_id . "\n";
