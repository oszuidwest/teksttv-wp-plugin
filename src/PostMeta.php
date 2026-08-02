<?php

namespace TekstTV;

class PostMeta
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_meta_box']);
        add_action('save_post', [self::class, 'save_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
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
            'Tekst TV',
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

        if (!current_user_can('edit_teksttv')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post') {
            return;
        }

        $page_separator = Helpers::has_feature('page_separator');
        if ($page_separator) {
            // mce_external_plugins is page-level: core applies it once, for the
            // first TinyMCE editor initialized, and merges the result into every
            // editor on the page. Per-editor scoping happens via the toolbar in
            // render_meta_box, so don't gate on the filter's $editor_id here.
            add_filter('mce_external_plugins', [self::class, 'register_tinymce_plugin']);
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
        $default_end = self::default_end_date((string) $saved_start);

        $prompts = Helpers::get_ai_prompts();
        $ai_supported = Helpers::ai_supported();

        $config = [
            'previewUrl' => $preview_url,
            'imageDataUrl' => rest_url('teksttv/v1/image-data'),
            'defaultEndDate' => $default_end,
            'fallbackImage' => $fallback_image ?: '',
            'customImage' => $custom_image ?: '',
            'generateUrl' => rest_url('teksttv/v1/generate'),
            'aiSupported' => $ai_supported,
            'aiDiagnostics' => $prompts['diagnostics'],
            'postId' => $post_id ?: 0,
            'isNewPost' => !$post_id || get_post_status($post_id) === 'auto-draft',
            'titleCharLimit' => $prompts['title_char_limit'],
            'wordLimit' => $prompts['word_limit'],
            'wordLimitPhoto' => $prompts['word_limit_photo'],
            'pageSeparator' => $page_separator,
        ];
        wp_add_inline_script('teksttv-admin', 'var teksttvPost = ' . wp_json_encode($config) . ';', 'before');
    }

    /**
     * Default scheduling start date: the post's publish date, or today when
     * the post has none yet.
     */
    private static function default_start_date(?\WP_Post $post): string
    {
        $pub_date = ($post && $post->post_date !== '0000-00-00 00:00:00') ? $post->post_date : '';
        return $pub_date ? mysql2date('Y-m-d', $pub_date) : current_time('Y-m-d');
    }

    /**
     * Default scheduling end date derived from a start date and the
     * teksttv_default_end_days setting ('' when that setting is 0).
     */
    private static function default_end_date(string $start_date): string
    {
        $default_days = (int) get_option('teksttv_default_end_days', 7);
        $start_date = Helpers::sanitize_date_input($start_date);
        if ($default_days <= 0 || $start_date === '') {
            return '';
        }

        return (new \DateTimeImmutable($start_date))->modify('+' . $default_days . ' days')->format('Y-m-d');
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

        // Missing meta means "all days"; a stored empty array means "no days".
        $days = Helpers::normalize_days($days);
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

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an array and sanitized with absint below
        $raw_images = wp_unslash($_POST['teksttv_images'] ?? []);
        $raw_days = [];
        if (array_key_exists('teksttv_days', $_POST)) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Helpers::sanitize_days_input() in process_save()
            $raw_days = wp_unslash($_POST['teksttv_days']);
        }

        // Collect POST data; process_save() performs the remaining field-specific sanitization.
        $data = [
            'active' => isset($_POST['teksttv_active']),
            'title' => sanitize_text_field(wp_unslash($_POST['teksttv_title'] ?? '')),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via wp_kses in process_save()
            'content' => wp_unslash($_POST['teksttv_content'] ?? ''),
            'date_start' => sanitize_text_field(wp_unslash($_POST['teksttv_date_start'] ?? '')),
            'date_end' => sanitize_text_field(wp_unslash($_POST['teksttv_date_end'] ?? '')),
            'days' => $raw_days,
            'images' => is_array($raw_images) ? array_map('absint', $raw_images) : [],
            'sidebar_image' => sanitize_text_field(wp_unslash($_POST['teksttv_sidebar_image'] ?? '')),
        ];

        self::process_save($post_id, $data);
    }

    /**
     * Sanitize feature-owned values and persist normalized meta input.
     *
     * @param array<string, mixed> $data Normalized field values.
     */
    private static function process_save(int $post_id, array $data): void
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
            update_post_meta($post_id, '_teksttv_date_start', Helpers::sanitize_date_input($data['date_start'] ?? ''));
            update_post_meta($post_id, '_teksttv_date_end', Helpers::sanitize_date_input($data['date_end'] ?? ''));

            $raw_days = array_key_exists('days', $data) ? $data['days'] : [];
            $days = Helpers::sanitize_days_input($raw_days);
            if ($days === null) {
                delete_post_meta($post_id, '_teksttv_days');
            } else {
                update_post_meta($post_id, '_teksttv_days', $days);
            }
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
