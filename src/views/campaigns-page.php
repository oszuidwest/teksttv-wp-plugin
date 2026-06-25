<?php
/**
 * Campaigns admin template.
 *
 * @var list<array<string, mixed>> $campaigns
 * @var list<array{slug: string, label: string}> $channels
 * @var list<string> $groups
 */

namespace TekstTV;

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Campagnes', 'teksttv-wp-plugin') . '</h1>';
settings_errors('teksttv_campaigns');

?>
<form method="post" x-data="teksttvCampaignsPage">
    <?php wp_nonce_field('teksttv_save_campaigns', 'teksttv_campaigns_nonce'); ?>

    <!-- Groups management -->
    <div class="teksttv-card" style="margin-bottom:24px;">
        <h3><?php esc_html_e('Groepen', 'teksttv-wp-plugin'); ?></h3>
        <p class="description"><?php esc_html_e('Definieer groepen om campagnes te organiseren. In de loop kies je per campagne-blok welke groepen getoond worden.', 'teksttv-wp-plugin'); ?></p>
        <table class="widefat teksttv-channels-table" id="teksttv-groups" @click="groupsClick($event)">
            <thead>
                <tr>
                    <th><?php esc_html_e('Naam', 'teksttv-wp-plugin'); ?></th>
                    <th class="teksttv-channel-actions"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $gi => $group_label) : ?>
                <tr class="teksttv-group-row">
                    <td><input type="text" name="teksttv_campaign_groups[]" value="<?php echo esc_attr($group_label); ?>" class="regular-text" required placeholder="<?php echo esc_attr__('Bijv. Campagne', 'teksttv-wp-plugin'); ?>" /></td>
                    <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-group"><span class="dashicons dashicons-trash"></span></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="teksttv-card-actions">
            <button type="button" class="button" id="teksttv-add-group" @click.prevent="addGroupRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> <?php esc_html_e('Groep toevoegen', 'teksttv-wp-plugin'); ?></button>
        </p>
    </div>

    <h2><?php esc_html_e('Campagnes', 'teksttv-wp-plugin'); ?></h2>
    <div id="teksttv-campaigns" class="teksttv-blocks" @click="blocksClick($event)" @change="blocksFieldChange($event)" @input="blocksFieldChange($event)">
        <?php
        if (!empty($campaigns)) {
            foreach ($campaigns as $i => $campaign) {
                CampaignsPage::render_campaign($i, $campaign, $channels, $groups);
            }
        } else {
            ?>
            <div class="teksttv-empty-state" id="teksttv-empty-state">
                <span class="dashicons dashicons-megaphone"></span><br />
                <?php esc_html_e('Nog geen campagnes. Voeg een campagne toe om te beginnen.', 'teksttv-wp-plugin'); ?>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="teksttv-add-block-bar">
        <button type="button" class="button" id="teksttv-add-campaign" @click.prevent="addCampaignBlock()"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Campagne toevoegen', 'teksttv-wp-plugin'); ?></button>
        <span class="teksttv-bar-spacer"></span>
        <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="expandAllBlocks()"><?php esc_html_e('Alles openklappen', 'teksttv-wp-plugin'); ?></button>
        <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="collapseAllBlocks()"><?php esc_html_e('Alles dichtklappen', 'teksttv-wp-plugin'); ?></button>
        <span class="teksttv-bar-spacer"></span>
        <?php submit_button(__('Opslaan', 'teksttv-wp-plugin'), 'primary', 'submit', false); ?>
    </div>
</form>

<script type="text/html" id="tmpl-teksttv-campaign">
    <?php CampaignsPage::render_campaign('__INDEX__', [], $channels, $groups); ?>
</script>

</div>
