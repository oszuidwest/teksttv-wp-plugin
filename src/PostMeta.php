<?php

namespace TekstTV;

class PostMeta
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_meta_box']);
        add_action('save_post', [self::class, 'save_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('mce_external_plugins', [self::class, 'register_tinymce_plugin']);

        // Broader slides-cache invalidation for editorial changes that affect
        // output but do not go through the Tekst TV meta box: quick edits,
        // scheduled publishes, category assignments and media caption edits.
        add_action('save_post_post', [self::class, 'invalidate_on_post_save'], 10, 2);
        add_action('transition_post_status', [self::class, 'invalidate_on_status_transition'], 10, 3);
        add_action('set_object_terms', [self::class, 'invalidate_on_terms_change'], 10, 1);
        add_action('attachment_updated', [self::class, 'invalidate_on_attachment_update'], 10, 1);
    }

    /**
     * Invalidate the slides cache when a post is created or edited outside the
     * Tekst TV meta box (e.g. quick edit, block editor, REST).
     */
    public static function invalidate_on_post_save(int $post_id, \WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        RestApi::invalidate_slides_cache();
    }

    /**
     * Invalidate when a post enters or leaves the published state, which covers
     * scheduled publishes that never fire save_post.
     */
    public static function invalidate_on_status_transition(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type !== 'post' || $new_status === $old_status) {
            return;
        }
        if ($new_status === 'publish' || $old_status === 'publish') {
            RestApi::invalidate_slides_cache();
        }
    }

    /**
     * Invalidate when a post's terms change (e.g. category reassignment), which
     * can change category-derived sidebar images and taxonomy-filtered loops.
     *
     * @param mixed $object_id
     */
    public static function invalidate_on_terms_change($object_id): void
    {
        if (get_post_type((int) $object_id) === 'post') {
            RestApi::invalidate_slides_cache();
        }
    }

    /**
     * Invalidate when an attachment is updated, since caption/attribution flow
     * into image slides.
     */
    public static function invalidate_on_attachment_update(int $post_id): void
    {
        RestApi::invalidate_slides_cache();
    }

    /**
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    public static function register_tinymce_plugin(array $plugins): array
    {
        $plugins['teksttv_separator'] = TEKSTTV_PLUGIN_URL . 'assets/tinymce-separator.js';
        return $plugins;
    }

    public static function register_meta_box(): void
    {
        if (!current_user_can('edit_teksttv')) {
            return;
        }

        add_meta_box(
            'teksttv_meta',
            __('Tekst TV', 'teksttv-wp-plugin'),
            [self::class, 'render_meta_box'],
            'post',
            'normal',
            'high'
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post') {
            return;
        }

        Helpers::enqueue_admin_script();

        $preview_url = Helpers::get_preview_url();
        $post_id = get_the_ID();

        // Build fallback image data (post thumbnail with caption/attribution)
        $fallback_image = null;
        if ($post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $fallback_image = Helpers::get_image_data((int) $thumb_id, 'large', 'text_sidebar');
            }
        }

        // Build custom sidebar image data (for JS preview of already saved custom images)
        $custom_image = null;
        if ($post_id) {
            $sidebar_id = get_post_meta($post_id, '_teksttv_sidebar_image', true);
            if ($sidebar_id) {
                $custom_image = Helpers::get_image_data((int) $sidebar_id, 'large', 'text_sidebar');
            }
        }

        // Calculate default end date using the same start date shown in the form
        $saved_start = $post_id ? get_post_meta($post_id, '_teksttv_date_start', true) : '';
        if (empty($saved_start) && $post_id) {
            $saved_start = self::default_start_date(get_post($post_id));
        }
        $default_end = !empty($saved_start) ? self::default_end_date($saved_start) : '';

        $ai_supported = Helpers::ai_supported();
        $prompts = $ai_supported ? Helpers::get_ai_prompts() : [];

        wp_localize_script('teksttv-admin', 'teksttvPost', [
            'previewUrl' => $preview_url,
            'restNonce' => wp_create_nonce('wp_rest'),
            'imageDataUrl' => rest_url('teksttv/v1/image-data'),
            'defaultEndDate' => $default_end,
            'fallbackImage' => $fallback_image ?: '',
            'customImage' => $custom_image ?: '',
            'generateUrl' => rest_url('teksttv/v1/generate'),
            'aiSupported' => $ai_supported,
            'postId' => $post_id ?: 0,
            'isNewPost' => !$post_id || get_post_status($post_id) === 'auto-draft',
            'titleCharLimit' => $prompts['title_char_limit'] ?? 0,
            'wordLimit' => $prompts['word_limit'] ?? 0,
            'wordLimitPhoto' => $prompts['word_limit_photo'] ?? 0,
        ]);
    }

    /**
     * Default scheduling start date: the post's publish date, or today when
     * the post has none yet.
     */
    private static function default_start_date(?\WP_Post $post): string
    {
        $pub_date = ($post && $post->post_date !== '0000-00-00 00:00:00') ? $post->post_date : '';
        return $pub_date ? date('Y-m-d', strtotime($pub_date)) : date('Y-m-d');
    }

    /**
     * Default scheduling end date derived from a start date and the
     * teksttv_default_end_days setting ('' when that setting is 0).
     */
    private static function default_end_date(string $start_date): string
    {
        $default_days = (int) get_option('teksttv_default_end_days', 7);
        return $default_days > 0 ? date('Y-m-d', strtotime($start_date . ' + ' . $default_days . ' days')) : '';
    }

    public static function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('teksttv_save_meta', 'teksttv_meta_nonce');

        $active = get_post_meta($post->ID, '_teksttv_active', true);
        $content = get_post_meta($post->ID, '_teksttv_content', true);
        $date_start = get_post_meta($post->ID, '_teksttv_date_start', true);
        $date_end = get_post_meta($post->ID, '_teksttv_date_end', true);
        $days = get_post_meta($post->ID, '_teksttv_days', true);
        $images = get_post_meta($post->ID, '_teksttv_images', true);

        // Empty days means "all days" and renders with every checkbox checked.
        if (!is_array($days)) {
            $days = [];
        }
        if (!is_array($images)) {
            $images = [];
        }

        // Default dates for new/unsaved posts
        if (empty($date_start) && empty($date_end)) {
            $date_start = self::default_start_date($post);
            $date_end = self::default_end_date($date_start);
        }

        $preview_url = Helpers::get_preview_url();
        $ai_enabled = Helpers::ai_supported();

        // Build TinyMCE toolbar and valid elements based on features
        $toolbar_items = [];
        if (Helpers::has_feature('bold')) {
            $toolbar_items[] = 'bold';
        }
        if (Helpers::has_feature('italic')) {
            $toolbar_items[] = 'italic';
        }
        if (Helpers::has_feature('underline')) {
            $toolbar_items[] = 'underline';
        }
        if (!empty($toolbar_items)) {
            $toolbar_items[] = '|';
        }
        if (Helpers::has_feature('lists')) {
            $toolbar_items[] = 'bullist';
            $toolbar_items[] = 'numlist';
            $toolbar_items[] = '|';
        }
        if (Helpers::has_feature('page_separator')) {
            $toolbar_items[] = 'teksttv_separator';
            $toolbar_items[] = '|';
        }
        $toolbar_items[] = 'removeformat';
        $toolbar_items[] = 'undo';
        $toolbar_items[] = 'redo';

        $valid_elements = ['br', 'p'];
        if (Helpers::has_feature('bold')) {
            array_push($valid_elements, 'strong', 'b');
        }
        if (Helpers::has_feature('italic')) {
            array_push($valid_elements, 'em', 'i');
        }
        if (Helpers::has_feature('underline')) {
            $valid_elements[] = 'u';
        }
        if (Helpers::has_feature('lists')) {
            array_push($valid_elements, 'ul', 'ol', 'li');
        }

        include TEKSTTV_PLUGIN_DIR . 'src/views/post-meta-box.php';
    }

    public static function save_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['teksttv_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_meta_nonce'])), 'teksttv_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'post') {
            return;
        }

        if (!current_user_can('edit_teksttv') || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sanitize POST data
        $data = [
            'active' => isset($_POST['teksttv_active']),
            'title' => sanitize_text_field(wp_unslash($_POST['teksttv_title'] ?? '')),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via wp_kses in process_save()
            'content' => wp_unslash($_POST['teksttv_content'] ?? ''),
            'date_start' => sanitize_text_field(wp_unslash($_POST['teksttv_date_start'] ?? '')),
            'date_end' => sanitize_text_field(wp_unslash($_POST['teksttv_date_end'] ?? '')),
            'days' => array_map('sanitize_text_field', wp_unslash($_POST['teksttv_days'] ?? [])),
            'images' => array_map('absint', wp_unslash($_POST['teksttv_images'] ?? [])),
            'sidebar_image' => sanitize_text_field(wp_unslash($_POST['teksttv_sidebar_image'] ?? '')),
        ];

        self::process_save($post_id, $data);

        // save_post_post also invalidates for this save, but it fires BEFORE
        // this callback writes the meta. A concurrent /slides request in that
        // window can re-cache the old meta, so invalidate again after writing.
        RestApi::invalidate_slides_cache();
    }

    /**
     * Process sanitized meta data and persist to the database.
     * Separated from save_meta() for testability without $_POST.
     *
     * @param array<string, mixed> $data Sanitized field values.
     */
    public static function process_save(int $post_id, array $data): void
    {
        // Active toggle
        update_post_meta($post_id, '_teksttv_active', $data['active'] ? '1' : '0');

        // Title override (only save if feature enabled)
        if (Helpers::has_feature('custom_title')) {
            update_post_meta($post_id, '_teksttv_title', $data['title'] ?? '');
        }

        // Content — strip tags that are disabled by features
        $allowed_tags = ['p' => [], 'br' => []];
        if (Helpers::has_feature('bold')) {
            $allowed_tags['strong'] = [];
            $allowed_tags['b'] = [];
        }
        if (Helpers::has_feature('italic')) {
            $allowed_tags['em'] = [];
            $allowed_tags['i'] = [];
        }
        if (Helpers::has_feature('underline')) {
            $allowed_tags['u'] = [];
        }
        if (Helpers::has_feature('lists')) {
            $allowed_tags['ul'] = [];
            $allowed_tags['ol'] = [];
            $allowed_tags['li'] = [];
        }
        $content = wp_kses($data['content'] ?? '', $allowed_tags);
        update_post_meta($post_id, '_teksttv_content', $content);

        // Scheduling (only save if feature enabled)
        if (Helpers::has_feature('scheduling')) {
            update_post_meta($post_id, '_teksttv_date_start', $data['date_start'] ?? '');
            update_post_meta($post_id, '_teksttv_date_end', $data['date_end'] ?? '');

            // null (all 7 days checked) and [] are both "no restriction".
            $days = Helpers::sanitize_days_input($data['days'] ?? []) ?? [];
            update_post_meta($post_id, '_teksttv_days', $days);
        }

        // Extra images (only save if feature enabled)
        if (Helpers::has_feature('extra_images')) {
            $images = array_filter($data['images'] ?? []);
            update_post_meta($post_id, '_teksttv_images', $images);
        }

        // Sidebar image (only save if feature enabled)
        if (Helpers::has_feature('sidebar_image')) {
            $sidebar_raw = $data['sidebar_image'] ?? '';
            if ($sidebar_raw === '0') {
                update_post_meta($post_id, '_teksttv_sidebar_image', '0');
            } elseif (absint($sidebar_raw) > 0) {
                update_post_meta($post_id, '_teksttv_sidebar_image', absint($sidebar_raw));
            } else {
                delete_post_meta($post_id, '_teksttv_sidebar_image');
            }
        }
    }
}
