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

echo "fixtures-ok post_id={$teksttv_post_id}\n";
