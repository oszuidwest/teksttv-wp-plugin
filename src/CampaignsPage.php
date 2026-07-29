<?php

namespace TekstTV;

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
        $duration = $campaign['duration'] ?? '';
        $slides = $campaign['slides'] ?? [];
        $default_duration = (int) get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image']);

        ?>
        <div class="teksttv-block" data-type="campaign_item">
            <div class="teksttv-block-header">
                <span class="teksttv-block-handle dashicons dashicons-move"></span>
                <span class="teksttv-block-icon" style="background:#d63638"><span class="dashicons dashicons-megaphone"></span></span>
                <span class="teksttv-block-title"><?php echo esc_html($name ?: 'Campagne'); ?></span>
                <span class="teksttv-block-summary"></span>
                <span class="teksttv-block-toggle dashicons dashicons-arrow-down-alt2"></span>
                <button type="button" class="button-link teksttv-remove-block"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="teksttv-block-body">
                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label><?php echo esc_html('Naam'); ?></label>
                        <input type="text" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr('Bijv. Sponsor X'); ?>" data-summary data-summary-empty="<?php echo esc_attr('Naamloze campagne'); ?>" />
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php echo esc_html('Groep'); ?></label>
                        <select name="teksttv_campaigns[<?php echo esc_attr($index); ?>][group]" class="teksttv-campaign-group-select">
                            <option value=""><?php echo esc_html('— Geen groep —'); ?></option>
                            <?php foreach ($groups as $group_option) : ?>
                            <option value="<?php echo esc_attr($group_option['id']); ?>" <?php selected($group, $group_option['id']); ?>><?php echo esc_html($group_option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php echo esc_html('Duur per slide'); ?></label>
                        <input type="number" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][duration]" value="<?php echo esc_attr($duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_duration); ?>" /> <span class="teksttv-unit">sec</span>
                    </div>
                </div>
                <div class="teksttv-block-fields">
                    <?php AdminPage::render_scheduling_inputs($index, $campaign, 'teksttv_campaigns'); ?>
                </div>
                <?php if (count($channels) > 1) : ?>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <span class="teksttv-field-label"><?php echo esc_html('Kanalen'); ?></span>
                        <?php foreach ($channels as $ch) : ?>
                        <label class="teksttv-inline-checkbox">
                            <input type="checkbox" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][channels][]" value="<?php echo esc_attr($ch['slug']); ?>" <?php checked(in_array($ch['slug'], $campaign_channels, true) || empty($campaign_channels)); ?> />
                            <?php echo esc_html($ch['label']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else : ?>
                    <?php foreach ($channels as $ch) : ?>
                    <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][channels][]" value="<?php echo esc_attr($ch['slug']); ?>" />
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="teksttv-campaign-slides-section">
                    <label class="teksttv-section-label"><?php echo esc_html('Slides'); ?></label>
                    <div class="teksttv-campaign-slides teksttv-images-list" data-name="teksttv_campaigns[<?php echo esc_attr($index); ?>][slides][]">
                        <?php foreach ($slides as $attachment_id) :
                            $thumb = wp_get_attachment_image_url((int) $attachment_id, 'thumbnail');
                            if ($thumb) : ?>
                            <div class="teksttv-image-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                                <img src="<?php echo esc_url($thumb); ?>" alt="" />
                                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][slides][]" value="<?php echo esc_attr($attachment_id); ?>" />
                                <button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>
                            </div>
                            <?php endif;
                        endforeach; ?>
                    </div>
                    <button type="button" class="button teksttv-campaign-add-slides"><span class="dashicons dashicons-format-gallery teksttv-button-icon"></span> <?php echo esc_html('Slides toevoegen'); ?></button>
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
        $campaigns = self::sanitize_campaigns($raw, Helpers::channel_slugs());

        update_option('teksttv_campaigns', $campaigns);

        RestApi::invalidate_slides_cache();

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
                $saved['duration'] = Helpers::clamp_int($dur, 1, 120);
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
