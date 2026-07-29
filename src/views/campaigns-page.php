<?php
/**
 * Campaigns admin template.
 *
 * @var list<array<string, mixed>> $campaigns
 * @var list<array{slug: string, label: string}> $channels
 * @var list<array{id: string, label: string}> $groups
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap">';
echo '<h1>' . esc_html('Campagnes') . '</h1>';
settings_errors('teksttv_campaigns');

?>
<form method="post" x-data="teksttvCampaignsPage">
    <?php wp_nonce_field('teksttv_save_campaigns', 'teksttv_campaigns_nonce'); ?>

    <!-- Groups management -->
    <div class="teksttv-card" style="margin-bottom:24px;">
        <h3><?php echo esc_html('Groepen'); ?></h3>
        <p class="description"><?php echo esc_html('Definieer groepen om campagnes te organiseren. In de loop kies je per campagne-blok welke groepen getoond worden.'); ?></p>
        <table class="widefat teksttv-channels-table" id="teksttv-groups" @click="groupsClick($event)">
            <thead>
                <tr>
                    <th><?php echo esc_html('Naam'); ?></th>
                    <th class="teksttv-channel-actions"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $gi => $group) : ?>
                <tr class="teksttv-group-row">
                    <td>
                        <input type="hidden" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][id]" value="<?php echo esc_attr($group['id']); ?>" />
                        <input type="text" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][label]" value="<?php echo esc_attr($group['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr('Bijv. Campagne'); ?>" />
                    </td>
                    <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-group"><span class="dashicons dashicons-trash"></span></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="teksttv-card-actions">
            <button type="button" class="button" id="teksttv-add-group" @click.prevent="addGroupRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> <?php echo esc_html('Groep toevoegen'); ?></button>
        </p>
    </div>

    <h2><?php echo esc_html('Campagnes'); ?></h2>
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
                <?php echo esc_html('Nog geen campagnes. Voeg een campagne toe om te beginnen.'); ?>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="teksttv-add-block-bar">
        <button type="button" class="button" id="teksttv-add-campaign" @click.prevent="addCampaignBlock()"><span class="dashicons dashicons-plus-alt2"></span> <?php echo esc_html('Campagne toevoegen'); ?></button>
        <span class="teksttv-bar-spacer"></span>
        <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="expandAllBlocks()"><?php echo esc_html('Alles openklappen'); ?></button>
        <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="collapseAllBlocks()"><?php echo esc_html('Alles dichtklappen'); ?></button>
        <span class="teksttv-bar-spacer"></span>
        <?php submit_button('Opslaan', 'primary', 'submit', false); ?>
    </div>
</form>

<script type="text/html" id="tmpl-teksttv-campaign">
    <?php CampaignsPage::render_campaign('__INDEX__', [], $channels, $groups); ?>
</script>

</div>
