<?php

namespace TekstTV;

class AdminPage
{
    private const ORIGIN_DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_notices', [self::class, 'render_preview_origin_notice']);
    }

    /**
     * Whether the preview URL shares the site's HTTP(S) origin.
     *
     * The preview iframes run with sandbox="allow-scripts allow-same-origin",
     * which is safe for a preview app on a separate origin but effectively
     * disables the sandbox when the preview is same-origin as WordPress.
     */
    public static function preview_url_shares_site_origin(string $preview_url, string $site_url): bool
    {
        $preview_origin = self::parse_http_origin($preview_url);

        return $preview_origin !== null && $preview_origin === self::parse_http_origin($site_url);
    }

    /**
     * @return array{scheme: string, host: string, port: int}|null
     */
    private static function parse_http_origin(string $url): ?array
    {
        // parse_url() replaces bytes 0x80-0x9F and 0xAD in hosts with "_",
        // corrupting raw UTF-8 (e.g. the 0x9F in "ß"), so percent-encode
        // high bytes first and decode the host after parsing.
        $encoded_url = preg_replace_callback(
            '/[\x80-\xFF]/',
            static fn (array $matches): string => rawurlencode($matches[0]),
            $url
        );
        $parts = wp_parse_url($encoded_url ?? $url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = rawurldecode($parts['host'] ?? '');
        if ($host === '' || !isset(self::ORIGIN_DEFAULT_PORTS[$scheme])) {
            return null;
        }

        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            $ascii_host = idn_to_ascii(
                $host,
                IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            );
            if ($ascii_host === false) {
                return null;
            }

            $host = $ascii_host;
        }

        return [
            'scheme' => $scheme,
            'host' => strtolower($host),
            'port' => $parts['port'] ?? self::ORIGIN_DEFAULT_PORTS[$scheme],
        ];
    }

    /**
     * Warn admins when the configured preview URL is same-origin as the site,
     * which weakens the preview iframe sandbox.
     */
    public static function render_preview_origin_notice(): void
    {
        $screen = get_current_screen();
        if (!$screen || !str_contains($screen->id, 'teksttv')) {
            return;
        }
        if (!self::preview_url_shares_site_origin(Helpers::get_preview_url(), home_url())) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html('De TekstTV preview-URL draait op dezelfde origin als deze site. Gebruik een aparte origin voor de preview, anders is de iframe-sandbox effectief uitgeschakeld (XSS/CSRF-risico).')
        );
    }

    public static function register_menu(): void
    {
        // Main menu item — first loop channel or settings if no channels
        $channels = Helpers::get_channels();
        $first_channel = $channels[0]['slug'] ?? '';

        add_menu_page(
            'Tekst TV',
            'Tekst TV',
            'manage_teksttv',
            'teksttv',
            $first_channel ? [self::class, 'render_loop_page'] : [self::class, 'render_settings_page'],
            'dashicons-desktop',
            30
        );

        // Submenu per channel loop
        foreach ($channels as $ch) {
            $loop_label = count($channels) > 1 ? sprintf('Loop: %s', $ch['label']) : 'Loop';
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
            'Instellingen',
            'Instellingen',
            'manage_teksttv',
            'teksttv-settings',
            [self::class, 'render_settings_page']
        );

        // AI prompts submenu (separate capability)
        if (Helpers::has_feature('ai_generate')) {
            add_submenu_page(
                'teksttv',
                'Inhoud & AI',
                'Inhoud & AI',
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
        // Align the save capability (checked by options.php) with the view capability of each page
        add_filter('option_page_capability_teksttv_settings', static fn() => 'manage_teksttv');
        add_filter('option_page_capability_teksttv_content', static fn() => 'manage_teksttv_content');

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
            'sanitize_callback' => fn($v) => Helpers::clamp_int($v, 0, 365),
            'default' => 7,
        ]);

        register_setting('teksttv_settings', 'teksttv_max_post_age', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => Helpers::clamp_int($v, 0, 365),
            'default' => 30,
        ]);

        foreach (Helpers::DURATION_DEFAULTS as $duration_option => $duration_default) {
            register_setting('teksttv_settings', $duration_option, [
                'type' => 'integer',
                'sanitize_callback' => fn($v) => Helpers::clamp_int($v, 1, 120),
                'default' => $duration_default,
            ]);
        }

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
            'default' => Helpers::DEFAULT_FEATURES,
        ]);

        register_setting('teksttv_content', 'teksttv_ai_prompts', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_ai_prompts'],
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
        $seen = [];
        foreach ($input as $channel) {
            $slug = sanitize_key($channel['slug'] ?? '');
            $label = sanitize_text_field($channel['label'] ?? '');
            if (empty($slug) || empty($label)) {
                continue;
            }
            // Loop/ticker option names are keyed by slug, so a duplicate slug
            // would make two channels share storage. Keep the first, reject the rest.
            if (isset($seen[$slug])) {
                add_settings_error(
                    'teksttv-wp-plugin',
                    'duplicate_channel_slug',
                    sprintf(
                        'Kanaal-slug "%s" komt meerdere keren voor; alleen de eerste is bewaard.',
                        $slug
                    ),
                    'error'
                );
                continue;
            }
            $seen[$slug] = true;
            $channels[] = ['slug' => $slug, 'label' => $label];
        }
        return $channels;
    }

    /**
     * Sanitize the AI prompts option.
     *
     * Merges permitted submitted values over the stored option so a partial form
     * cannot clear fields it never displayed. Region and technical fields are
     * also protected here because hiding them in the UI is not authorization.
     *
     * @param mixed $input
     * @return array<string, mixed>
     */
    public static function sanitize_ai_prompts(mixed $input): array
    {
        $current = get_option('teksttv_ai_prompts', []);
        if (!is_array($current)) {
            $current = [];
        }
        if (!is_array($input)) {
            return $current;
        }

        if (!current_user_can('manage_teksttv')) {
            $input = array_diff_key($input, array_flip([
                'region_taxonomy',
                'provider',
                'model',
                'temperature',
                'top_p',
                'max_tokens',
            ]));
        }

        $merged = array_merge($current, $input);

        return array_merge([
            'system' => sanitize_textarea_field($merged['system'] ?? ''),
            'prompt_title' => sanitize_textarea_field($merged['prompt_title'] ?? ''),
            'prompt_body' => sanitize_textarea_field($merged['prompt_body'] ?? ''),
            'region_taxonomy' => sanitize_key($merged['region_taxonomy'] ?? ''),
            'provider' => sanitize_key($merged['provider'] ?? ''),
            'model' => sanitize_text_field($merged['model'] ?? ''),
        ], Helpers::normalize_ai_prompt_limits($merged));
    }

    public static function enqueue_assets(string $hook): void
    {
        // Load on any Tekst TV admin page
        if (!str_contains($hook, 'teksttv')) {
            return;
        }

        Helpers::enqueue_admin_script();
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
            echo '<div class="wrap teksttv-admin"><h1>' . esc_html('Tekst TV') . '</h1>';
            echo '<p>' . wp_kses(sprintf('Ga naar <a href="%s">Instellingen</a> om eerst een kanaal toe te voegen.', esc_url(admin_url('admin.php?page=teksttv-settings'))), ['a' => ['href' => []]]) . '</p>';
            echo '</div>';
            return;
        }

        // Handle loop save via POST
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in handle_loop_save()
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teksttv_loop_nonce'])) {
            self::handle_loop_save();
        }

        $channels = Helpers::get_channels();
        $channel_label = array_column($channels, 'label', 'slug')[$channel_slug] ?? '';

        $blocks = Helpers::get_loop_config($channel_slug);
        $page_title = sprintf('Kanaal: %s', $channel_label ?: $channel_slug);
        $ticker_items = Helpers::get_ticker_config($channel_slug);

        include TEKSTTV_PLUGIN_DIR . 'src/views/loop-page.php';
    }

    // =========================================================================
    // Settings page (channels + preview URL)
    // =========================================================================

    public static function render_settings_page(): void
    {
        $channels = Helpers::get_channels();
        $api_base_url = rest_url('teksttv/v1/slides');
        $features = Helpers::get_features();
        $all_taxonomies = Helpers::get_post_taxonomies();
        $enabled_taxonomies = get_option('teksttv_enabled_taxonomies', ['category']);

        include TEKSTTV_PLUGIN_DIR . 'src/views/settings-page.php';
    }

    // =========================================================================
    // AI Settings page
    // =========================================================================

    public static function render_prompts_page(): void
    {
        $prompts = Helpers::get_ai_prompts();
        $all_taxonomies = Helpers::get_post_taxonomies();
        $ai_models = Helpers::get_ai_models();

        include TEKSTTV_PLUGIN_DIR . 'src/views/prompts-page.php';
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
        $body_id = str_replace('_', '-', $prefix) . '-' . (string) $index . '-body';

        ?>
        <div class="teksttv-block" data-type="<?php echo esc_attr($type); ?>">
            <?php self::render_block_header($body_id, $reg['label'], $reg['icon'], $reg['color'], sprintf('Verwijder blok %s', $reg['label'])); ?>
            <div class="teksttv-block-body" id="<?php echo esc_attr($body_id); ?>" style="display:none;">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][type]" value="<?php echo esc_attr($type); ?>" />
                <?php BlockRegistry::render($type, $index, $block, $prefix); ?>
                <?php self::render_scheduling_fields($index, $block, $prefix); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the shared block header: drag handle, accordion toggle wired to
     * the body via `$body_id`, and the remove button. The classes and ARIA
     * wiring are a contract with the workbench JS; keep every accordion
     * (loop, ticker, campaigns) on this one renderer.
     */
    public static function render_block_header(string $body_id, string $title, string $icon, string $color, string $remove_label): void
    {
        ?>
        <div class="teksttv-block-header">
            <span class="teksttv-block-handle dashicons dashicons-move" aria-hidden="true"></span>
            <button type="button" class="teksttv-block-toggle-control" aria-expanded="false" aria-controls="<?php echo esc_attr($body_id); ?>">
                <span class="teksttv-block-icon" style="background:<?php echo esc_attr($color); ?>" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span></span>
                <span class="teksttv-block-title"><?php echo esc_html($title); ?></span>
                <span class="teksttv-block-summary"></span>
                <span class="teksttv-block-toggle dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </button>
            <button type="button" class="button-link teksttv-block-order-control teksttv-move-block-up" aria-label="<?php echo esc_attr(sprintf('%s omhoog verplaatsen', $title)); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
            <button type="button" class="button-link teksttv-block-order-control teksttv-move-block-down" aria-label="<?php echo esc_attr(sprintf('%s omlaag verplaatsen', $title)); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
            <button type="button" class="button-link teksttv-remove-block" aria-label="<?php echo esc_attr($remove_label); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
        </div>
        <?php
    }

    /**
     * Render the collapsible scheduling section for a loop/ticker block: the
     * enable checkbox plus the date+days inputs, hidden until scheduling is
     * set. The checkbox and --scheduling class are a contract with the
     * scheduling toggle in the admin JS.
     *
     * @param array<string, mixed> $block
     */
    public static function render_scheduling_fields(int|string $index, array $block, string $prefix = 'teksttv_blocks'): void
    {
        $days = Helpers::normalize_days($block['days'] ?? null);
        $has_scheduling = !empty($block['date_start']) || !empty($block['date_end']) || $days !== null;

        ?>
        <div class="teksttv-block-scheduling-toggle">
            <label>
                <input type="checkbox" class="teksttv-scheduling-checkbox" <?php checked($has_scheduling); ?> />
                <?php echo esc_html('Planning inschakelen'); ?>
            </label>
        </div>
        <div class="teksttv-field-grid teksttv-field-grid--scheduling" <?php echo $has_scheduling ? '' : 'style="display:none;"'; ?>>
            <?php self::render_scheduling_inputs($index, $block, $prefix); ?>
        </div>
        <?php
    }

    /**
     * Render the bare date-range + weekday inputs for a block-shaped item,
     * without the toggle chrome. Callers provide their own container (the
     * campaigns page renders these always-visible).
     *
     * @param array<string, mixed> $block
     */
    public static function render_scheduling_inputs(int|string $index, array $block, string $prefix): void
    {
        $date_start = $block['date_start'] ?? '';
        $date_end = $block['date_end'] ?? '';
        $date_start_id = Helpers::field_id($prefix, $index, 'date-start');
        $date_end_id = Helpers::field_id($prefix, $index, 'date-end');

        ?>
        <div class="teksttv-field">
            <label for="<?php echo esc_attr($date_start_id); ?>" data-teksttv-label="date-start"><?php echo esc_html('Vanaf'); ?></label>
            <input type="date" id="<?php echo esc_attr($date_start_id); ?>" data-teksttv-field="date-start" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_start]" value="<?php echo esc_attr($date_start); ?>" />
        </div>
        <div class="teksttv-field">
            <label for="<?php echo esc_attr($date_end_id); ?>" data-teksttv-label="date-end"><?php echo esc_html('Tot en met'); ?></label>
            <input type="date" id="<?php echo esc_attr($date_end_id); ?>" data-teksttv-field="date-end" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_end]" value="<?php echo esc_attr($date_end); ?>" />
        </div>
        <div class="teksttv-field teksttv-field--primary">
            <span class="teksttv-field-label"><?php echo esc_html('Dagen'); ?></span>
            <?php self::render_days_row($prefix . '[' . $index . '][days][]', Helpers::normalize_days($block['days'] ?? null)); ?>
        </div>
        <?php
    }

    /**
     * Render the weekday checkbox row. Null means "all days"; an empty list
     * means no days are selected.
     *
     * @param list<string>|null $days Selected ISO day numbers ('1'..'7').
     */
    public static function render_days_row(string $field_name, ?array $days): void
    {
        ?>
        <div class="teksttv-days-row">
            <?php foreach (Helpers::get_day_labels() as $num => $label) : ?>
            <label class="teksttv-day-toggle">
                <input type="checkbox" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr((string) $num); ?>" <?php checked($days === null || in_array((string) $num, $days, true)); ?> />
                <span><?php echo esc_html($label); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    // =========================================================================
    // Save handler
    // =========================================================================

    private static function validate_loop_save_request(): ?string
    {
        if (!isset($_POST['teksttv_loop_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_loop_nonce'])), 'teksttv_save_loop')) {
            add_settings_error('teksttv', 'loop_nonce_failed', 'Beveiligingscontrole mislukt; wijzigingen zijn niet opgeslagen. Vernieuw de pagina en probeer het opnieuw.');
            return null;
        }

        if (!current_user_can('manage_teksttv')) {
            add_settings_error('teksttv', 'loop_no_permission', 'Onvoldoende rechten; wijzigingen zijn niet opgeslagen.');
            return null;
        }

        $channel = sanitize_key(wp_unslash($_POST['teksttv_loop_channel'] ?? ''));
        if (empty($channel) || !in_array($channel, Helpers::channel_slugs(), true)) {
            add_settings_error('teksttv', 'loop_unknown_channel', 'Onbekend kanaal; wijzigingen zijn niet opgeslagen.');
            return null;
        }

        return $channel;
    }

    private static function handle_loop_save(): void
    {
        $channel = self::validate_loop_save_request();
        if ($channel === null) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce is verified in validate_loop_save_request(); fields are sanitized individually in sanitize_registry_items()
        $raw_blocks = isset($_POST['teksttv_blocks']) && is_array($_POST['teksttv_blocks']) ? wp_unslash($_POST['teksttv_blocks']) : [];
        [$blocks, $blocks_preserved] = self::sanitize_registry_items($raw_blocks, 'teksttv_loop_' . $channel, 'loop');
        update_option('teksttv_loop_' . $channel, $blocks);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce is verified in validate_loop_save_request(); fields are sanitized individually in sanitize_registry_items()
        $raw_ticker = isset($_POST['teksttv_ticker']) && is_array($_POST['teksttv_ticker']) ? wp_unslash($_POST['teksttv_ticker']) : [];
        [$ticker, $ticker_preserved] = self::sanitize_registry_items($raw_ticker, 'teksttv_ticker_' . $channel, 'ticker');
        update_option('teksttv_ticker_' . $channel, $ticker);

        if ($blocks_preserved || $ticker_preserved) {
            add_settings_error('teksttv', 'loop_preserved_unknown', 'Sommige opgeslagen blokken horen bij een niet-actieve plugin. Ze zijn bewaard maar verschijnen pas weer als die plugin actief is.', 'warning');
        }

        add_settings_error('teksttv', 'loop_saved', 'Loop configuratie opgeslagen.', 'success');
    }

    /**
     * Sanitize submitted registry items and restore stored items whose type is
     * no longer registered (for example because an add-on was deactivated) at
     * their prior positions.
     *
     * @param array<int|string, mixed> $raw_items Unslashed POST items.
     * @param string                   $option_name Stored option name to read preserved rows from.
     * @param string                   $context   Target registry context.
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function sanitize_registry_items(array $raw_items, string $option_name, string $context): array
    {
        $items = [];
        $allowed = BlockRegistry::all($context);
        foreach ($raw_items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = sanitize_key($item['type'] ?? '');
            if (!isset($allowed[$type])) {
                continue;
            }
            $saved = BlockRegistry::save($type, $item);
            if ($saved) {
                $items[] = array_merge($saved, Helpers::extract_scheduling_fields($item));
            }
        }

        $preserved = false;
        $existing = get_option($option_name, []);
        foreach (is_array($existing) ? array_values($existing) : [] as $index => $existing_item) {
            $existing_type = is_array($existing_item) ? (string) ($existing_item['type'] ?? '') : '';
            if ($existing_type !== '' && BlockRegistry::get($existing_type) === null) {
                array_splice($items, min($index, count($items)), 0, [$existing_item]);
                $preserved = true;
            }
        }

        return [$items, $preserved];
    }
}
