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

echo '<div class="wrap teksttv-admin">';
echo '<h1>' . esc_html('Campagnes') . '</h1>';
settings_errors('teksttv_campaigns');

?>
<form method="post" class="teksttv-admin-column" x-data="teksttvCampaignsPage">
    <?php wp_nonce_field('teksttv_save_campaigns', 'teksttv_campaigns_nonce'); ?>

    <!-- Groups management -->
    <section class="teksttv-card teksttv-campaign-groups teksttv-workbench-section">
        <h2><?php echo esc_html('Groepen'); ?></h2>
        <p class="description"><?php echo esc_html('Definieer groepen om campagnes te organiseren. In de loop kies je per campagne-blok welke groepen getoond worden.'); ?></p>
        <div class="teksttv-table-scroll">
            <table class="widefat teksttv-management-table" id="teksttv-groups" @click="groupsClick($event)">
                <thead>
                    <tr>
                        <th><?php echo esc_html('Naam'); ?></th>
                        <th class="teksttv-table-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $gi => $group) :
                        $group_label_id = 'teksttv-group-' . (string) $gi . '-label';
                        ?>
                    <tr class="teksttv-group-row">
                        <td>
                            <input type="hidden" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][id]" value="<?php echo esc_attr($group['id']); ?>" />
                            <label class="teksttv-mobile-field-label" for="<?php echo esc_attr($group_label_id); ?>"><?php echo esc_html('Naam'); ?></label>
                            <input type="text" id="<?php echo esc_attr($group_label_id); ?>" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][label]" value="<?php echo esc_attr($group['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr('bijv. Campagne'); ?>" autocomplete="off" />
                        </td>
                        <td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-group" aria-label="<?php echo esc_attr('Groep verwijderen'); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($groups)) : ?>
                    <tr class="teksttv-table-empty">
                        <td colspan="2"><?php echo esc_html('Nog geen groepen. Voeg een groep toe om campagnes te ordenen.'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-group" @click.prevent="addGroupRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Groep toevoegen'); ?></button>
        </div>
    </section>

    <section class="teksttv-card teksttv-workbench-section teksttv-campaign-workbench">
        <h2><?php echo esc_html('Campagnes'); ?></h2>
        <div id="teksttv-campaigns" data-empty-focus="#teksttv-add-campaign" data-empty-icon="megaphone" data-empty-text="<?php echo esc_attr('Nog geen campagnes. Voeg een campagne toe om te beginnen.'); ?>" @click="blocksClick($event)" @change="blocksFieldChange($event)" @input="blocksFieldChange($event)">
            <?php
            if (!empty($campaigns)) {
                foreach ($campaigns as $i => $campaign) {
                    CampaignsPage::render_campaign($i, $campaign, $channels, $groups);
                }
            } else {
                ?>
                <div class="teksttv-empty-state">
                    <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                    <p><?php echo esc_html('Nog geen campagnes. Voeg een campagne toe om te beginnen.'); ?></p>
                </div>
                <?php
            }
            ?>
        </div>

        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-campaign" @click.prevent="addCampaignBlock()"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php echo esc_html('Campagne toevoegen'); ?></button>
            <div class="teksttv-view-actions">
                <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="expandAllBlocks()"><?php echo esc_html('Alles openklappen'); ?></button>
                <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="collapseAllBlocks()"><?php echo esc_html('Alles dichtklappen'); ?></button>
            </div>
        </div>
    </section>
    <div class="teksttv-form-actions"><?php submit_button('Wijzigingen opslaan', 'primary', 'submit', false); ?></div>
</form>

<template id="tmpl-teksttv-campaign">
    <?php CampaignsPage::render_campaign(0, [], $channels, $groups); ?>
</template>

</div>
