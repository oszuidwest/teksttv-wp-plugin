<?php

namespace TekstTV;

class PostMeta
{
    /** @var list<string> */
    private const RICH_TEXT_FEATURES = ['bold', 'italic', 'underline', 'lists'];

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

    /**
     * Post types that carry the TekstTV meta box, mapped to the capability required to use it.
     *
     * @return array<string, string>
     */
    public static function post_types(): array
    {
        $types = ['post' => 'edit_teksttv'];
        if (Messages::enabled()) {
            $types[Messages::POST_TYPE] = 'edit_teksttv_messages';
        }

        return $types;
    }

    /**
     * Whether the current user may use the TekstTV fields on this post type.
     */
    public static function user_can_edit_type(string $post_type): bool
    {
        $capability = self::post_types()[$post_type] ?? null;
        return $capability !== null && current_user_can($capability);
    }

    /**
     * Whether the current user may use the TekstTV fields on any supported post type.
     */
    public static function user_can_edit_any(): bool
    {
        foreach (array_keys(self::post_types()) as $post_type) {
            if (self::user_can_edit_type($post_type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Messages have no article text to generate from.
     */
    private static function ai_enabled_for(string $post_type): bool
    {
        return $post_type !== Messages::POST_TYPE && Helpers::ai_supported();
    }

    public static function register_meta_box(): void
    {
        foreach (array_keys(self::post_types()) as $post_type) {
            if (!self::user_can_edit_type($post_type)) {
                continue;
            }

            add_meta_box(
                'teksttv_meta',
                'Tekst TV',
                [self::class, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !self::user_can_edit_type((string) $screen->post_type)) {
            return;
        }

        $page_separator = Helpers::has_feature('page_separator');
        if ($page_separator && self::has_rich_text_features()) {
            // Core applies external plugins page-wide; toolbars scope editors.
            add_filter('mce_external_plugins', [self::class, 'register_tinymce_plugin']);
        }

        Helpers::enqueue_admin_script();

        $preview_url = Helpers::get_preview_url();
        $post_id = get_the_ID();

        $fallback_image = null;
        $custom_image = null;
        $saved_start = '';
        if ($post_id) {
            $fallback_image = ArticleContent::image_data(ArticleContent::thumbnail_id($post_id), 'text_sidebar');
            $custom_image = ArticleContent::image_data(ArticleContent::sidebar_override($post_id), 'text_sidebar');
            $saved_start = ArticleContent::string_meta($post_id, '_teksttv_date_start') ?: self::default_start_date(get_post($post_id));
        }
        $default_end = self::default_end_date($saved_start);

        $prompts = Helpers::get_ai_prompts();
        $ai_supported = self::ai_enabled_for((string) $screen->post_type);
        $is_new_post = !$post_id || get_post_status($post_id) === 'auto-draft';
        $fallback_title = $is_new_post ? '' : get_the_title($post_id);

        $config = [
            'previewUrl' => $preview_url,
            'imageDataUrl' => rest_url('teksttv/v1/image-data'),
            'defaultEndDate' => $default_end,
            'fallbackImage' => $fallback_image ?: '',
            'customImage' => $custom_image ?: '',
            'generateUrl' => rest_url('teksttv/v1/generate'),
            'aiSupported' => $ai_supported,
            'postId' => $post_id ?: 0,
            'isNewPost' => $is_new_post,
            'fallbackTitle' => $fallback_title,
            'titleCharLimit' => $prompts['title_char_limit'],
            'wordLimit' => $prompts['word_limit'],
            'wordLimitPhoto' => $prompts['word_limit_photo'],
            'pageSeparator' => $page_separator,
        ];
        wp_add_inline_script('teksttv-admin', 'var teksttvPost = ' . wp_json_encode($config) . ';', 'before');
    }

    /**
     * Use the publish date, or today for unpublished posts.
     */
    private static function default_start_date(?\WP_Post $post): string
    {
        $pub_date = ($post && $post->post_date !== '0000-00-00 00:00:00') ? $post->post_date : '';
        return $pub_date ? mysql2date('Y-m-d', $pub_date) : current_time('Y-m-d');
    }

    /**
     * Add the configured default duration; zero leaves the end open.
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

    private static function has_rich_text_features(): bool
    {
        return user_can_richedit()
            && array_intersect(self::RICH_TEXT_FEATURES, Helpers::get_features()) !== [];
    }

    private static function plain_editor_content(string $content): string
    {
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content) ?? $content;
        $content = preg_replace('/<\/(?:p|li|div)>/i', "\n", $content) ?? $content;
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace("/\r\n?/", "\n", $content) ?? $content;

        return preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;
    }

    /**
     * Match generated or stored content to the editor currently in use.
     */
    public static function prepare_editor_content(string $content): string
    {
        return self::has_rich_text_features() ? $content : self::plain_editor_content($content);
    }

    public static function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('teksttv_save_meta', 'teksttv_meta_nonce');

        $active = ArticleContent::string_meta($post->ID, '_teksttv_active') ?? '';
        $title = ArticleContent::string_meta($post->ID, '_teksttv_title') ?? '';
        $content = ArticleContent::string_meta($post->ID, '_teksttv_content') ?? '';
        $date_start = ArticleContent::string_meta($post->ID, '_teksttv_date_start') ?? '';
        $date_end = ArticleContent::string_meta($post->ID, '_teksttv_date_end') ?? '';
        $days = get_post_meta($post->ID, '_teksttv_days', true);
        $images = get_post_meta($post->ID, '_teksttv_images', true);
        // Playout skips the post; the editor should see why.
        $schedule_invalid = Helpers::has_feature('scheduling') && ArticleContent::schedule_meta($post->ID) === null;

        // Missing meta means "all days"; a stored empty array means "no days".
        $days = Helpers::normalize_days($days);
        $images = is_array($images) ? array_values(array_filter(array_map(ArticleContent::attachment_id(...), $images))) : [];

        if (empty($date_start) && empty($date_end)) {
            $date_start = self::default_start_date($post);
            $date_end = self::default_end_date($date_start);
        }

        $preview_url = Helpers::get_preview_url();
        $is_message = $post->post_type === Messages::POST_TYPE;
        $ai_enabled = self::ai_enabled_for($post->post_type);
        $channels = $is_message ? Helpers::get_channels() : [];
        $selected_channels = $is_message ? Messages::channels($post->ID) : [];

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
        if (Helpers::has_feature('lists')) {
            $toolbar_items[] = 'bullist';
            $toolbar_items[] = 'numlist';
        }
        $has_page_separator = Helpers::has_feature('page_separator');
        $use_tinymce = self::has_rich_text_features();
        if ($has_page_separator && $use_tinymce) {
            $toolbar_items[] = 'teksttv_separator';
        }

        $plain_content = $use_tinymce ? '' : self::plain_editor_content($content);

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

        if (!self::user_can_edit_type($post->post_type) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an array and sanitized with absint below
        $raw_images = wp_unslash($_POST['teksttv_images'] ?? []);
        $raw_days = [];
        if (array_key_exists('teksttv_days', $_POST)) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Helpers::sanitize_days_input() in process_save()
            $raw_days = wp_unslash($_POST['teksttv_days']);
        }

        // process_save() applies field-specific sanitization.
        $data = [
            'active' => isset($_POST['teksttv_active']),
            'title' => sanitize_text_field(wp_unslash($_POST['teksttv_title'] ?? '')),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- encoded as text or sanitized via wp_kses in process_save()
            'content' => wp_unslash($_POST['teksttv_content'] ?? ''),
            'date_start' => sanitize_text_field(wp_unslash($_POST['teksttv_date_start'] ?? '')),
            'date_end' => sanitize_text_field(wp_unslash($_POST['teksttv_date_end'] ?? '')),
            'days' => $raw_days,
            'images' => is_array($raw_images) ? array_map('absint', $raw_images) : [],
            'sidebar_image' => sanitize_text_field(wp_unslash($_POST['teksttv_sidebar_image'] ?? '')),
        ];

        if ($post->post_type === Messages::POST_TYPE) {
            // A message exists only for TekstTV; publishing it is what puts it on air.
            $data['active'] = true;
            // A hidden channel section (one channel) must not overwrite the stored choice.
            if (isset($_POST['teksttv_channels_shown'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Messages::sanitize_channels()
                update_post_meta($post_id, Messages::CHANNELS_META, Messages::sanitize_channels(wp_unslash($_POST['teksttv_channels'] ?? [])));
            }
        }

        self::process_save($post_id, $data);
    }

    /**
     * Sanitize feature-owned values and persist normalized meta input.
     *
     * @param array<string, mixed> $data Normalized field values.
     */
    private static function process_save(int $post_id, array $data): void
    {
        update_post_meta($post_id, '_teksttv_active', $data['active'] ? '1' : '0');

        if (Helpers::has_feature('custom_title')) {
            update_post_meta($post_id, '_teksttv_title', $data['title'] ?? '');
        }

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

        $content = (string) ($data['content'] ?? '');
        if (!self::has_rich_text_features()) {
            // Double-encode entities for a lossless plain-text HTML round trip.
            $content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', true);
        }
        $content = wp_kses($content, $allowed_tags);
        update_post_meta($post_id, '_teksttv_content', $content);

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

        if (Helpers::has_feature('extra_images')) {
            $images = array_filter($data['images'] ?? []);
            update_post_meta($post_id, '_teksttv_images', $images);
        }

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
