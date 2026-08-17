<?php
/**
 * Commercials admin template.
 *
 * @var list<array<string, mixed>> $campaigns
 * @var list<array{slug: string, label: string}> $channels
 * @var list<array{id: string, label: string}> $commercial_blocks
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap teksttv-admin">';
echo '<h1>' . esc_html('Reclame') . '</h1>';
settings_errors('teksttv_commercials');

/**
 * Shared row renderer; the server derives IDs for new rows.
 *
 * @var callable(int|string, array{id: string, label: string}): void $render_commercial_block_row
 */
$render_commercial_block_row = static function (int|string $block_index, array $commercial_block): void {
    ?>
    <tr class="teksttv-commercial-block-row">
        <td>
            <input type="hidden" name="teksttv_commercial_blocks[<?php echo esc_attr((string) $block_index); ?>][id]" value="<?php echo esc_attr($commercial_block['id']); ?>" />
            <label><span class="screen-reader-text teksttv-mobile-field-label"><?php echo esc_html('Naam'); ?></span><input type="text" name="teksttv_commercial_blocks[<?php echo esc_attr((string) $block_index); ?>][label]" value="<?php echo esc_attr($commercial_block['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr('bijv. Lokale sponsors'); ?>" autocomplete="off" /></label>
        </td>
        <td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-commercial-block"><?php echo esc_html('Verwijderen'); ?></button></td>
    </tr>
    <?php
};

?>
<form method="post" class="teksttv-admin-column" x-data="teksttvCommercialsPage">
    <?php wp_nonce_field('teksttv_save_commercials', 'teksttv_commercials_nonce'); ?>

    <section class="teksttv-card teksttv-commercial-blocks teksttv-workbench-section">
        <h2><?php echo esc_html('Reclameblokken'); ?></h2>
        <p class="description"><?php echo esc_html('Definieer reclameblokken om campagnes te organiseren. Met het looponderdeel Reclame kies je welke reclameblokken worden uitgezonden.'); ?></p>
        <div class="teksttv-table-scroll">
            <table class="widefat teksttv-management-table" id="teksttv-commercial-blocks" @click="commercialBlocksClick($event)">
                <thead>
                    <tr>
                        <th><?php echo esc_html('Naam'); ?></th>
                        <th class="teksttv-table-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="teksttv-table-empty">
                        <td colspan="2"><?php echo esc_html('Nog geen reclameblokken. Voeg een reclameblok toe om campagnes te ordenen.'); ?></td>
                    </tr>
                    <?php foreach ($commercial_blocks as $block_index => $commercial_block) :
                        $render_commercial_block_row($block_index, $commercial_block);
                    endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-commercial-block" @click.prevent="addCommercialBlockRow()"><?php echo esc_html('Reclameblok toevoegen'); ?></button>
        </div>
    </section>

    <section class="teksttv-card teksttv-workbench-section teksttv-campaign-workbench">
        <h2><?php echo esc_html('Campagnes'); ?></h2>
        <div id="teksttv-campaigns" data-empty-focus="#teksttv-add-campaign" @click="blocksClick($event)" @change="blocksFieldChange($event)" @input="blocksFieldChange($event)">
            <?php // Keep sortable blocks as contiguous siblings. ?>
            <?php AdminPage::render_empty_state('megaphone', 'Nog geen campagnes', 'Voeg een campagne toe en koppel daarna de gewenste slides.'); ?>
            <?php foreach ($campaigns as $i => $campaign) {
                CommercialsPage::render_campaign($i, $campaign, $channels, $commercial_blocks);
            } ?>
        </div>

        <div class="teksttv-add-block-bar teksttv-section-actions">
            <button type="button" class="button teksttv-add-action" id="teksttv-add-campaign" @click.prevent="addCampaignBlock()"><?php echo esc_html('Campagne toevoegen'); ?></button>
            <div class="teksttv-view-actions">
                <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="setAllBlocksOpen(true)"><?php echo esc_html('Alles openklappen'); ?></button>
                <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="setAllBlocksOpen(false)"><?php echo esc_html('Alles dichtklappen'); ?></button>
            </div>
        </div>
    </section>
    <?php AdminPage::render_form_actions(); ?>
</form>

<template id="tmpl-teksttv-campaign">
    <?php CommercialsPage::render_campaign(0, [], $channels, $commercial_blocks); ?>
</template>

<template id="tmpl-teksttv-commercial-block-row">
    <?php $render_commercial_block_row(0, ['id' => '', 'label' => '']); ?>
</template>

</div>
