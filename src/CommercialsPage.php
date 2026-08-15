<?php

namespace TekstTV;

use TekstTV\Blocks\Common\DurationField;

class CommercialsPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function redirect_legacy_page(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=teksttv-commercials'));
        exit;
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'teksttv',
            'Reclame',
            'Reclame',
            'manage_teksttv_commercials',
            'teksttv-commercials',
            [self::class, 'render_page']
        );

        // Register a hidden compatibility page so WordPress authorizes old
        // bookmarks long enough to redirect before rendering the admin header.
        // The load- hook always exits, so the render callback never runs.
        $legacy_hook = add_submenu_page(
            '',
            'Reclame',
            'Reclame',
            'manage_teksttv_commercials',
            'teksttv-campaigns',
            '__return_null'
        );
        if ($legacy_hook) {
            add_action('load-' . $legacy_hook, [self::class, 'redirect_legacy_page']);
        }
    }

    public static function render_page(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in handle_save()
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teksttv_commercials_nonce'])) {
            self::handle_save();
        }

        $campaigns = Helpers::get_campaigns();
        $channels = Helpers::get_channels();
        $commercial_blocks = Helpers::get_commercial_blocks();

        include TEKSTTV_PLUGIN_DIR . 'src/views/commercials-page.php';
    }

    /**
     * @param array<string, mixed> $campaign
     * @param list<array{slug: string, label: string}> $channels
     * @param list<array{id: string, label: string}> $commercial_blocks Available commercial blocks.
     */
    public static function render_campaign(int|string $index, array $campaign, array $channels, array $commercial_blocks): void
    {
        // Template rows render an empty id. A unique id is minted for each
        // submitted row so multiple additions never share the template's id.
        $id = $campaign['id'] ?? '';
        $name = $campaign['name'] ?? '';
        $campaign_channels = $campaign['channels'] ?? [];
        $commercial_block_id = (string) ($campaign['commercial_block_id'] ?? '');
        $slides = $campaign['slides'] ?? [];
        $default_duration = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image']);
        $body_id = Helpers::field_id('teksttv_campaigns', $index, 'body');

        ?>
        <div class="teksttv-block" data-type="campaign_item" data-summary-as-title="Campagne">
            <?php AdminPage::render_block_header($body_id, $name ?: 'Campagne', 'megaphone', '#d63638', 'Campagne verwijderen', true); ?>
            <div class="teksttv-block-body" id="<?php echo esc_attr($body_id); ?>" style="display:none;">
                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
                <?php AdminPage::render_block_section_start('Campagne', 'Geef de campagne een naam en een optioneel reclameblok.', 'content'); ?>
                <div class="teksttv-field-grid teksttv-field-grid--campaign-details">
                    <div class="teksttv-field teksttv-field--primary">
                        <label <?php Helpers::field_for('teksttv_campaigns', $index, 'name'); ?>><?php echo esc_html('Naam'); ?></label>
                        <input type="text" <?php Helpers::field_attrs('teksttv_campaigns', $index, 'name'); ?> value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr('bijv. Sponsor X'); ?>" autocomplete="off" data-summary data-summary-empty="<?php echo esc_attr('Naamloze campagne'); ?>" />
                    </div>
                    <div class="teksttv-field teksttv-field--choice">
                        <label <?php Helpers::field_for('teksttv_campaigns', $index, 'commercial_block_id'); ?>><?php echo esc_html('Reclameblok'); ?></label>
                        <select <?php Helpers::field_attrs('teksttv_campaigns', $index, 'commercial_block_id'); ?>>
                            <option value=""><?php echo esc_html('— Geen reclameblok —'); ?></option>
                            <?php foreach ($commercial_blocks as $commercial_block) : ?>
                            <option value="<?php echo esc_attr($commercial_block['id']); ?>" <?php selected($commercial_block_id, $commercial_block['id']); ?>><?php echo esc_html($commercial_block['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php AdminPage::render_block_section_end(); ?>
                <?php AdminPage::render_block_section_start('Weergaveduur', 'Leeg laten gebruikt de standaardinstelling.', 'duration'); ?>
                <div class="teksttv-field-grid teksttv-field-grid--duration">
                    <?php DurationField::render('teksttv_campaigns', $index, 'duration', 'Per slide', (string) ($campaign['duration'] ?? ''), $default_duration); ?>
                </div>
                <?php AdminPage::render_block_section_end(); ?>
                <?php AdminPage::render_block_section_start('Planning', 'Bepaal op welke dagen de campagne actief is.', 'planning'); ?>
                <div class="teksttv-field-grid teksttv-field-grid--scheduling">
                    <?php AdminPage::render_scheduling_inputs($index, $campaign, 'teksttv_campaigns'); ?>
                </div>
                <?php AdminPage::render_block_section_end(); ?>
                <?php AdminPage::render_block_section_start('Kanalen', 'Kies waar deze campagne wordt uitgezonden.', 'channels'); ?>
                <div class="teksttv-field-grid">
                    <div class="teksttv-field teksttv-field--full">
                        <?php foreach ($channels as $ch) : ?>
                        <label class="teksttv-inline-checkbox">
                            <input type="checkbox" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][channels][]" value="<?php echo esc_attr($ch['slug']); ?>" <?php checked(in_array($ch['slug'], $campaign_channels, true)); ?> />
                            <?php echo esc_html($ch['label']); ?>
                        </label>
                        <?php endforeach; ?>
                        <p class="description"><?php echo esc_html('Zonder geselecteerde kanalen is deze campagne nergens actief.'); ?></p>
                    </div>
                </div>
                <?php AdminPage::render_block_section_end(); ?>
                <?php AdminPage::render_block_section_start('Slides', 'Voeg de beelden toe in de gewenste volgorde.', 'slides'); ?>
                <div class="teksttv-campaign-slides-section">
                    <div class="teksttv-campaign-slides teksttv-images-list" data-name="teksttv_campaigns[<?php echo esc_attr($index); ?>][slides][]">
                        <?php foreach ($slides as $attachment_id) :
                            $thumb = wp_get_attachment_image_url((int) $attachment_id, 'thumbnail');
                            if ($thumb) : ?>
                            <div class="teksttv-image-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                                <img src="<?php echo esc_url($thumb); ?>" alt="" width="90" height="90" loading="lazy" />
                                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][slides][]" value="<?php echo esc_attr($attachment_id); ?>" />
                                <button type="button" class="button-link teksttv-remove-image" aria-label="<?php echo esc_attr('Afbeelding verwijderen'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                            </div>
                            <?php endif;
                        endforeach; ?>
                    </div>
                    <button type="button" class="button teksttv-add-action teksttv-campaign-add-slides"><?php echo esc_html('Slides toevoegen'); ?></button>
                </div>
                <?php AdminPage::render_block_section_end(); ?>
            </div>
        </div>
        <?php
    }

    private static function handle_save(): void
    {
        if (!isset($_POST['teksttv_commercials_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_commercials_nonce'])), 'teksttv_save_commercials')) {
            add_settings_error('teksttv_commercials', 'nonce_failed', 'Beveiligingscontrole mislukt; wijzigingen zijn niet opgeslagen. Vernieuw de pagina en probeer het opnieuw.');
            return;
        }

        if (!current_user_can('manage_teksttv_commercials')) {
            add_settings_error('teksttv_commercials', 'no_permission', 'Onvoldoende rechten; wijzigingen zijn niet opgeslagen.');
            return;
        }

        // Save commercial blocks.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_commercial_blocks()
        $raw_commercial_blocks = isset($_POST['teksttv_commercial_blocks']) ? wp_unslash($_POST['teksttv_commercial_blocks']) : [];
        $commercial_blocks = self::sanitize_commercial_blocks($raw_commercial_blocks);
        update_option('teksttv_commercial_blocks', $commercial_blocks);
        $valid_block_ids = array_fill_keys(array_column($commercial_blocks, 'id'), true);

        // Save campaigns.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_campaigns()
        $raw = isset($_POST['teksttv_campaigns']) ? wp_unslash($_POST['teksttv_campaigns']) : [];
        update_option('teksttv_campaigns', self::sanitize_campaigns($raw, Helpers::channel_slugs(), $valid_block_ids));

        add_settings_error('teksttv_commercials', 'saved', 'Reclame opgeslagen.', 'success');
    }

    /**
     * @param mixed               $raw             Unslashed submitted campaigns.
     * @param list<string>        $valid_slugs     Configured channel slugs.
     * @param array<string, true> $valid_block_ids Available commercial block ids.
     * @return list<array<string, mixed>>
     */
    private static function sanitize_campaigns(mixed $raw, array $valid_slugs, array $valid_block_ids): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $campaigns = [];
        $seen_ids = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = sanitize_key($item['id'] ?? '');
            if ($id === '' || isset($seen_ids[$id])) {
                $id = self::new_campaign_id();
            }
            $seen_ids[$id] = true;

            $commercial_block_id = sanitize_key($item['commercial_block_id'] ?? '');
            $saved = [
                'id' => $id,
                'name' => sanitize_text_field($item['name'] ?? ''),
                'commercial_block_id' => isset($valid_block_ids[$commercial_block_id]) ? $commercial_block_id : '',
            ];

            // Channels
            $saved_channels = [];
            if (!empty($item['channels']) && is_array($item['channels'])) {
                $saved_channels = array_map('sanitize_key', $item['channels']);
                $saved_channels = array_values(array_intersect($saved_channels, $valid_slugs));
            }
            $saved['channels'] = $saved_channels;

            // Duration
            $dur = $item['duration'] ?? '';
            if ($dur !== '') {
                $saved['duration'] = Helpers::clamp_int($dur, Helpers::DURATION_MIN_SECONDS, Helpers::DURATION_MAX_SECONDS);
            }

            $saved = array_merge($saved, Helpers::extract_scheduling_fields($item));

            // Slides (attachment IDs)
            $saved_slides = [];
            if (!empty($item['slides']) && is_array($item['slides'])) {
                $saved_slides = array_filter(array_map('absint', $item['slides']));
            }
            $saved['slides'] = array_values($saved_slides);

            $campaigns[] = $saved;
        }

        return $campaigns;
    }

    /**
     * Fallback id for a campaign row that reaches the server without an id,
     * or with one that duplicates an earlier row's.
     */
    private static function new_campaign_id(): string
    {
        return 'camp_' . wp_generate_uuid4();
    }

    /**
     * Sanitize submitted commercial blocks into stable id/label pairs.
     *
     * Each row carries a hidden id so a rename preserves the id (and therefore
     * every campaign/loop reference to it). Rows without an id (newly added in
     * the browser) or with a duplicate id get a stable id derived from the
     * label. Empty labels are dropped, new rows repeating the same label
     * collapse into one, and a derived id that still collides with a
     * differently-labeled block gets a suffixed unique id.
     *
     * @param mixed $raw
     * @return list<array{id: string, label: string}>
     */
    public static function sanitize_commercial_blocks(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $commercial_blocks = [];
        $seen = [];
        foreach ($raw as $row) {
            $label = sanitize_text_field(is_array($row) ? ($row['label'] ?? '') : $row);
            if ($label === '') {
                continue;
            }
            $id = sanitize_key(is_array($row) ? ($row['id'] ?? '') : '');
            if ($id === '' && in_array($label, $seen, true)) {
                continue;
            }
            if ($id === '' || isset($seen[$id])) {
                $id = Helpers::commercial_block_id($label);
            }
            // A colliding id either belongs to a block with this same label
            // (a duplicate row: collapse into it) or to a block renamed away
            // from this label (suffix until unique instead of dropping).
            $base = $id;
            for ($suffix = 2; isset($seen[$id]); $suffix++) {
                if ($seen[$id] === $label) {
                    continue 2;
                }
                $id = $base . '_' . $suffix;
            }
            $seen[$id] = $label;
            $commercial_blocks[] = ['id' => $id, 'label' => $label];
        }

        return $commercial_blocks;
    }
}
