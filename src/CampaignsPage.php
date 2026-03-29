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
            'Reclame',
            'Reclame',
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

        echo '<div class="wrap">';
        echo '<h1>Reclame campagnes</h1>';
        settings_errors('teksttv_campaigns');

        ?>
        <form method="post">
            <?php wp_nonce_field('teksttv_save_campaigns', 'teksttv_campaigns_nonce'); ?>

            <div id="teksttv-campaigns" class="teksttv-blocks">
                <?php
                if (!empty($campaigns)) {
                    foreach ($campaigns as $i => $campaign) {
                        self::render_campaign($i, $campaign, $channels);
                    }
                } else {
                    ?>
                    <div class="teksttv-empty-state" id="teksttv-empty-state">
                        <span class="dashicons dashicons-megaphone"></span><br />
                        Nog geen campagnes. Voeg een campagne toe om te beginnen.
                    </div>
                    <?php
                }
                ?>
            </div>

            <div class="teksttv-add-block-bar">
                <button type="button" class="button" id="teksttv-add-campaign"><span class="dashicons dashicons-plus-alt2"></span> Campagne toevoegen</button>
                <span class="teksttv-bar-spacer"></span>
                <button type="button" class="button-link" id="teksttv-expand-all">Alles openklappen</button>
                <button type="button" class="button-link" id="teksttv-collapse-all">Alles dichtklappen</button>
                <span class="teksttv-bar-spacer"></span>
                <?php submit_button('Opslaan', 'primary', 'submit', false); ?>
            </div>
        </form>

        <script type="text/html" id="tmpl-teksttv-campaign">
            <?php self::render_campaign('__INDEX__', [], $channels); ?>
        </script>

        </div>
        <?php
    }

    private static function render_campaign(int|string $index, array $campaign, array $channels): void
    {
        $id = $campaign['id'] ?? 'camp_' . time() . '_' . wp_rand();
        $name = $campaign['name'] ?? '';
        $campaign_channels = $campaign['channels'] ?? [];
        $group = $campaign['group'] ?? 1;
        $date_start = $campaign['date_start'] ?? '';
        $date_end = $campaign['date_end'] ?? '';
        $duration = $campaign['duration'] ?? '';
        $slides = $campaign['slides'] ?? [];
        $default_duration = (int) get_option('teksttv_duration_image', 7);

        ?>
        <div class="teksttv-block" data-type="campaign">
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
                        <label>Naam</label>
                        <input type="text" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="Bijv. Sponsor X" />
                    </div>
                    <div class="teksttv-block-field">
                        <label>Groep</label>
                        <select name="teksttv_campaigns[<?php echo esc_attr($index); ?>][group]">
                            <?php for ($g = 1; $g <= 10; $g++) : ?>
                            <option value="<?php echo $g; ?>" <?php selected($group, $g); ?>><?php echo $g; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="teksttv-block-field">
                        <label>Duur per slide</label>
                        <input type="number" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][duration]" value="<?php echo esc_attr($duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr($default_duration); ?>" /> <span class="teksttv-unit">sec</span>
                    </div>
                </div>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label>Vanaf</label>
                        <input type="date" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][date_start]" value="<?php echo esc_attr($date_start); ?>" />
                    </div>
                    <div class="teksttv-block-field">
                        <label>Tot en met</label>
                        <input type="date" name="teksttv_campaigns[<?php echo esc_attr($index); ?>][date_end]" value="<?php echo esc_attr($date_end); ?>" />
                    </div>
                </div>
                <?php if (count($channels) > 1) : ?>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <span class="teksttv-field-label">Kanalen</span>
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
                    <label class="teksttv-section-label">Slides</label>
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
                    <button type="button" class="button teksttv-campaign-add-slides"><span class="dashicons dashicons-format-gallery teksttv-button-icon"></span> Slides toevoegen</button>
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

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below
        $raw = isset($_POST['teksttv_campaigns']) ? wp_unslash($_POST['teksttv_campaigns']) : [];
        $campaigns = [];

        foreach ($raw as $item) {
            $saved = [
                'id' => sanitize_key($item['id'] ?? ('camp_' . time() . '_' . wp_rand())),
                'name' => sanitize_text_field($item['name'] ?? ''),
                'group' => max(1, min(10, absint($item['group'] ?? 1))),
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
                $saved['duration'] = absint($dur);
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

            // Slides (attachment IDs)
            $saved_slides = [];
            if (!empty($item['slides']) && is_array($item['slides'])) {
                $saved_slides = array_filter(array_map('absint', $item['slides']));
            }
            $saved['slides'] = array_values($saved_slides);

            $campaigns[] = $saved;
        }

        update_option('teksttv_campaigns', $campaigns);

        add_settings_error('teksttv_campaigns', 'saved', 'Campagnes opgeslagen.', 'success');
    }
}
