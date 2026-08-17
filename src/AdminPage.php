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
     * Whether the preview URL weakens its sandbox by sharing the site origin.
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
        // Preserve UTF-8 hosts: parse_url() corrupts some raw high bytes.
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
     * Warn when a same-origin preview weakens the iframe sandbox.
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

        add_submenu_page(
            'teksttv',
            'Instellingen',
            'Instellingen',
            'manage_teksttv',
            'teksttv-settings',
            [self::class, 'render_settings_page']
        );

        // Model discovery may call remote providers.
        if (current_user_can('manage_teksttv_content') && Helpers::ai_supported()) {
            add_submenu_page(
                'teksttv',
                'Inhoud & AI',
                'Inhoud & AI',
                'manage_teksttv_content',
                'teksttv-content',
                [self::class, 'render_prompts_page']
            );
        }

        remove_submenu_page('teksttv', 'teksttv');
    }

    public static function register_settings(): void
    {
        // options.php must use the page's view capability.
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
                'sanitize_callback' => fn($v) => Helpers::clamp_int($v, Helpers::DURATION_MIN_SECONDS, Helpers::DURATION_MAX_SECONDS),
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
            // Duplicate slugs would share loop and ticker options.
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
     * Preserve hidden fields on partial forms; authorize privileged fields here.
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
        if (!str_contains($hook, 'teksttv')) {
            return;
        }

        Helpers::enqueue_admin_script();
    }

    private static function get_current_channel(): string
    {
        $page = sanitize_key($_GET['page'] ?? '');

        if (str_starts_with($page, 'teksttv-loop-')) {
            return substr($page, strlen('teksttv-loop-'));
        }

        $channels = Helpers::get_channels();
        return $channels[0]['slug'] ?? '';
    }

    public static function render_loop_page(): void
    {
        $channel_slug = self::get_current_channel();
        if (empty($channel_slug)) {
            echo '<div class="wrap teksttv-admin"><h1>' . esc_html('Tekst TV') . '</h1>';
            echo '<p>' . wp_kses(sprintf('Ga naar <a href="%s">Instellingen</a> om eerst een kanaal toe te voegen.', esc_url(admin_url('admin.php?page=teksttv-settings'))), ['a' => ['href' => []]]) . '</p>';
            echo '</div>';
            return;
        }

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

    public static function render_settings_page(): void
    {
        $channels = Helpers::get_channels();
        $api_base_url = rest_url('teksttv/v1/slides');
        $features = Helpers::get_features();
        $all_taxonomies = Helpers::get_post_taxonomies();
        $enabled_taxonomies = get_option('teksttv_enabled_taxonomies', ['category']);

        include TEKSTTV_PLUGIN_DIR . 'src/views/settings-page.php';
    }

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
        $body_id = Helpers::field_id($prefix, $index, 'body');

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
     * Render the CSS-hidden empty state kept inside repeatable lists.
     */
    public static function render_empty_state(
        string $icon,
        string $title,
        string $description = ''
    ): void {
        ?>
        <div class="teksttv-empty-state">
            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <h3 class="teksttv-empty-state-title"><?php echo esc_html($title); ?></h3>
            <?php if ($description !== '') : ?>
            <p><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_block_section_start(string $title, string $description = '', string $modifier = ''): void
    {
        $classes = 'teksttv-block-section';
        if ($modifier !== '') {
            $classes .= ' teksttv-block-section--' . sanitize_html_class($modifier);
        }

        ?>
        <section class="<?php echo esc_attr($classes); ?>">
            <div class="teksttv-block-section-heading">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if ($description !== '') : ?>
                <p><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
        <?php
    }

    public static function render_block_section_end(): void
    {
        echo '</section>';
    }

    public static function render_form_actions(): void
    {
        submit_button('Wijzigingen opslaan');
    }

    /**
     * Render the block header shared with the workbench JS.
     */
    public static function render_block_header(
        string $body_id,
        string $title,
        string $icon,
        string $color,
        string $remove_label,
        bool $summary_hidden = false
    ): void {
        ?>
        <div class="teksttv-block-header">
            <span class="teksttv-block-handle dashicons dashicons-move" aria-hidden="true"></span>
            <button type="button" class="teksttv-block-toggle-control" aria-expanded="false" aria-controls="<?php echo esc_attr($body_id); ?>">
                <span class="teksttv-block-icon" style="background:<?php echo esc_attr($color); ?>" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span></span>
                <span class="teksttv-block-title"><?php echo esc_html($title); ?></span>
                <span class="teksttv-block-summary" aria-hidden="<?php echo esc_attr($summary_hidden ? 'true' : 'false'); ?>"></span>
                <span class="teksttv-block-toggle dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </button>
            <details class="teksttv-block-actions" name="teksttv-block-actions">
                <summary class="teksttv-block-actions-toggle" aria-label="<?php echo esc_attr(sprintf('Acties voor %s', $title)); ?>"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span></summary>
                <div class="teksttv-block-actions-menu">
                    <button type="button" class="teksttv-block-order-control teksttv-move-block-up"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span><?php echo esc_html('Omhoog verplaatsen'); ?></button>
                    <button type="button" class="teksttv-block-order-control teksttv-move-block-down"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><?php echo esc_html('Omlaag verplaatsen'); ?></button>
                    <button type="button" class="teksttv-remove-block"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php echo esc_html($remove_label); ?></button>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Render scheduling controls bound to the admin-JS toggle.
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
                <span class="teksttv-block-scheduling-copy">
                    <strong><?php echo esc_html('Planning'); ?></strong>
                    <span><?php echo esc_html('Alleen tonen binnen een periode of op vaste dagen.'); ?></span>
                </span>
            </label>
        </div>
        <div class="teksttv-field-grid teksttv-field-grid--scheduling" <?php echo $has_scheduling ? '' : 'style="display:none;"'; ?>>
            <?php self::render_scheduling_inputs($index, $block, $prefix); ?>
        </div>
        <?php
    }

    /**
     * Render date and weekday fields without a scheduling toggle.
     *
     * @param array<string, mixed> $block
     */
    public static function render_scheduling_inputs(int|string $index, array $block, string $prefix): void
    {
        $date_start = $block['date_start'] ?? '';
        $date_end = $block['date_end'] ?? '';

        ?>
        <div class="teksttv-field">
            <label <?php Helpers::field_for($prefix, $index, 'date_start'); ?>><?php echo esc_html('Vanaf'); ?></label>
            <input type="date" <?php Helpers::field_attrs($prefix, $index, 'date_start'); ?> value="<?php echo esc_attr($date_start); ?>" />
        </div>
        <div class="teksttv-field">
            <label <?php Helpers::field_for($prefix, $index, 'date_end'); ?>><?php echo esc_html('Tot en met'); ?></label>
            <input type="date" <?php Helpers::field_attrs($prefix, $index, 'date_end'); ?> value="<?php echo esc_attr($date_end); ?>" />
        </div>
        <div class="teksttv-field teksttv-field--primary">
            <span class="teksttv-field-label"><?php echo esc_html('Dagen'); ?></span>
            <?php self::render_days_row($prefix . '[' . $index . '][days][]', Helpers::normalize_days($block['days'] ?? null)); ?>
        </div>
        <?php
    }

    /**
     * Render weekdays; null means all and an empty list means none.
     *
     * @param list<string>|null $days Selected ISO day numbers ('1'..'7').
     */
    public static function render_days_row(string $field_name, ?array $days): void
    {
        ?>
        <div class="teksttv-days-row">
            <?php foreach (Helpers::get_day_labels() as $num => $label) : ?>
            <label class="teksttv-day-toggle">
                <input type="checkbox" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr((string) $num); ?>" class="teksttv-visually-hidden" <?php checked($days === null || in_array((string) $num, $days, true)); ?> />
                <span><?php echo esc_html($label); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

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
     * Sanitize registry items while preserving rows from disabled add-ons.
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
