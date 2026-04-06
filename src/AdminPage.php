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
            $loop_label = count($channels) > 1 ? 'Loop: ' . $ch['label'] : 'Loop';
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
                'Content & AI',
                'Content & AI',
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

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style(
            'teksttv-tomselect',
            TEKSTTV_PLUGIN_URL . 'node_modules/tom-select/dist/css/tom-select.default.min.css',
            [],
            '2.5.2'
        );
        wp_enqueue_script(
            'teksttv-tomselect',
            TEKSTTV_PLUGIN_URL . 'node_modules/tom-select/dist/js/tom-select.complete.min.js',
            [],
            '2.5.2',
            true
        );
        wp_enqueue_script(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.js',
            ['jquery', 'jquery-ui-sortable', 'teksttv-tomselect'],
            (string) filemtime(TEKSTTV_PLUGIN_DIR . 'assets/admin.js'),
            true
        );
        wp_enqueue_style(
            'teksttv-admin',
            TEKSTTV_PLUGIN_URL . 'assets/admin.css',
            ['teksttv-tomselect'],
            (string) filemtime(TEKSTTV_PLUGIN_DIR . 'assets/admin.css')
        );
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
            echo '<div class="wrap"><h1>Tekst TV</h1>';
            echo '<p>Ga naar <a href="' . esc_url(admin_url('admin.php?page=teksttv-settings')) . '">Instellingen</a> om eerst een kanaal toe te voegen.</p>';
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
        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = self::get_post_taxonomies_static();
        $taxonomies = array_filter($all_taxonomies, fn($t) => in_array($t['name'], $enabled_tax, true));
        $api_url = rest_url('teksttv/v1/slides?channel=' . $channel_slug);

        echo '<div class="wrap">';
        $page_title = count($channels) > 1 ? 'Loop: ' . $channel_label : 'Loop';
        echo '<h1>' . esc_html($page_title) . '</h1>';
        settings_errors('teksttv');

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-loop-header">
                <span class="teksttv-api-url">
                    <span class="dashicons dashicons-rest-api"></span>
                    API: <code><a href="<?php echo esc_url($api_url); ?>" target="_blank"><?php echo esc_html($api_url); ?></a></code>
                </span>
            </div>

            <form method="post">
                <?php wp_nonce_field('teksttv_save_loop', 'teksttv_loop_nonce'); ?>
                <input type="hidden" name="teksttv_loop_channel" value="<?php echo esc_attr($channel_slug); ?>" />

                <div id="teksttv-blocks" class="teksttv-blocks">
                    <?php
                    if (!empty($blocks)) {
                        foreach ($blocks as $i => $block) {
                            self::render_block_generic($i, $block);
                        }
                    } else {
                        ?>
                        <div class="teksttv-empty-state" id="teksttv-empty-state">
                            <span class="dashicons dashicons-playlist-video"></span><br />
                            Nog geen blokken. Voeg een artikelen-blok toe om te beginnen.
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="teksttv-add-block-bar">
                    <div class="teksttv-dropdown-button">
                        <button type="button" class="button" id="teksttv-add-block-toggle"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Blok toevoegen <span class="dashicons dashicons-arrow-down-alt2 teksttv-button-icon"></span></button>
                        <div class="teksttv-dropdown-menu" id="teksttv-add-block-menu">
                            <?php foreach (BlockRegistry::all('loop') as $block_slug => $block_meta) : ?>
                            <button type="button" data-type="<?php echo esc_attr($block_slug); ?>"><span class="dashicons dashicons-<?php echo esc_attr($block_meta['icon']); ?>"></span> <?php echo esc_html($block_meta['label']); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <span class="teksttv-bar-spacer"></span>
                    <button type="button" class="button-link" id="teksttv-expand-all">Alles openklappen</button>
                    <button type="button" class="button-link" id="teksttv-collapse-all">Alles dichtklappen</button>
                </div>

                <!-- Ticker -->
                <h2 class="teksttv-ticker-heading">Ticker berichten</h2>
                <?php
                $ticker_items = get_option('teksttv_ticker_' . $channel_slug, []);
                ?>
                <div id="teksttv-ticker" class="teksttv-blocks">
                    <?php if (!empty($ticker_items)) :
                        foreach ($ticker_items as $ti => $ticker_item) :
                            self::render_block_generic($ti, $ticker_item, 'teksttv_ticker');
                        endforeach;
                    endif; ?>
                </div>
                <?php $ticker_types = BlockRegistry::all('ticker'); ?>
                <div class="teksttv-add-block-bar">
                    <?php if (count($ticker_types) > 1) : ?>
                    <div class="teksttv-dropdown-button">
                        <button type="button" class="button" id="teksttv-add-ticker-toggle"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Ticker toevoegen <span class="dashicons dashicons-arrow-down-alt2 teksttv-button-icon"></span></button>
                        <div class="teksttv-dropdown-menu" id="teksttv-add-ticker-menu">
                            <?php foreach ($ticker_types as $ticker_slug => $ticker_meta) : ?>
                            <button type="button" data-type="<?php echo esc_attr($ticker_slug); ?>"><span class="dashicons dashicons-<?php echo esc_attr($ticker_meta['icon']); ?>"></span> <?php echo esc_html($ticker_meta['label']); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else :
                        $single_ticker = array_key_first($ticker_types);
                        ?>
                    <button type="button" class="button" id="teksttv-add-ticker-single" data-type="<?php echo esc_attr((string) $single_ticker); ?>"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Ticker toevoegen</button>
                    <?php endif; ?>
                </div>

                <?php
                // Ticker templates per type
                $ticker_types = BlockRegistry::all('ticker');
                foreach ($ticker_types as $ticker_type => $ticker_meta) : ?>
                <script type="text/html" id="tmpl-teksttv-ticker-<?php echo esc_attr($ticker_type); ?>">
                    <?php self::render_block_generic('__TINDEX__', ['type' => $ticker_type], 'teksttv_ticker'); ?>
                </script>
                <?php endforeach; ?>

                <div class="teksttv-add-block-bar">
                    <span class="teksttv-bar-spacer"></span>
                    <button type="button" class="button-link" id="teksttv-expand-all">Alles openklappen</button>
                    <button type="button" class="button-link" id="teksttv-collapse-all">Alles dichtklappen</button>
                    <span class="teksttv-bar-spacer"></span>
                    <?php submit_button('Loop opslaan', 'primary', 'submit', false); ?>
                </div>
            </form>

            <!-- Block templates (generated from registry) -->
            <?php foreach (BlockRegistry::all('loop') as $block_slug => $block_meta) : ?>
            <script type="text/html" id="tmpl-teksttv-block-<?php echo esc_attr($block_slug); ?>">
                <?php self::render_block_generic('__INDEX__', ['type' => $block_slug]); ?>
            </script>
            <?php endforeach; ?>
        </div>
        <?php

        echo '</div>';
    }

    // =========================================================================
    // Settings page (channels + preview URL)
    // =========================================================================

    public static function render_settings_page(): void
    {
        $channels = Helpers::get_channels();

        echo '<div class="wrap">';
        echo '<h1>Tekst TV Instellingen</h1>';

        ?>
        <div class="teksttv-tab-content">
            <form method="post" action="options.php" class="teksttv-settings-form">
                <?php settings_fields('teksttv_settings'); ?>

                <!-- Channels -->
                <div class="teksttv-card">
                    <h3>Kanalen</h3>
                    <p class="description">Beheer de kanalen waarvoor Tekst TV slides worden gegenereerd. Elk kanaal krijgt een eigen loop en API endpoint.</p>
                    <table class="widefat teksttv-channels-table" id="teksttv-channels">
                        <thead>
                            <tr>
                                <th>Slug</th>
                                <th>Naam</th>
                                <th class="teksttv-channel-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($channels as $i => $ch) : ?>
                            <tr class="teksttv-channel-row">
                                <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][slug]" value="<?php echo esc_attr($ch['slug']); ?>" class="regular-text" pattern="[a-z0-9\-]+" required placeholder="bijv. tv1" /></td>
                                <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][label]" value="<?php echo esc_attr($ch['label']); ?>" class="regular-text" required placeholder="bijv. TV 1" /></td>
                                <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-channel"><span class="dashicons dashicons-trash"></span></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="teksttv-card-actions">
                        <button type="button" class="button" id="teksttv-add-channel"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Kanaal toevoegen</button>
                    </p>
                </div>

                <!-- Features -->
                <div class="teksttv-card">
                    <h3>Post editor features</h3>
                    <p class="description">Bepaal welke opties beschikbaar zijn bij het bewerken van een post.</p>
                    <?php $features = Helpers::get_features(); ?>
                    <fieldset class="teksttv-checkbox-list">
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="custom_title" <?php checked(in_array('custom_title', $features, true)); ?> />
                            Kop overschrijven
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="sidebar_image" <?php checked(in_array('sidebar_image', $features, true)); ?> />
                            Sidebar afbeelding kiezen
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="extra_images" <?php checked(in_array('extra_images', $features, true)); ?> />
                            Extra afbeeldingen
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="scheduling" <?php checked(in_array('scheduling', $features, true)); ?> />
                            Planning (datums &amp; weekdagen)
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="page_separator" <?php checked(in_array('page_separator', $features, true)); ?> />
                            Paginascheiding (meerdere slides)
                        </label>
                    </fieldset>
                    <h4>Tekst opmaak</h4>
                    <fieldset class="teksttv-checkbox-list teksttv-checkbox-list--inline">
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="bold" <?php checked(in_array('bold', $features, true)); ?> />
                            <strong>Vet</strong>
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="italic" <?php checked(in_array('italic', $features, true)); ?> />
                            <em>Cursief</em>
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="underline" <?php checked(in_array('underline', $features, true)); ?> />
                            <u>Onderstreept</u>
                        </label>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="lists" <?php checked(in_array('lists', $features, true)); ?> />
                            Lijsten
                        </label>
                    </fieldset>
                    <h4>AI</h4>
                    <fieldset class="teksttv-checkbox-list">
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_features[]" value="ai_generate" <?php checked(in_array('ai_generate', $features, true)); ?> />
                            AI tekst genereren
                        </label>
                    </fieldset>
                </div>

                <!-- Slide duur -->
                <div class="teksttv-card">
                    <h3>Slide duur</h3>
                    <p class="description">Standaard weergaveduur per slide type. Kan per post worden overschreven.</p>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_duration_text">Tekstslide</label></th>
                            <td>
                                <input type="number" id="teksttv_duration_text" name="teksttv_duration_text" value="<?php echo esc_attr(get_option('teksttv_duration_text', 20)); ?>" min="1" max="120" class="small-text" /> seconden
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_duration_image">Afbeeldingslide</label></th>
                            <td>
                                <input type="number" id="teksttv_duration_image" name="teksttv_duration_image" value="<?php echo esc_attr(get_option('teksttv_duration_image', 7)); ?>" min="1" max="120" class="small-text" /> seconden
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Taxonomies -->
                <div class="teksttv-card">
                    <h3>Taxonomy filters</h3>
                    <p class="description">Kies welke taxonomies beschikbaar zijn als filter in de loop-blokken.</p>
                    <?php
                    $all_taxonomies = self::get_post_taxonomies_static();
                    $enabled = get_option('teksttv_enabled_taxonomies', ['category']);
                    ?>
                    <fieldset class="teksttv-checkbox-list">
                        <?php foreach ($all_taxonomies as $tax) : ?>
                        <label class="teksttv-checkbox-list-item">
                            <input type="checkbox" name="teksttv_enabled_taxonomies[]" value="<?php echo esc_attr($tax['name']); ?>" <?php checked(in_array($tax['name'], $enabled, true)); ?> />
                            <?php echo esc_html($tax['label']); ?> <code><?php echo esc_html($tax['name']); ?></code>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <!-- Standaardwaarden -->
                <div class="teksttv-card">
                    <h3>Standaardwaarden</h3>
                    <p class="description">Standaard instellingen voor nieuwe Tekst TV items op posts.</p>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_default_end_days">Standaard einddatum</label></th>
                            <td>
                                <input type="number" id="teksttv_default_end_days" name="teksttv_default_end_days" value="<?php echo esc_attr(get_option('teksttv_default_end_days', 7)); ?>" min="0" max="365" class="small-text" />
                                dagen na publicatiedatum
                                <p class="description">Stel 0 in om geen standaard einddatum te gebruiken.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_max_post_age">Maximale leeftijd berichten</label></th>
                            <td>
                                <input type="number" id="teksttv_max_post_age" name="teksttv_max_post_age" value="<?php echo esc_attr(get_option('teksttv_max_post_age', 30)); ?>" min="0" max="365" class="small-text" />
                                dagen
                                <p class="description">Berichten ouder dan dit worden niet meegenomen. Stel 0 in voor geen limiet.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Weather -->
                <div class="teksttv-card">
                    <h3>Weer</h3>
                    <p class="description">OpenWeather API configuratie voor weer-slides.</p>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_openweather_api_key">API key</label></th>
                            <td>
                                <input type="text" id="teksttv_openweather_api_key" name="teksttv_openweather_api_key" value="<?php echo esc_attr(get_option('teksttv_openweather_api_key', '')); ?>" class="regular-text" />
                                <p class="description">OneCall API 3.0 key van <a href="https://openweathermap.org/api" target="_blank" rel="noopener">openweathermap.org</a>.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Preview -->
                <div class="teksttv-card">
                    <h3>Preview</h3>
                    <p class="description">Configureer de live preview die getoond wordt bij het bewerken van posts.</p>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_preview_url">Preview URL</label></th>
                            <td>
                                <input type="url" id="teksttv_preview_url" name="teksttv_preview_url" value="<?php echo esc_attr(Helpers::get_preview_url()); ?>" class="large-text" placeholder="https://teksttv.example.com/zuidwest-1/preview" />
                                <p class="description">De volledige URL naar de TekstTV frontend preview pagina.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('Instellingen opslaan'); ?>
            </form>
        </div>
        <?php

        echo '</div>';
    }

    // =========================================================================
    // AI Settings page
    // =========================================================================

    public static function render_prompts_page(): void
    {
        $prompts = Helpers::get_ai_prompts();

        echo '<div class="wrap">';
        echo '<h1>Content & AI</h1>';

        ?>
        <div class="teksttv-tab-content">
            <form method="post" action="options.php" class="teksttv-settings-form">
                <?php settings_fields('teksttv_content'); ?>

                <div class="teksttv-card">
                    <h3>Systeem instructie</h3>
                    <p class="description">De systeem instructie bepaalt de rol en stijl van de AI. Dit wordt bij elke generatie meegegeven.</p>
                    <textarea name="teksttv_ai_prompts[system]" rows="4" class="large-text"><?php echo esc_textarea($prompts['system']); ?></textarea>
                </div>

                <div class="teksttv-card">
                    <h3>Prompt: Kop</h3>
                    <p class="description">Instructie voor het genereren van de titel. De artikeltitel en inhoud worden automatisch toegevoegd.</p>
                    <textarea name="teksttv_ai_prompts[prompt_title]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_title']); ?></textarea>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_title_char_limit">Tekenlimiet</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_title_char_limit" name="teksttv_ai_prompts[title_char_limit]" value="<?php echo esc_attr((string) $prompts['title_char_limit']); ?>" min="10" max="100" class="small-text" /> tekens
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="teksttv-card">
                    <h3>Prompt: Tekst</h3>
                    <p class="description">Instructie voor het genereren van de body tekst. De artikeltitel en inhoud worden automatisch toegevoegd.</p>
                    <textarea name="teksttv_ai_prompts[prompt_body]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_body']); ?></textarea>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_word_limit">Woordlimiet</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_word_limit" name="teksttv_ai_prompts[word_limit]" value="<?php echo esc_attr((string) $prompts['word_limit']); ?>" min="10" max="500" class="small-text" /> woorden
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="teksttv-card">
                    <h3>Overig</h3>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_min_input">Minimum input</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_min_input" name="teksttv_ai_prompts[min_input_words]" value="<?php echo esc_attr((string) $prompts['min_input_words']); ?>" min="0" max="500" class="small-text" /> woorden
                                <p class="description">Minimum aantal woorden in het bronartikel. Stel 0 in om uit te schakelen.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_ai_max_retries">Max pogingen</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_max_retries" name="teksttv_ai_prompts[max_retries]" value="<?php echo esc_attr((string) $prompts['max_retries']); ?>" min="1" max="5" class="small-text" />
                                <p class="description">Aantal pogingen als de output niet binnen het limiet valt. Elke extra poging kost een API-call.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_ai_rate_limit">Rate limit</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_rate_limit" name="teksttv_ai_prompts[rate_limit]" value="<?php echo esc_attr((string) $prompts['rate_limit']); ?>" min="1" max="60" class="small-text" /> per minuut
                                <p class="description">Maximaal aantal AI-verzoeken per gebruiker per minuut.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if (current_user_can('manage_teksttv')) : ?>
                <div class="teksttv-card">
                    <h3>Regio-prefix</h3>
                    <p class="description">Voeg automatisch een regio-prefix toe aan de gegenereerde kop, bijv. <code>LEIDEN - Kop hier</code>.</p>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_region_taxonomy">Taxonomy</label></th>
                            <td>
                                <?php
                                $all_taxonomies = self::get_post_taxonomies_static();
                                $region_tax = $prompts['region_taxonomy'];
                                ?>
                                <select id="teksttv_ai_region_taxonomy" name="teksttv_ai_prompts[region_taxonomy]">
                                    <option value="">Geen regio-prefix</option>
                                    <?php foreach ($all_taxonomies as $tax) : ?>
                                        <option value="<?php echo esc_attr($tax['name']); ?>" <?php selected($region_tax, $tax['name']); ?>><?php echo esc_html($tax['label']); ?> (<?php echo esc_html($tax['name']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Kies de taxonomy waarvan de terms als regio-prefix worden gebruikt. Bij meerdere terms worden ze samengevoegd met <code>/</code>.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="teksttv-card">
                    <h3>Technisch</h3>
                    <?php $ai_models = Helpers::get_ai_models(); ?>
                    <?php if (!empty($ai_models)) : ?>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_provider">Provider</label></th>
                            <td>
                                <select id="teksttv_ai_provider" name="teksttv_ai_prompts[provider]">
                                    <option value="">Automatisch</option>
                                    <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                        <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($prompts['provider'], $provider_id); ?>><?php echo esc_html($provider_data['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Forceer een specifieke AI-provider. Bij "Automatisch" kiest WordPress de beste beschikbare provider.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_ai_model">Model</label></th>
                            <td>
                                <select id="teksttv_ai_model" name="teksttv_ai_prompts[model]">
                                    <option value="">Automatisch</option>
                                    <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                        <optgroup label="<?php echo esc_attr($provider_data['label']); ?>">
                                            <?php foreach ($provider_data['models'] as $model_id => $model_name) : ?>
                                                <?php $value = $provider_id . '/' . $model_id; ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($prompts['model'], $value); ?>><?php echo esc_html($model_name); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Forceer een specifiek model. Overschrijft de provider-keuze hierboven.</p>
                            </td>
                        </tr>
                    </table>
                    <?php else : ?>
                    <p class="description">Geen AI-providers beschikbaar. Configureer een provider via <a href="<?php echo esc_url(admin_url('options-connectors.php')); ?>">WordPress Connectors</a>.</p>
                    <?php endif; ?>
                    <h4>Model parameters</h4>
                    <table class="form-table teksttv-form-table">
                        <tr>
                            <th scope="row"><label for="teksttv_ai_temperature">Temperature</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_temperature" name="teksttv_ai_prompts[temperature]" value="<?php echo esc_attr($prompts['temperature']); ?>" min="0" max="2" step="0.1" class="small-text" />
                                <p class="description">Creativiteit van de output. 0 = deterministisch, 1 = standaard, 2 = zeer creatief. Leeg = provider default.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_ai_top_p">Top P</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_top_p" name="teksttv_ai_prompts[top_p]" value="<?php echo esc_attr($prompts['top_p']); ?>" min="0" max="1" step="0.05" class="small-text" />
                                <p class="description">Nucleus sampling. Lagere waarde = meer gefocust. Leeg = provider default.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="teksttv_ai_max_tokens">Max tokens</label></th>
                            <td>
                                <input type="number" id="teksttv_ai_max_tokens" name="teksttv_ai_prompts[max_tokens]" value="<?php echo esc_attr((string) $prompts['max_tokens']); ?>" min="64" max="8192" step="1" class="small-text" />
                                <p class="description">Maximaal aantal tokens in de AI-response. Standaard 2048.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php submit_button('Instellingen opslaan'); ?>
            </form>
        </div>
        <?php

        echo '</div>';
    }

    // =========================================================================
    // Block rendering
    // =========================================================================

    /**
     * Get all public taxonomies that apply to posts.
     *
     * @return list<array{name: string, label: string, terms: array<int, string>}>
     */
    public static function get_post_taxonomies_static(): array
    {
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

        $day_labels = [
            '1' => 'Ma', '2' => 'Di', '3' => 'Wo', '4' => 'Do',
            '5' => 'Vr', '6' => 'Za', '7' => 'Zo',
        ];

        ?>
        <div class="teksttv-block-scheduling-toggle">
            <label>
                <input type="checkbox" class="teksttv-scheduling-checkbox" <?php checked($has_scheduling); ?> />
                Planning inschakelen
            </label>
        </div>
        <div class="teksttv-block-fields teksttv-block-fields--scheduling" <?php echo $has_scheduling ? '' : 'style="display:none;"'; ?>>
            <div class="teksttv-block-field">
                <label>Vanaf</label>
                <input type="date" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_start]" value="<?php echo esc_attr($date_start); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label>Tot en met</label>
                <input type="date" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][date_end]" value="<?php echo esc_attr($date_end); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label>Dagen</label>
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

        add_settings_error('teksttv', 'loop_saved', 'Loop configuratie opgeslagen.', 'success');
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

        $block_days = $raw['days'] ?? [];
        if (is_array($block_days)) {
            $block_days = array_map('sanitize_text_field', $block_days);
            if (count($block_days) < 7) {
                $fields['days'] = $block_days;
            }
        }

        return $fields;
    }
}
