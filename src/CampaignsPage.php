<?php

namespace TekstTV;

use TekstTV\Blocks\Common\DurationField;

class CampaignsPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'teksttv',
            'Campagnes',
            'Campagnes',
            'manage_teksttv_campaigns',
            'teksttv-campaigns',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in handle_save()
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teksttv_campaigns_nonce'])) {
            self::handle_save();
        }

        $campaigns = Helpers::get_campaigns();
        $channels = Helpers::get_channels();
        $groups = Helpers::get_campaign_groups();

        include TEKSTTV_PLUGIN_DIR . 'src/views/campaigns-page.php';
    }

    /**
     * @param array<string, mixed> $campaign
     * @param list<array{slug: string, label: string}> $channels
     * @param list<array{id: string, label: string}> $groups Available groups.
     */
    public static function render_campaign(int|string $index, array $campaign, array $channels, array $groups): void
    {
        // Template rows render an empty id. A unique id is minted for each
        // submitted row so multiple additions never share the template's id.
        $id = $campaign['id'] ?? '';
        $name = $campaign['name'] ?? '';
        $campaign_channels = $campaign['channels'] ?? [];
        $group = (string) ($campaign['group'] ?? '');
        $slides = $campaign['slides'] ?? [];
        $default_duration = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image']);
        $body_id = Helpers::field_id('teksttv_campaigns', $index, 'body');
        $name_id = Helpers::field_id('teksttv_campaigns', $index, 'name');
        $group_id = Helpers::field_id('teksttv_campaigns', $index, 'group');

        ?>
        <div class="teksttv-block" data-type="campaign_item">
            <?php AdminPage::render_block_header($body_id, $name ?: 'Campagne', 'megaphone', '#d63638', 'Campagne verwijderen'); ?>
            <div class="teksttv-block-body" id="<?php echo esc_attr($body_id); ?>" style="display:none;">
                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
                <div class="teksttv-field-grid">
                    <div class="teksttv-field teksttv-field--primary">
                        <label for="<?php echo esc_attr($name_id); ?>"><?php echo esc_html('Naam'); ?></label>
                        <input type="text" id="<?php echo esc_attr($name_id); ?>" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr('bijv. Sponsor X'); ?>" autocomplete="off" data-summary data-summary-empty="<?php echo esc_attr('Naamloze campagne'); ?>" />
                    </div>
                    <div class="teksttv-field teksttv-field--choice">
                        <label for="<?php echo esc_attr($group_id); ?>"><?php echo esc_html('Groep'); ?></label>
                        <select id="<?php echo esc_attr($group_id); ?>" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][group]" class="teksttv-campaign-group-select">
                            <option value=""><?php echo esc_html('— Geen groep —'); ?></option>
                            <?php foreach ($groups as $group_option) : ?>
                            <option value="<?php echo esc_attr($group_option['id']); ?>" <?php selected($group, $group_option['id']); ?>><?php echo esc_html($group_option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php DurationField::render('teksttv_campaigns', $index, 'duration', 'Duur per slide', (string) ($campaign['duration'] ?? ''), $default_duration); ?>
                </div>
                <div class="teksttv-field-grid">
                    <?php AdminPage::render_scheduling_inputs($index, $campaign, 'teksttv_campaigns'); ?>
                </div>
                <div class="teksttv-field-grid">
                    <div class="teksttv-field teksttv-field--full">
                        <span class="teksttv-field-label"><?php echo esc_html('Kanalen'); ?></span>
                        <?php foreach ($channels as $ch) : ?>
                        <label class="teksttv-inline-checkbox">
                            <input type="checkbox" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][channels][]" value="<?php echo esc_attr($ch['slug']); ?>" <?php checked(in_array($ch['slug'], $campaign_channels, true)); ?> />
                            <?php echo esc_html($ch['label']); ?>
                        </label>
                        <?php endforeach; ?>
                        <p class="description"><?php echo esc_html('Zonder geselecteerde kanalen is deze campagne nergens actief.'); ?></p>
                    </div>
                </div>
                <div class="teksttv-campaign-slides-section">
                    <h3 class="teksttv-section-label"><?php echo esc_html('Slides'); ?></h3>
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
                    <button type="button" class="button teksttv-campaign-add-slides"><span class="dashicons dashicons-format-gallery teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Slides toevoegen'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function handle_save(): void
    {
        if (!isset($_POST['teksttv_campaigns_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_campaigns_nonce'])), 'teksttv_save_campaigns')) {
            add_settings_error('teksttv_campaigns', 'nonce_failed', 'Beveiligingscontrole mislukt; wijzigingen zijn niet opgeslagen. Vernieuw de pagina en probeer het opnieuw.');
            return;
        }

        if (!current_user_can('manage_teksttv_campaigns')) {
            add_settings_error('teksttv_campaigns', 'no_permission', 'Onvoldoende rechten; wijzigingen zijn niet opgeslagen.');
            return;
        }

        // Save groups
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_groups()
        $raw_groups = isset($_POST['teksttv_campaign_groups']) ? wp_unslash($_POST['teksttv_campaign_groups']) : [];
        update_option('teksttv_campaign_groups', self::sanitize_groups($raw_groups));

        // Save campaigns
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below
        $raw = isset($_POST['teksttv_campaigns']) ? wp_unslash($_POST['teksttv_campaigns']) : [];
        update_option('teksttv_campaigns', self::sanitize_campaigns($raw, Helpers::channel_slugs()));

        add_settings_error('teksttv_campaigns', 'saved', 'Campagnes opgeslagen.', 'success');
    }

    /**
     * @param mixed        $raw         Unslashed submitted campaigns.
     * @param list<string> $valid_slugs Configured channel slugs.
     * @return list<array<string, mixed>>
     */
    private static function sanitize_campaigns(mixed $raw, array $valid_slugs): array
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

            $saved = [
                'id' => $id,
                'name' => sanitize_text_field($item['name'] ?? ''),
                'group' => sanitize_key($item['group'] ?? ''),
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
     * Fallback id for a campaign row that reaches the server without one.
     */
    private static function new_campaign_id(): string
    {
        return 'camp_' . wp_generate_uuid4();
    }

    /**
     * Sanitize submitted campaign groups into stable id/label pairs.
     *
     * Each row carries a hidden id so a rename preserves the id (and therefore
     * every campaign/loop reference to it). Rows without an id — newly added in
     * the browser — get a stable id derived from the label. Duplicate ids and
     * empty labels are dropped.
     *
     * @param mixed $raw
     * @return list<array{id: string, label: string}>
     */
    public static function sanitize_groups(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $groups = [];
        $seen = [];
        foreach ($raw as $row) {
            $label = sanitize_text_field(is_array($row) ? ($row['label'] ?? '') : $row);
            if ($label === '') {
                continue;
            }
            $id = sanitize_key(is_array($row) ? ($row['id'] ?? '') : '');
            if ($id === '' || isset($seen[$id])) {
                $id = Helpers::campaign_group_id($label);
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $groups[] = ['id' => $id, 'label' => $label];
        }

        return $groups;
    }
}
