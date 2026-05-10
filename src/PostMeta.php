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
            __('Tekst TV', 'teksttv'),
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

        wp_enqueue_media();
        wp_enqueue_script(
            'teksttv-post-meta',
            TEKSTTV_PLUGIN_URL . 'assets/admin.js',
            ['jquery', 'wp-i18n'],
            TEKSTTV_VERSION,
            true
        );
        wp_enqueue_style(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.css',
            [],
            TEKSTTV_VERSION
        );

        $preview_url = Helpers::get_preview_url();
        $post_id = get_the_ID();

        // Build fallback image data (post thumbnail with caption/attribution)
        $fallback_image = null;
        if ($post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $fallback_image = Helpers::get_image_data((int) $thumb_id);
            }
        }

        // Build custom sidebar image data (for JS preview of already saved custom images)
        $custom_image = null;
        if ($post_id) {
            $sidebar_id = get_post_meta($post_id, '_teksttv_sidebar_image', true);
            if ($sidebar_id) {
                $custom_image = Helpers::get_image_data((int) $sidebar_id);
            }
        }

        // Calculate default end date using the same start date shown in the form
        $default_days = (int) get_option('teksttv_default_end_days', 7);
        $saved_start = $post_id ? get_post_meta($post_id, '_teksttv_date_start', true) : '';
        if (empty($saved_start) && $post_id) {
            $post_obj = get_post($post_id);
            $saved_start = ($post_obj && $post_obj->post_date !== '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($post_obj->post_date)) : date('Y-m-d');
        }
        $default_end = ($default_days > 0 && !empty($saved_start)) ? date('Y-m-d', strtotime($saved_start . ' + ' . $default_days . ' days')) : '';

        $ai_supported = Helpers::has_feature('ai_generate') && function_exists('wp_supports_ai') && wp_supports_ai();

        wp_localize_script('teksttv-post-meta', 'teksttvPost', [
            'previewUrl' => $preview_url,
            'nonce' => wp_create_nonce('teksttv_meta'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'imageDataUrl' => rest_url('teksttv/v1/image-data'),
            'defaultEndDate' => $default_end,
            'fallbackImage' => $fallback_image ?: '',
            'customImage' => $custom_image ?: '',
            'generateUrl' => rest_url('teksttv/v1/generate'),
            'aiSupported' => $ai_supported,
            'postId' => $post_id ?: 0,
            'isNewPost' => !$post_id || get_post_status($post_id) === 'auto-draft',
            'titleCharLimit' => $ai_supported ? Helpers::get_ai_prompts()['title_char_limit'] : 0,
            'wordLimit' => $ai_supported ? Helpers::get_ai_prompts()['word_limit'] : 0,
            'hasAiContent' => $post_id && (get_post_meta($post_id, '_teksttv_ai_title', true) || get_post_meta($post_id, '_teksttv_ai_body', true)),
        ]);
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

        if (!is_array($days) || empty($days)) {
            $days = ['1', '2', '3', '4', '5', '6', '7'];
        }
        if (!is_array($images)) {
            $images = [];
        }

        // Default dates for new/unsaved posts
        $is_new = empty($date_start) && empty($date_end);
        if ($is_new) {
            $pub_date = $post->post_date !== '0000-00-00 00:00:00' ? $post->post_date : '';
            $date_start = $pub_date ? date('Y-m-d', strtotime($pub_date)) : date('Y-m-d');

            $default_days = (int) get_option('teksttv_default_end_days', 7);
            if ($default_days > 0) {
                $date_end = date('Y-m-d', strtotime($date_start . ' + ' . $default_days . ' days'));
            }
        }

        $day_labels = [
            '1' => __('Ma', 'teksttv'), '2' => __('Di', 'teksttv'), '3' => __('Wo', 'teksttv'), '4' => __('Do', 'teksttv'),
            '5' => __('Vr', 'teksttv'), '6' => __('Za', 'teksttv'), '7' => __('Zo', 'teksttv'),
        ];

        $preview_url = Helpers::get_preview_url();
        $ai_enabled = Helpers::has_feature('ai_generate') && function_exists('wp_supports_ai') && wp_supports_ai();

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

            $valid_days = ['1', '2', '3', '4', '5', '6', '7'];
            $days = array_values(array_intersect($data['days'] ?? [], $valid_days));
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
