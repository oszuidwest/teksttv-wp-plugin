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

/**
 * One renderer for both the saved rows and the add-row template, so the row
 * markup exists in exactly one place. New rows render an empty id; the server
 * derives a stable id from the label on save.
 *
 * @var callable(int|string, array{id: string, label: string}): void $render_group_row
 */
$render_group_row = static function (int|string $gi, array $group): void {
    ?>
    <tr class="teksttv-group-row">
        <td>
            <input type="hidden" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][id]" value="<?php echo esc_attr($group['id']); ?>" />
            <label><span class="screen-reader-text teksttv-mobile-field-label"><?php echo esc_html('Naam'); ?></span><input type="text" name="teksttv_campaign_groups[<?php echo esc_attr((string) $gi); ?>][label]" value="<?php echo esc_attr($group['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr('bijv. Campagne'); ?>" autocomplete="off" /></label>
        </td>
        <td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-group" aria-label="<?php echo esc_attr('Groep verwijderen'); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>
    </tr>
    <?php
};

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
                    <tr class="teksttv-table-empty">
                        <td colspan="2"><?php echo esc_html('Nog geen groepen. Voeg een groep toe om campagnes te ordenen.'); ?></td>
                    </tr>
                    <?php foreach ($groups as $gi => $group) :
                        $render_group_row($gi, $group);
                    endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-group" @click.prevent="addGroupRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Groep toevoegen'); ?></button>
        </div>
    </section>

    <section class="teksttv-card teksttv-workbench-section teksttv-campaign-workbench">
        <h2><?php echo esc_html('Campagnes'); ?></h2>
        <div id="teksttv-campaigns" data-empty-focus="#teksttv-add-campaign" @click="blocksClick($event)" @change="blocksFieldChange($event)" @input="blocksFieldChange($event)">
            <?php // The empty state renders first so the blocks stay contiguous siblings (keyboard reorder walks siblings). ?>
            <div class="teksttv-empty-state">
                <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                <p><?php echo esc_html('Nog geen campagnes. Voeg een campagne toe om te beginnen.'); ?></p>
            </div>
            <?php foreach ($campaigns as $i => $campaign) {
                CampaignsPage::render_campaign($i, $campaign, $channels, $groups);
            } ?>
        </div>

        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-campaign" @click.prevent="addCampaignBlock()"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php echo esc_html('Campagne toevoegen'); ?></button>
            <div class="teksttv-view-actions">
                <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="setAllBlocksOpen(true)"><?php echo esc_html('Alles openklappen'); ?></button>
                <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="setAllBlocksOpen(false)"><?php echo esc_html('Alles dichtklappen'); ?></button>
            </div>
        </div>
    </section>
    <div class="teksttv-form-actions"><?php submit_button('Wijzigingen opslaan', 'primary', 'submit', false); ?></div>
</form>

<template id="tmpl-teksttv-campaign">
    <?php CampaignsPage::render_campaign(0, [], $channels, $groups); ?>
</template>

<template id="tmpl-teksttv-group-row">
    <?php $render_group_row(0, ['id' => '', 'label' => '']); ?>
</template>

</div>
