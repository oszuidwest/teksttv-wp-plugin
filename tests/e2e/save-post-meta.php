<?php
/**
 * Persist representative TekstTV post metadata through the production save callback.
 *
 * This runs inside WordPress Playground through its PHP API, so nonce validation,
 * capabilities, sanitization, metadata writes, and WP_Post are provided by
 * WordPress rather than by the PHPUnit bootstrap.
 */

defined('ABSPATH') || exit;

$post = get_page_by_path('teksttv-smoke-post', OBJECT, 'post');
if (!$post instanceof WP_Post) {
    throw new RuntimeException('TekstTV smoke post not found.');
}

wp_set_current_user(1);
$_POST = [
    'teksttv_meta_nonce' => wp_create_nonce('teksttv_save_meta'),
    'teksttv_active' => '1',
    'teksttv_title' => 'Opgeslagen via WordPress',
    'teksttv_content' => '<p>Opgeslagen contractinhoud.</p>',
    'teksttv_date_start' => '',
    'teksttv_date_end' => '',
    'teksttv_days' => ['1', '2', '3', '4', '5', '6', '7'],
    'teksttv_images' => [],
    'teksttv_sidebar_image' => '0',
];

\TekstTV\PostMeta::save_meta($post->ID, $post);

echo 'post-meta-save-ok post_id=' . $post->ID . "\n";
