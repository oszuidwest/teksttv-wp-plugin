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

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
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
                $thumb_url = wp_get_attachment_image_url($thumb_id, 'large');
                if ($thumb_url) {
                    $fallback_image = ['url' => $thumb_url];
                    $caption = wp_get_attachment_caption($thumb_id);
                    if ($caption) {
                        $fallback_image['caption'] = $caption;
                    }
                    $attribution = apply_filters('teksttv_image_attribution', '', $thumb_id);
                    if ($attribution) {
                        $fallback_image['attribution'] = $attribution;
                    }
                }
            }
        }

        // Build custom sidebar image data (for JS preview of already saved custom images)
        $custom_image = null;
        if ($post_id) {
            $sidebar_id = get_post_meta($post_id, '_teksttv_sidebar_image', true);
            if ($sidebar_id) {
                $custom_url = wp_get_attachment_image_url((int) $sidebar_id, 'large');
                if ($custom_url) {
                    $custom_image = ['url' => $custom_url];
                    $custom_caption = wp_get_attachment_caption((int) $sidebar_id);
                    if ($custom_caption) {
                        $custom_image['caption'] = $custom_caption;
                    }
                    $custom_attr = apply_filters('teksttv_image_attribution', '', (int) $sidebar_id);
                    if ($custom_attr) {
                        $custom_image['attribution'] = $custom_attr;
                    }
                }
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
            '1' => 'Ma',
            '2' => 'Di',
            '3' => 'Wo',
            '4' => 'Do',
            '5' => 'Vr',
            '6' => 'Za',
            '7' => 'Zo',
        ];

        $preview_url = Helpers::get_preview_url();

        ?>
        <div class="teksttv-meta-box">
            <div class="teksttv-toggle-bar">
                <label>
                    <input type="checkbox" name="teksttv_active" value="1" <?php checked($active, '1'); ?> id="teksttv-active" />
                    <span class="dashicons dashicons-desktop"></span>
                    Toon op Tekst TV
                </label>
                <span class="teksttv-toggle-status <?php echo $active === '1' ? 'is-active' : ''; ?>" id="teksttv-toggle-status">
                    <?php echo $active === '1' ? 'Actief' : 'Inactief'; ?>
                </span>
            </div>

            <div class="teksttv-fields" id="teksttv-fields">
                <!-- Two-column layout: editor left, preview right -->
                <div class="teksttv-editor-layout">
                    <div class="teksttv-editor-main">
                        <?php $ai_enabled = Helpers::has_feature('ai_generate') && function_exists('wp_supports_ai') && wp_supports_ai(); ?>
                        <?php if ($ai_enabled) : ?>
                        <div class="teksttv-meta-section teksttv-ai-section">
                            <button type="button" class="button button-small teksttv-generate-btn" data-field="both"><span class="dashicons dashicons-admin-generic teksttv-button-icon"></span> Genereer kop &amp; tekst</button>
                            <span class="teksttv-generate-status" id="teksttv-generate-status"></span>
                            <?php if (get_post_meta($post->ID, '_teksttv_ai_title', true) || get_post_meta($post->ID, '_teksttv_ai_body', true)) : ?>
                            <span class="teksttv-ai-badge" id="teksttv-ai-badge"><span class="dashicons dashicons-admin-generic"></span> AI gegenereerd</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (Helpers::has_feature('custom_title')) : ?>
                        <!-- Title override -->
                        <div class="teksttv-meta-section">
                            <div class="teksttv-section-header">
                                <label for="teksttv-title" class="teksttv-section-label">Kop</label>
                                <?php if ($ai_enabled) : ?>
                                <button type="button" class="button button-small teksttv-generate-btn" data-field="title"><span class="dashicons dashicons-admin-generic teksttv-button-icon"></span> Genereer</button>
                                <?php endif; ?>
                            </div>
                            <?php $custom_title = get_post_meta($post->ID, '_teksttv_title', true); ?>
                            <input type="text" name="teksttv_title" id="teksttv-title" value="<?php echo esc_attr($custom_title); ?>" class="large-text" placeholder="<?php echo esc_attr(get_the_title($post)); ?>" />
                            <div class="teksttv-title-footer">
                                <p class="description">Laat leeg om de titel van het artikel te gebruiken.</p>
                                <span class="teksttv-charcount" id="teksttv-charcount"></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Content -->
                        <div class="teksttv-meta-section teksttv-content-wrap">
                            <div class="teksttv-section-header">
                                <label class="teksttv-section-label">Tekst voor Tekst TV</label>
                                <?php if ($ai_enabled) : ?>
                                <button type="button" class="button button-small teksttv-generate-btn" data-field="body"><span class="dashicons dashicons-admin-generic teksttv-button-icon"></span> Genereer</button>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Build toolbar based on enabled features
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

                            // Build valid elements based on enabled features
                            $valid = ['br', 'p'];
                            if (Helpers::has_feature('bold')) {
                                array_push($valid, 'strong', 'b');
                            }
                            if (Helpers::has_feature('italic')) {
                                array_push($valid, 'em', 'i');
                            }
                            if (Helpers::has_feature('underline')) {
                                $valid[] = 'u';
                            }
                            if (Helpers::has_feature('lists')) {
                                array_push($valid, 'ul', 'ol', 'li');
                            }

                            wp_editor($content, 'teksttv_content', [
                                'textarea_name' => 'teksttv_content',
                                'textarea_rows' => 4,
                                'editor_height' => 100,
                                'media_buttons' => false,
                                'teeny' => false,
                                'quicktags' => ['buttons' => implode(',', array_filter([
                                    Helpers::has_feature('bold') ? 'strong' : '',
                                    Helpers::has_feature('italic') ? 'em' : '',
                                    Helpers::has_feature('lists') ? 'ul' : '',
                                    Helpers::has_feature('lists') ? 'ol' : '',
                                    Helpers::has_feature('lists') ? 'li' : '',
                                    'close',
                                ]))
                                ],
                                'tinymce' => [
                                    'toolbar1' => implode(',', $toolbar_items),
                                    'toolbar2' => '',
                                    'toolbar3' => '',
                                    'toolbar4' => '',
                                    'block_formats' => '',
                                    'valid_elements' => implode(',', $valid),
                                    'formats' => wp_json_encode([
                                        'underline' => ['inline' => 'u'],
                                    ]),
                                    'forced_root_block' => 'p',
                                    'plugins' => 'lists,paste,wpautoresize',
                                    'wp_autoresize_on' => true,
                                    'autoresize_min_height' => 100,
                                    'autoresize_max_height' => 350,
                                    'content_css' => TEKSTTV_PLUGIN_URL . 'assets/tinymce-content.css',
                                    'content_style' => 'body { margin: 0 !important; padding: 6px 8px !important; } body p { margin: 0 0 0.5em !important; }',
                                ],
                            ]);
                            ?>
                            <div class="teksttv-editor-footer">
                                <span class="teksttv-wordcount" id="teksttv-wordcount"></span>
                            </div>
                        </div>

                        <?php if (Helpers::has_feature('sidebar_image')) : ?>
                        <!-- Sidebar image -->
                        <div class="teksttv-meta-section teksttv-sidebar-image-section">
                            <span class="teksttv-section-label">Sidebar afbeelding</span>
                            <?php
                            $sidebar_image_id = get_post_meta($post->ID, '_teksttv_sidebar_image', true);
                            $is_none = $sidebar_image_id === '0';
                            $is_custom = $sidebar_image_id !== '' && $sidebar_image_id !== false && !$is_none;
                            $custom_url = $is_custom ? wp_get_attachment_image_url((int) $sidebar_image_id, 'medium') : '';
                            $fallback_url = get_the_post_thumbnail_url($post->ID, 'medium') ?: '';
                            $active_state = $is_none ? 'none' : ($is_custom ? 'custom' : 'default');
                            ?>
                            <input type="hidden" name="teksttv_sidebar_image" id="teksttv-sidebar-image-id" value="<?php echo esc_attr($sidebar_image_id); ?>" />
                            <div class="teksttv-image-cards" data-active="<?php echo esc_attr($active_state); ?>">
                                <button type="button" class="teksttv-image-card <?php echo $active_state === 'default' ? 'is-active' : ''; ?>" data-state="default" id="teksttv-sidebar-card-default">
                                    <span class="teksttv-image-card-label">Standaard</span>
                                    <?php if ($fallback_url) : ?>
                                        <img src="<?php echo esc_url($fallback_url); ?>" alt="" class="teksttv-image-card-thumb" />
                                    <?php else : ?>
                                        <span class="teksttv-image-card-icon"><span class="dashicons dashicons-format-image"></span></span>
                                    <?php endif; ?>
                                </button>
                                <button type="button" class="teksttv-image-card <?php echo $active_state === 'custom' ? 'is-active' : ''; ?>" data-state="custom" id="teksttv-sidebar-card-custom">
                                    <span class="teksttv-image-card-label">Eigen</span>
                                    <?php if ($custom_url) : ?>
                                        <img src="<?php echo esc_url($custom_url); ?>" alt="" class="teksttv-image-card-thumb" id="teksttv-sidebar-image-img" />
                                    <?php else : ?>
                                        <span class="teksttv-image-card-icon" id="teksttv-sidebar-image-placeholder"><span class="dashicons dashicons-upload"></span></span>
                                        <img src="" alt="" class="teksttv-image-card-thumb is-hidden" id="teksttv-sidebar-image-img" />
                                    <?php endif; ?>
                                </button>
                                <button type="button" class="teksttv-image-card <?php echo $active_state === 'none' ? 'is-active' : ''; ?>" data-state="none" id="teksttv-sidebar-card-none">
                                    <span class="teksttv-image-card-label">Geen</span>
                                    <span class="teksttv-image-card-icon"><span class="dashicons dashicons-hidden"></span></span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (Helpers::has_feature('extra_images')) : ?>
                        <!-- Extra images -->
                        <div class="teksttv-meta-section teksttv-images-section">
                            <h4>Extra afbeeldingen</h4>
                            <p class="description">Worden als aparte fullscreen image-slides getoond na de tekst.</p>
                            <div id="teksttv-images-list" class="teksttv-images-list">
                                <?php foreach ($images as $attachment_id) : ?>
                                    <?php $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail'); ?>
                                    <?php if ($thumb) : ?>
                                    <div class="teksttv-image-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                                        <img src="<?php echo esc_url($thumb); ?>" alt="" />
                                        <input type="hidden" name="teksttv_images[]" value="<?php echo esc_attr($attachment_id); ?>" />
                                        <button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button" id="teksttv-add-images"><span class="dashicons dashicons-format-gallery teksttv-button-icon"></span> Afbeeldingen toevoegen</button>
                        </div>
                        <?php endif; ?>

                        <?php if (Helpers::has_feature('scheduling')) : ?>
                        <!-- Scheduling -->
                        <div class="teksttv-meta-section teksttv-collapsible">
                            <button type="button" class="teksttv-collapsible-toggle">
                                <span class="teksttv-section-label">Planning</span>
                                <span class="dashicons dashicons-arrow-down-alt2 teksttv-collapsible-icon"></span>
                            </button>
                            <div class="teksttv-collapsible-body is-hidden">
                            <div class="teksttv-scheduling">
                                <div class="teksttv-scheduling-group">
                                    <h4>Periode</h4>
                                    <div class="teksttv-dates-row">
                                        <div class="teksttv-date-field">
                                            <label for="teksttv-date-start">Vanaf</label>
                                            <input type="date" name="teksttv_date_start" value="<?php echo esc_attr($date_start); ?>" id="teksttv-date-start" />
                                        </div>
                                        <div class="teksttv-date-field">
                                            <label for="teksttv-date-end">Tot en met</label>
                                            <input type="date" name="teksttv_date_end" value="<?php echo esc_attr($date_end); ?>" id="teksttv-date-end" />
                                            <button type="button" class="teksttv-date-reset is-hidden" id="teksttv-date-end-reset" title="Zet naar standaard einddatum">
                                                <span class="dashicons dashicons-image-rotate"></span> Standaard
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="teksttv-scheduling-group">
                                    <h4>Weekdagen</h4>
                                    <div class="teksttv-days-row">
                                        <?php foreach ($day_labels as $num => $label) : ?>
                                        <label class="teksttv-day-toggle">
                                            <input type="checkbox" name="teksttv_days[]" value="<?php echo esc_attr((string) $num); ?>" <?php checked(in_array((string) $num, $days, true)); ?> />
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">Bericht wordt alleen op geselecteerde dagen getoond.</p>
                                </div>
                            </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Preview sidebar -->
                    <div class="teksttv-editor-preview" id="teksttv-preview-wrap">
                        <div class="teksttv-preview-header">
                            <span class="teksttv-section-label">Preview</span>
                            <div class="teksttv-preview-nav" id="teksttv-preview-nav">
                                <button type="button" class="button button-small" id="teksttv-preview-prev" disabled><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                                <span class="teksttv-preview-counter" id="teksttv-preview-counter">1 / 1</span>
                                <button type="button" class="button button-small" id="teksttv-preview-next" disabled><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                            </div>
                        </div>
                        <?php if ($preview_url) : ?>
                            <div class="teksttv-preview-container">
                                <iframe id="teksttv-preview-iframe" class="teksttv-preview-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
                                <button type="button" class="teksttv-preview-enlarge-btn" id="teksttv-preview-enlarge" title="Vergroot preview"><span class="dashicons dashicons-editor-expand"></span></button>
                            </div>
                            <div class="teksttv-preview-thumbs" id="teksttv-preview-thumbs">
                                <!-- Filled by JS: mini slide thumbnails -->
                            </div>
                        <?php else : ?>
                            <div class="teksttv-no-preview">
                                Stel een preview URL in bij <a href="<?php echo esc_url(admin_url('admin.php?page=teksttv&tab=settings')); ?>">Tekst TV &rarr; Instellingen</a> om live preview te activeren.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
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

        // Active toggle
        $active = isset($_POST['teksttv_active']) ? '1' : '0';
        update_post_meta($post_id, '_teksttv_active', $active);

        // Title override (only save if feature enabled)
        if (Helpers::has_feature('custom_title')) {
            $title = sanitize_text_field(wp_unslash($_POST['teksttv_title'] ?? ''));
            update_post_meta($post_id, '_teksttv_title', $title);
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
        $content = wp_kses(wp_unslash($_POST['teksttv_content'] ?? ''), $allowed_tags);
        update_post_meta($post_id, '_teksttv_content', $content);

        // Scheduling (only save if feature enabled)
        if (Helpers::has_feature('scheduling')) {
            $date_start = sanitize_text_field(wp_unslash($_POST['teksttv_date_start'] ?? ''));
            $date_end = sanitize_text_field(wp_unslash($_POST['teksttv_date_end'] ?? ''));
            update_post_meta($post_id, '_teksttv_date_start', $date_start);
            update_post_meta($post_id, '_teksttv_date_end', $date_end);

            $days = array_map('sanitize_text_field', wp_unslash($_POST['teksttv_days'] ?? []));
            update_post_meta($post_id, '_teksttv_days', $days);
        }

        // Extra images (only save if feature enabled)
        if (Helpers::has_feature('extra_images')) {
            $images = array_map('absint', wp_unslash($_POST['teksttv_images'] ?? []));
            $images = array_filter($images);
            update_post_meta($post_id, '_teksttv_images', $images);
        }

        // Sidebar image (only save if feature enabled)
        if (Helpers::has_feature('sidebar_image')) {
            $sidebar_raw = sanitize_text_field(wp_unslash($_POST['teksttv_sidebar_image'] ?? ''));
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
