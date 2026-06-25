<?php

namespace TekstTV;

class AdminPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_menu(): void
    {
        // Main menu item — first loop channel or settings if no channels
        $channels = Helpers::get_channels();
        $first_channel = $channels[0]['slug'] ?? '';

        add_menu_page(
            __('Tekst TV', 'teksttv-wp-plugin'),
            __('Tekst TV', 'teksttv-wp-plugin'),
            'manage_teksttv',
            'teksttv',
            $first_channel ? [self::class, 'render_loop_page'] : [self::class, 'render_settings_page'],
            'dashicons-desktop',
            30
        );

        // Submenu per channel loop
        foreach ($channels as $ch) {
            $loop_label = count($channels) > 1 ? sprintf(/* translators: %s: channel label */ __('Loop: %s', 'teksttv-wp-plugin'), $ch['label']) : __('Loop', 'teksttv-wp-plugin');
            add_submenu_page(
                'teksttv',
                $loop_label,
                $loop_label,
                'manage_teksttv',
                'teksttv-loop-' . $ch['slug'],
                [self::class, 'render_loop_page']
            );
        }

        // Settings submenu
        add_submenu_page(
            'teksttv',
            __('Instellingen', 'teksttv-wp-plugin'),
            __('Instellingen', 'teksttv-wp-plugin'),
            'manage_teksttv',
            'teksttv-settings',
            [self::class, 'render_settings_page']
        );

        // AI prompts submenu (separate capability)
        if (Helpers::has_feature('ai_generate')) {
            add_submenu_page(
                'teksttv',
                __('Content & AI', 'teksttv-wp-plugin'),
                __('Content & AI', 'teksttv-wp-plugin'),
                'manage_teksttv_content',
                'teksttv-content',
                [self::class, 'render_prompts_page']
            );
        }

        // Remove the auto-generated duplicate submenu
        remove_submenu_page('teksttv', 'teksttv');
    }

    public static function register_settings(): void
    {
        register_setting('teksttv_settings', 'teksttv_channels', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_channels'],
            'default' => [],
        ]);

        register_setting('teksttv_settings', 'teksttv_preview_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);

        register_setting('teksttv_settings', 'teksttv_default_end_days', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 7,
        ]);

        register_setting('teksttv_settings', 'teksttv_max_post_age', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30,
        ]);

        register_setting('teksttv_settings', 'teksttv_duration_text', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20,
        ]);

        register_setting('teksttv_settings', 'teksttv_duration_image', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 7,
        ]);

        register_setting('teksttv_settings', 'teksttv_openweather_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('teksttv_settings', 'teksttv_features', [
            'type' => 'array',
            'sanitize_callback' => function ($input) {
                if (!is_array($input)) {
                    return [];
                }
                return array_map('sanitize_key', $input);
            },
            'default' => ['custom_title', 'sidebar_image', 'extra_images', 'scheduling', 'page_separator', 'bold', 'italic', 'underline', 'lists'],
        ]);

        register_setting('teksttv_content', 'teksttv_ai_prompts', [
            'type' => 'array',
            'sanitize_callback' => function ($input) {
                if (!is_array($input)) {
                    return [];
                }
                return [
                    'system' => sanitize_textarea_field($input['system'] ?? ''),
                    'prompt_title' => sanitize_textarea_field($input['prompt_title'] ?? ''),
                    'prompt_body' => sanitize_textarea_field($input['prompt_body'] ?? ''),
                    'word_limit' => max(10, absint($input['word_limit'] ?? 100)),
                    'title_char_limit' => max(10, absint($input['title_char_limit'] ?? 40)),
                    'min_input_words' => max(0, absint($input['min_input_words'] ?? 50)),
                    'max_retries' => max(1, min(5, absint($input['max_retries'] ?? 3))),
                    'rate_limit' => max(1, min(60, absint($input['rate_limit'] ?? 10))),
                    'region_taxonomy' => sanitize_key($input['region_taxonomy'] ?? ''),
                    'provider' => sanitize_key($input['provider'] ?? ''),
                    'model' => sanitize_text_field($input['model'] ?? ''),
                    'temperature' => $input['temperature'] !== '' ? max(0, min(2, (float) $input['temperature'])) : '',
                    'top_p' => $input['top_p'] !== '' ? max(0, min(1, (float) $input['top_p'])) : '',
                    'max_tokens' => max(64, min(8192, absint($input['max_tokens'] ?? 2048))),
                ];
            },
            'default' => [],
        ]);

        register_setting('teksttv_settings', 'teksttv_enabled_taxonomies', [
            'type' => 'array',
            'sanitize_callback' => function ($input) {
                return is_array($input) ? array_map('sanitize_key', $input) : [];
            },
            'default' => ['category'],
        ]);
    }

    /**
     * @param mixed $input
     * @return list<array{slug: string, label: string}>
     */
    public static function sanitize_channels(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $channels = [];
        foreach ($input as $channel) {
            $slug = sanitize_key($channel['slug'] ?? '');
            $label = sanitize_text_field($channel['label'] ?? '');
            if (!empty($slug) && !empty($label)) {
                $channels[] = ['slug' => $slug, 'label' => $label];
            }
        }
        return $channels;
    }

    public static function enqueue_assets(string $hook): void
    {
        // Load on any Tekst TV admin page
        if (strpos($hook, 'teksttv') === false) {
            return;
        }

        wp_enqueue_style(
            'teksttv-tomselect',
            TEKSTTV_PLUGIN_URL . 'assets/tom-select.default.min.css',
            [],
            Helpers::asset_version('assets/tom-select.default.min.css')
        );
        wp_enqueue_script(
            'teksttv-tomselect',
            TEKSTTV_PLUGIN_URL . 'assets/tom-select.complete.min.js',
            [],
            Helpers::asset_version('assets/tom-select.complete.min.js'),
            true
        );
        Helpers::enqueue_admin_script(['teksttv-tomselect'], ['teksttv-tomselect']);
    }

    /**
     * Get the channel slug from the current admin page.
     */
    private static function get_current_channel(): string
    {
        $page = sanitize_key($_GET['page'] ?? '');

        if (str_starts_with($page, 'teksttv-loop-')) {
            return substr($page, strlen('teksttv-loop-'));
        }

        // Fallback: first channel (for the main teksttv page)
        $channels = Helpers::get_channels();
        return $channels[0]['slug'] ?? '';
    }

    // =========================================================================
    // Loop page
    // =========================================================================

    public static function render_loop_page(): void
    {
        $channel_slug = self::get_current_channel();
        if (empty($channel_slug)) {
            echo '<div class="wrap"><h1>' . esc_html__('Tekst TV', 'teksttv-wp-plugin') . '</h1>';
            echo '<p>' . wp_kses(sprintf(/* translators: %s: settings page URL */ __('Ga naar <a href="%s">Instellingen</a> om eerst een kanaal toe te voegen.', 'teksttv-wp-plugin'), esc_url(admin_url('admin.php?page=teksttv-settings'))), ['a' => ['href' => []]]) . '</p>';
            echo '</div>';
            return;
        }

        // Handle loop save via POST
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in handle_loop_save()
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teksttv_loop_nonce'])) {
            self::handle_loop_save();
        }

        $channels = Helpers::get_channels();
        $channel_label = '';
        foreach ($channels as $ch) {
            if ($ch['slug'] === $channel_slug) {
                $channel_label = $ch['label'];
                break;
            }
        }

        $blocks = Helpers::get_loop_config($channel_slug);
        $api_url = rest_url('teksttv/v1/slides?channel=' . $channel_slug);
        $page_title = count($channels) > 1 ? sprintf(/* translators: %s: channel label */ __('Loop: %s', 'teksttv-wp-plugin'), $channel_label) : __('Loop', 'teksttv-wp-plugin');
        $ticker_items = get_option('teksttv_ticker_' . $channel_slug, []);

        include TEKSTTV_PLUGIN_DIR . 'src/views/loop-page.php';
    }

    // =========================================================================
    // Settings page (channels + preview URL)
    // =========================================================================

    public static function render_settings_page(): void
    {
        $channels = Helpers::get_channels();
        $features = Helpers::get_features();
        $all_taxonomies = self::get_post_taxonomies_static();
        $enabled_taxonomies = get_option('teksttv_enabled_taxonomies', ['category']);

        include TEKSTTV_PLUGIN_DIR . 'src/views/settings-page.php';
    }

    // =========================================================================
    // AI Settings page
    // =========================================================================

    public static function render_prompts_page(): void
    {
        $prompts = Helpers::get_ai_prompts();
        $all_taxonomies = self::get_post_taxonomies_static();
        $ai_models = Helpers::get_ai_models();

        include TEKSTTV_PLUGIN_DIR . 'src/views/prompts-page.php';
    }

    // =========================================================================
    // Block rendering
    // =========================================================================

    /**
     * Get all public taxonomies that apply to posts. Cached per request.
     *
     * @return list<array{name: string, label: string, terms: array<int, string>}>
     */
    public static function get_post_taxonomies_static(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $tax_names = get_object_taxonomies('post');
        $result = [];

        foreach ($tax_names as $tax_name) {
            $tax = get_taxonomy($tax_name);
            if (!$tax || !$tax->public || $tax->name === 'post_format') {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $tax->name,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $term_options = [];
            foreach ($terms as $term) {
                $term_options[$term->term_id] = $term->name;
            }

            $result[] = [
                'name' => $tax->name,
                'label' => $tax->labels->singular_name,
                'terms' => $term_options,
            ];
        }

        $cache = $result;
        return $result;
    }

    /**
     * Render a loop or ticker block using the registry.
     *
     * @param array<string, mixed> $block
     */
    public static function render_block_generic(int|string $index, array $block, string $prefix = 'teksttv_blocks'): void
    {
        $type = $block['type'] ?? '';
        $reg = BlockRegistry::get($type);
        if (!$reg) {
            return;
        }

        ?>
        <div class="teksttv-block" data-type="<?php echo esc_attr($type); ?>">
            <div class="teksttv-block-header">
                <span class="teksttv-block-handle dashicons dashicons-move"></span>
                <span class="teksttv-block-icon" style="background:<?php echo esc_attr($reg['color']); ?>"><span class="dashicons dashicons-<?php echo esc_attr($reg['icon']); ?>"></span></span>
                <span class="teksttv-block-title"><?php echo esc_html($reg['label']); ?></span>
                <span class="teksttv-block-summary"></span>
                <span class="teksttv-block-toggle dashicons dashicons-arrow-down-alt2"></span>
                <button type="button" class="button-link teksttv-remove-block"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="teksttv-block-body">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][type]" value="<?php echo esc_attr($type); ?>" />
                <?php BlockRegistry::render($type, $index, $block, $prefix); ?>
                <?php self::render_scheduling_fields($index, $block, $prefix); ?>
            </div>
        </div>
        <?php
    }

    /** @param array<string, mixed> $block */
    private static function render_scheduling_fields(int|string $index, array $block, string $prefix = 'teksttv_blocks'): void
    {
        $date_start = $block['date_start'] ?? '';
        $date_end = $block['date_end'] ?? '';
        $days = $block['days'] ?? [];
        $has_scheduling = !empty($date_start) || !empty($date_end) || !empty($days);
        $day_labels = Helpers::get_day_labels();

        ?>
        <div class="teksttv-block-scheduling-toggle">
            <label>
                <input type="checkbox" class="teksttv-scheduling-checkbox" <?php checked($has_scheduling); ?> />
                <?php esc_html_e('Planning inschakelen', 'teksttv-wp-plugin'); ?>
            </label>
        </div>
        <div class="teksttv-block-fields teksttv-block-fields--scheduling" <?php echo $has_scheduling ? '' : 'style="display:none;"'; ?>>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Vanaf', 'teksttv-wp-plugin'); ?></label>
                <input type="date" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_start]" value="<?php echo esc_attr($date_start); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Tot en met', 'teksttv-wp-plugin'); ?></label>
                <input type="date" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_end]" value="<?php echo esc_attr($date_end); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Dagen', 'teksttv-wp-plugin'); ?></label>
                <div class="teksttv-days-row">
                    <?php foreach ($day_labels as $num => $label) : ?>
                    <label class="teksttv-day-toggle">
                        <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][days][]" value="<?php echo esc_attr((string) $num); ?>" <?php checked(empty($days) || in_array((string) $num, $days, true)); ?> />
                        <span><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Save handler
    // =========================================================================

    private static function handle_loop_save(): void
    {
        if (!isset($_POST['teksttv_loop_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_loop_nonce'])), 'teksttv_save_loop')) {
            return;
        }

        if (!current_user_can('manage_teksttv')) {
            return;
        }

        $channel = sanitize_key(wp_unslash($_POST['teksttv_loop_channel'] ?? ''));
        if (empty($channel)) {
            return;
        }

        // Validate channel exists
        $valid_slugs = array_column(Helpers::get_channels(), 'slug');
        if (!in_array($channel, $valid_slugs, true)) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually below
        $raw_blocks = isset($_POST['teksttv_blocks']) ? wp_unslash($_POST['teksttv_blocks']) : [];
        $blocks = [];

        foreach ($raw_blocks as $block) {
            $type = sanitize_key($block['type'] ?? '');
            $saved = BlockRegistry::save($type, $block);
            if ($saved) {
                $saved = array_merge($saved, self::extract_scheduling_fields($block));
                $blocks[] = $saved;
            }
        }

        update_option('teksttv_loop_' . $channel, $blocks);

        // Save ticker items
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below
        $raw_ticker = isset($_POST['teksttv_ticker']) ? wp_unslash($_POST['teksttv_ticker']) : [];
        $ticker = [];
        foreach ($raw_ticker as $item) {
            $type = sanitize_key($item['type'] ?? '');
            $saved_ticker = BlockRegistry::save($type, $item);
            if ($saved_ticker) {
                $saved_ticker = array_merge($saved_ticker, self::extract_scheduling_fields($item));
                $ticker[] = $saved_ticker;
            }
        }
        update_option('teksttv_ticker_' . $channel, $ticker);

        RestApi::invalidate_slides_cache($channel);

        add_settings_error('teksttv-wp-plugin', 'loop_saved', __('Loop configuratie opgeslagen.', 'teksttv-wp-plugin'), 'success');
    }

    /**
     * Extract and save scheduling fields (date_start, date_end, days) from a block.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function extract_scheduling_fields(array $raw): array
    {
        $fields = [];

        $ds = sanitize_text_field($raw['date_start'] ?? '');
        $de = sanitize_text_field($raw['date_end'] ?? '');
        if ($ds !== '') {
            $fields['date_start'] = $ds;
        }
        if ($de !== '') {
            $fields['date_end'] = $de;
        }

        $sanitized_days = Helpers::sanitize_days_input($raw['days'] ?? null);
        if ($sanitized_days !== null) {
            $fields['days'] = $sanitized_days;
        }

        return $fields;
    }
}
