<?php
/**
 * E2E fixtures, loaded inside a real WordPress via `wp eval-file`.
 *
 * Seeds a channel, all features, a loop + ticker config, a published TekstTV
 * post, campaign groups + campaigns, a media-library attachment (used by the
 * media picker specs), and a custom role/user with only the intended TekstTV
 * capabilities so the browser suite can exercise administrator and custom-role
 * save paths.
 */

defined('ABSPATH') || exit;

update_option('teksttv_channels', [['slug' => 'tv1', 'label' => 'TV 1']]);
update_option('teksttv_preview_url', 'https://preview.example.test/');
update_option('teksttv_duration_text', \TekstTV\Helpers::DURATION_DEFAULTS['teksttv_duration_text']);

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

// Only the protected fields, seeded with sentinels distinguishable from the
// plugin defaults; role.spec.ts asserts a content editor cannot overwrite them.
update_option('teksttv_ai_prompts', [
    'region_taxonomy' => 'category',
    'provider' => 'protected-provider',
    'model' => 'protected-provider/protected-model',
    'temperature' => 0.4,
    'top_p' => 0.8,
    'max_tokens' => 1024,
]);

update_option('teksttv_loop_tv1', [
    ['type' => 'articles', 'count' => 1, 'taxonomy_filters' => []],
]);

update_option('teksttv_ticker_tv1', [
    ['type' => 'ticker_text', 'message' => 'Smoke ticker bericht'],
]);

update_option('teksttv_campaign_groups', [
    ['id' => 'e2e-group-alpha', 'label' => 'E2E Seed Group Alpha'],
    ['id' => 'e2e-group-beta', 'label' => 'E2E Seed Group Beta'],
]);

// Real media-library attachment used by the isolated picker interaction spec.
$teksttv_attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'numberposts' => 1,
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off fixture lookup.
    'meta_key' => '_teksttv_e2e_fixture',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one-off fixture lookup.
    'meta_value' => '1',
]);

$teksttv_attachment_id = $teksttv_attachments ? (int) $teksttv_attachments[0]->ID : 0;
$teksttv_attachment_file = $teksttv_attachment_id ? get_attached_file($teksttv_attachment_id) : false;

if (!$teksttv_attachment_file || !is_file($teksttv_attachment_file)) {
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

    if ($teksttv_attachment_id) {
        update_attached_file($teksttv_attachment_id, $teksttv_upload['file']);
    } else {
        $teksttv_attachment_id = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'TekstTV E2E Image',
            'post_status' => 'inherit',
        ], $teksttv_upload['file'], 0, true);
        if (is_wp_error($teksttv_attachment_id) || $teksttv_attachment_id <= 0) {
            throw new RuntimeException('Could not create the E2E image attachment.');
        }
        update_post_meta($teksttv_attachment_id, '_teksttv_e2e_fixture', '1');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata(
        $teksttv_attachment_id,
        wp_generate_attachment_metadata($teksttv_attachment_id, $teksttv_upload['file'])
    );
}

