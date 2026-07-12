<?php

namespace TekstTV;

class CampaignsPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [Helpers::class, 'migrate_campaign_groups']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'teksttv',
            __('Campagnes', 'teksttv-wp-plugin'),
            __('Campagnes', 'teksttv-wp-plugin'),
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
        $id = $campaign['id'] ?? 'camp_' . time() . '_' . wp_rand();
        $name = $campaign['name'] ?? '';
        $campaign_channels = $campaign['channels'] ?? [];
        $group = (string) ($campaign['group'] ?? '');
        $date_start = $campaign['date_start'] ?? '';
        $date_end = $campaign['date_end'] ?? '';
        $days = $campaign['days'] ?? [];
        $duration = $campaign['duration'] ?? '';
        $slides = $campaign['slides'] ?? [];
        $default_duration = (int) get_option('teksttv_duration_image', 7);
        $day_labels = Helpers::get_day_labels();

        ?>
        <div class="teksttv-block" data-type="campaign_item">
            <div class="teksttv-block-header">
                <span class="teksttv-block-handle dashicons dashicons-move"></span>
                <span class="teksttv-block-icon" style="background:#d63638"><span class="dashicons dashicons-megaphone"></span></span>
                <span class="teksttv-block-title"><?php echo esc_html($name ?: __('Campagne', 'teksttv-wp-plugin')); ?></span>
                <span class="teksttv-block-summary"></span>
                <span class="teksttv-block-toggle dashicons dashicons-arrow-down-alt2"></span>
                <button type="button" class="button-link teksttv-remove-block"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="teksttv-block-body">
                <input type="hidden" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Naam', 'teksttv-wp-plugin'); ?></label>
                        <input type="text" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Bijv. Sponsor X', 'teksttv-wp-plugin'); ?>" />
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Groep', 'teksttv-wp-plugin'); ?></label>
                        <select name="teksttv_campaigns[<?php echo esc_attr($index); ?>][group]" class="teksttv-campaign-group-select">
                            <option value=""><?php esc_html_e('— Geen groep —', 'teksttv-wp-plugin'); ?></option>
                            <?php foreach ($groups as $group_option) : ?>
                            <option value="<?php echo esc_attr($group_option['id']); ?>" <?php selected($group, $group_option['id']); ?>><?php echo esc_html($group_option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Duur per slide', 'teksttv-wp-plugin'); ?></label>
                        <input type="number" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][duration]" value="<?php echo esc_attr($duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_duration); ?>" /> <span class="teksttv-unit">sec</span>
                    </div>
                </div>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Vanaf', 'teksttv-wp-plugin'); ?></label>
                        <input type="date" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][date_start]" value="<?php echo esc_attr($date_start); ?>" />
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Tot en met', 'teksttv-wp-plugin'); ?></label>
                        <input type="date" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][date_end]" value="<?php echo esc_attr($date_end); ?>" />
                    </div>
                    <div class="teksttv-block-field">
                        <label><?php esc_html_e('Dagen', 'teksttv-wp-plugin'); ?></label>
                        <div class="teksttv-days-row">
                            <?php foreach ($day_labels as $num => $label) : ?>
                            <label class="teksttv-day-toggle">
                                <input type="checkbox" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][days][]" value="<?php echo esc_attr((string) $num); ?>" <?php checked(empty($days) || in_array((string) $num, $days, true)); ?> />
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php if (count($channels) > 1) : ?>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <span class="teksttv-field-label"><?php esc_html_e('Kanalen', 'teksttv-wp-plugin'); ?></span>
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
                    <label class="teksttv-section-label"><?php esc_html_e('Slides', 'teksttv-wp-plugin'); ?></label>
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
                    <button type="button" class="button teksttv-campaign-add-slides"><span class="dashicons dashicons-format-gallery teksttv-button-icon"></span> <?php esc_html_e('Slides toevoegen', 'teksttv-wp-plugin'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function handle_save(): void
    {
        if (!isset($_POST['teksttv_campaigns_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['teksttv_campaigns_nonce'])), 'teksttv_save_campaigns')) {
            return;
        }

        if (!current_user_can('manage_teksttv_campaigns')) {
            return;
        }

        // Save groups
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_groups()
        $raw_groups = isset($_POST['teksttv_campaign_groups']) ? wp_unslash($_POST['teksttv_campaign_groups']) : [];
        update_option('teksttv_campaign_groups', self::sanitize_groups($raw_groups));

        // Save campaigns
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below
        $raw = isset($_POST['teksttv_campaigns']) ? wp_unslash($_POST['teksttv_campaigns']) : [];
        $campaigns = [];

        foreach ($raw as $item) {
            $saved = [
                'id' => sanitize_key($item['id'] ?? ('camp_' . time() . '_' . wp_rand())),
                'name' => sanitize_text_field($item['name'] ?? ''),
                'group' => sanitize_key($item['group'] ?? ''),
            ];

            // Channels
            $saved_channels = [];
            if (!empty($item['channels']) && is_array($item['channels'])) {
                $saved_channels = array_map('sanitize_key', $item['channels']);
                $valid_slugs = array_column(Helpers::get_channels(), 'slug');
                $saved_channels = array_values(array_intersect($saved_channels, $valid_slugs));
            }
            $saved['channels'] = $saved_channels;

            // Duration
            $dur = $item['duration'] ?? '';
            if ($dur !== '') {
                $saved['duration'] = Helpers::clamp_int($dur, 1, 120);
            }

            // Dates
            $ds = sanitize_text_field($item['date_start'] ?? '');
            $de = sanitize_text_field($item['date_end'] ?? '');
            if ($ds !== '') {
                $saved['date_start'] = $ds;
            }
            if ($de !== '') {
                $saved['date_end'] = $de;
            }

            // Days of week (ISO 1=Mon..7=Sun). Omit when all 7 are checked.
            $sanitized_days = Helpers::sanitize_days_input($item['days'] ?? null);
            if ($sanitized_days !== null) {
                $saved['days'] = $sanitized_days;
            }

            // Slides (attachment IDs)
            $saved_slides = [];
            if (!empty($item['slides']) && is_array($item['slides'])) {
                $saved_slides = array_filter(array_map('absint', $item['slides']));
            }
            $saved['slides'] = array_values($saved_slides);

            $campaigns[] = $saved;
        }

        update_option('teksttv_campaigns', $campaigns);

        RestApi::invalidate_slides_cache();

        add_settings_error('teksttv_campaigns', 'saved', __('Campagnes opgeslagen.', 'teksttv-wp-plugin'), 'success');
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