// Seeded after the attachment so campaign alpha can carry a real slide: the
// campaign runtime spec needs a campaign that actually emits a commercial.
update_option('teksttv_campaigns', [
    [
        'id' => 'e2e-campaign-alpha',
        'name' => 'E2E Seed Campaign Alpha',
        'group' => 'e2e-group-alpha',
        'channels' => ['tv1'],
        'duration' => 12,
        'slides' => [$teksttv_attachment_id],
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

$teksttv_editor = get_user_by('login', 'teksttv_editor');
if ($teksttv_editor) {
    wp_set_password('password', $teksttv_editor->ID);
} else {
    $teksttv_uid = wp_create_user('teksttv_editor', 'password', 'teksttv_editor@example.test');
    if (is_wp_error($teksttv_uid)) {
        throw new RuntimeException('Could not create the TekstTV editor fixture: ' . $teksttv_uid->get_error_message());
    }
    $teksttv_editor = new WP_User($teksttv_uid);
}
$teksttv_editor->set_role('teksttv_smoke_role');

// Content editors may change prompts, but must not gain general TekstTV
// administration or the hidden provider/model controls.
remove_role('teksttv_content_role');
add_role('teksttv_content_role', 'TekstTV Content Role', [
    'read' => true,
    'manage_teksttv_content' => true,
]);

$teksttv_content_user = get_user_by('login', 'teksttv_content_editor');
if ($teksttv_content_user) {
    wp_set_password('password', $teksttv_content_user->ID);
} else {
    $teksttv_content_uid = wp_create_user('teksttv_content_editor', 'password', 'teksttv_content_editor@example.test');
    if (is_wp_error($teksttv_content_uid)) {
        throw new RuntimeException(
            'Could not create the TekstTV content editor fixture: ' . $teksttv_content_uid->get_error_message()
        );
    }
    $teksttv_content_user = new WP_User($teksttv_content_uid);
}
$teksttv_content_user->set_role('teksttv_content_role');

// Disable the block-editor welcome guide via the persisted preference core
// preloads into the editor, so the onboarding modal never mounts and cannot
// race the specs. The post editor reads core/edit-post; `_modified` must be
// current or a stale localStorage copy wins the client-side persistence merge.
$teksttv_prefs_key = $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences';
foreach (['admin', 'teksttv_editor', 'teksttv_content_editor'] as $teksttv_login) {
    $teksttv_user = get_user_by('login', $teksttv_login);
    if ($teksttv_user) {
        update_user_meta($teksttv_user->ID, $teksttv_prefs_key, [
            '_modified' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'core/edit-post' => [
                'welcomeGuide' => false,
                'welcomeGuideTemplate' => false,
            ],
        ]);
    }
}

$teksttv_now = current_datetime();

// Upsert a fixture post by slug: reset every per-post TekstTV meta to a
// known-absent state (a reused wp-env keeps meta from earlier runs), then
// apply exactly the meta the fixture needs.
$teksttv_seed_post = static function (array $post_data, array $meta): int {
    $existing = get_page_by_path($post_data['post_name'], OBJECT, 'post');
    if ($existing) {
        $post_data['ID'] = $existing->ID;
    }
    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        throw new RuntimeException(
            'Could not seed the E2E post ' . $post_data['post_name'] . ': ' . $post_id->get_error_message()
        );
    }

    $teksttv_meta_keys = [
        '_teksttv_active',
        '_teksttv_title',
        '_teksttv_content',
        '_teksttv_days',
        '_teksttv_date_start',
        '_teksttv_date_end',
        '_teksttv_images',
        '_teksttv_sidebar_image',
    ];
    foreach ($teksttv_meta_keys as $key) {
        delete_post_meta($post_id, $key);
    }
    foreach ($meta as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }

    return $post_id;
};

// Published post that produces a text slide (active + content meta). Keep it
// older than every ineligible fixture so the REST test must backfill to it.
$teksttv_post_id = $teksttv_seed_post([
    'post_title' => 'TekstTV Smoke Post',
    'post_name' => 'teksttv-smoke-post',
    'post_content' => '<p>Bronartikel voor de integratietest.</p>',
    'post_status' => 'publish',
    'post_date' => $teksttv_now->modify('-1 hour')->format('Y-m-d H:i:s'),
], [
    '_teksttv_active' => '1',
    '_teksttv_content' => '<p>Slide-inhoud voor de smoke test.</p>',
    '_teksttv_images' => [$teksttv_attachment_id],
]);

$teksttv_scheduled_post_id = $teksttv_seed_post([
    'post_title' => 'TekstTV Toekomstig Bericht',
    'post_name' => 'teksttv-scheduled-future-post',
    'post_content' => '<p>Dit recentere artikel mag nog niet worden uitgezonden.</p>',
    'post_status' => 'publish',
    'post_date' => $teksttv_now->format('Y-m-d H:i:s'),
], [
    '_teksttv_active' => '1',
    '_teksttv_content' => '<p>Nog niet uitzenden.</p>',
    '_teksttv_date_start' => $teksttv_now->modify('+1 day')->format('Y-m-d'),
]);

// Ten filler posts, all newer than the smoke post, that pass the SQL-side
// filters but are rejected at runtime (an empty weekday list, or no content
// and no images). At loop count 1 the articles block queries batches of ten,
// so these fill the first batch completely: serving the smoke post then
// requires the backfill loop to issue a second query.
$teksttv_seeded_post_ids = [$teksttv_post_id, $teksttv_scheduled_post_id];
for ($teksttv_i = 1; $teksttv_i <= 10; $teksttv_i++) {
    $teksttv_filler_meta = ['_teksttv_active' => '1'];
    if ($teksttv_i % 2 === 0) {
        $teksttv_filler_meta['_teksttv_content'] = '<p>Geblokkeerd op weekdag.</p>';
        $teksttv_filler_meta['_teksttv_days'] = [];
    }
    $teksttv_seeded_post_ids[] = $teksttv_seed_post([
        'post_title' => 'TekstTV Backfill Vulling ' . $teksttv_i,
        'post_name' => 'teksttv-backfill-filler-' . $teksttv_i,
        'post_content' => '<p>Vulartikel voor de backfilltest.</p>',
        'post_status' => 'publish',
        'post_date' => $teksttv_now->modify('-' . $teksttv_i . ' minutes')->format('Y-m-d H:i:s'),
    ], $teksttv_filler_meta);
}

// The suite assumes a fully known post table (the smoke post must stay on
// page one of wp-admin/edit.php, and only seeded posts may reach the slides
// feed), so drop everything this file did not seed - including leftovers
// from older fixture versions in a reused wp-env database.
$teksttv_all_post_ids = get_posts([
    'post_type' => 'post',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
]);
foreach ($teksttv_all_post_ids as $teksttv_stale_id) {
    if (!in_array((int) $teksttv_stale_id, $teksttv_seeded_post_ids, true)) {
        wp_delete_post((int) $teksttv_stale_id, true);
    }
}

echo 'fixtures-ok post_id=' . $teksttv_post_id
    . ' scheduled_post_id=' . $teksttv_scheduled_post_id
    . ' attachment_id=' . $teksttv_attachment_id . "\n";
