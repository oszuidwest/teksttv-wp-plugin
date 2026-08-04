<?php
/**
 * Loop page template.
 *
 * @var string $channel_slug
 * @var string $channel_label
 * @var list<array{slug: string, label: string}> $channels
 * @var list<array<string, mixed>> $blocks
 * @var string $page_title
 * @var list<array<string, mixed>> $ticker_items
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap teksttv-admin">';
echo '<h1>' . esc_html($page_title) . '</h1>';
settings_errors('teksttv');

/**
 * One renderer for both add-menus (blocks and ticker), so the dropdown markup
 * and its ARIA wiring exist in exactly one place. `$key` derives the element
 * ids (`teksttv-add-{$key}-*`), the `x-ref`, and the Alpine open-state
 * (`menu{Key}Open`); `$method` is the Alpine handler that inserts the type.
 *
 * @var callable(string, string, array<string, array{icon: string, label: string}>, string): void $render_add_menu
 */
$render_add_menu = static function (string $key, string $label, array $types, string $method): void {
    $state = 'menu' . ucfirst($key) . 'Open';
    ?>
    <div class="teksttv-dropdown-button" @click.outside="<?php echo esc_attr($state); ?> = false" @keydown.escape.prevent.stop="<?php echo esc_attr($state); ?> = false; $refs.<?php echo esc_attr($key); ?>Toggle.focus()">
        <button type="button" class="button teksttv-add-action" id="teksttv-add-<?php echo esc_attr($key); ?>-toggle" x-ref="<?php echo esc_attr($key); ?>Toggle" aria-haspopup="menu" aria-controls="teksttv-add-<?php echo esc_attr($key); ?>-menu" :aria-expanded="<?php echo esc_attr($state); ?>.toString()" @click.prevent.stop="<?php echo esc_attr($state); ?> = !<?php echo esc_attr($state); ?>"><span><?php echo esc_html($label); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
        <div class="teksttv-dropdown-menu" id="teksttv-add-<?php echo esc_attr($key); ?>-menu" role="menu" :class="{ 'is-open': <?php echo esc_attr($state); ?> }">
            <?php foreach ($types as $type_slug => $type_meta) : ?>
            <button type="button" role="menuitem" data-type="<?php echo esc_attr((string) $type_slug); ?>" @click.prevent="<?php echo esc_attr($state); ?> = false; <?php echo esc_attr($method); ?>('<?php echo esc_js((string) $type_slug); ?>')"><span class="dashicons dashicons-<?php echo esc_attr($type_meta['icon']); ?>" aria-hidden="true"></span> <?php echo esc_html($type_meta['label']); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
};

?>
<div class="teksttv-tab-content teksttv-admin-column" x-data="teksttvLoopPage">
    <form method="post">
        <?php wp_nonce_field('teksttv_save_loop', 'teksttv_loop_nonce'); ?>
        <input type="hidden" name="teksttv_loop_channel" value="<?php echo esc_attr($channel_slug); ?>" />

        <?php $ticker_types = BlockRegistry::all('ticker'); ?>

        <section class="teksttv-card teksttv-workbench-section">
            <h2><?php echo esc_html('Loop'); ?></h2>
            <div id="teksttv-blocks" data-empty-focus="#teksttv-add-block-toggle" @click="blocksClick($event)" @change="blocksFieldChange($event)" @input="blocksFieldChange($event)">
                <?php // The empty state renders first so the blocks stay contiguous siblings (keyboard reorder walks siblings). ?>
                <?php AdminPage::render_empty_state('playlist-video', 'Nog geen blokken. Voeg een blok toe om te beginnen.'); ?>
                <?php foreach ($blocks as $i => $block) {
                    AdminPage::render_block_generic($i, $block);
                } ?>
            </div>

            <div class="teksttv-add-block-bar teksttv-section-actions">
                <?php $render_add_menu('block', 'Blok toevoegen', BlockRegistry::all('loop'), 'addLoopBlock'); ?>
                <div class="teksttv-view-actions">
                    <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-all" @click.prevent="setAllBlocksOpen(true)"><?php echo esc_html('Alles openklappen'); ?></button>
                    <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-all" @click.prevent="setAllBlocksOpen(false)"><?php echo esc_html('Alles dichtklappen'); ?></button>
                </div>
            </div>
        </section>

        <section class="teksttv-card teksttv-workbench-section">
            <h2><?php echo esc_html('Tickerberichten'); ?></h2>
            <div id="teksttv-ticker" data-empty-focus="#teksttv-add-ticker-toggle, #teksttv-add-ticker-single" @click="tickerClick($event)" @change="tickerFieldChange($event)" @input="tickerFieldChange($event)">
                <?php AdminPage::render_empty_state('editor-alignleft', 'Nog geen tickerberichten. Voeg een tickerbericht toe om te beginnen.'); ?>
                <?php foreach ($ticker_items as $ti => $ticker_item) :
                    AdminPage::render_block_generic($ti, $ticker_item, 'teksttv_ticker');
                endforeach; ?>
            </div>
            <div class="teksttv-add-block-bar teksttv-section-actions">
                <?php if (count($ticker_types) > 1) :
                    $render_add_menu('ticker', 'Ticker toevoegen', $ticker_types, 'addTickerBlock');
                else :
                    $single_ticker = array_key_first($ticker_types);
                    ?>
                <button type="button" class="button teksttv-add-action" id="teksttv-add-ticker-single" data-type="<?php echo esc_attr((string) $single_ticker); ?>" @click.prevent="addTickerBlock('<?php echo esc_js((string) $single_ticker); ?>')"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Ticker toevoegen'); ?></button>
                <?php endif; ?>
                <div class="teksttv-view-actions">
                    <button type="button" class="button-link teksttv-action-expand-blocks" id="teksttv-expand-ticker" @click.prevent="setAllTickerOpen(true)"><?php echo esc_html('Alles openklappen'); ?></button>
                    <button type="button" class="button-link teksttv-action-collapse-blocks" id="teksttv-collapse-ticker" @click.prevent="setAllTickerOpen(false)"><?php echo esc_html('Alles dichtklappen'); ?></button>
                </div>
            </div>
        </section>

        <?php
        // Ticker templates per type
        foreach ($ticker_types as $ticker_type => $ticker_meta) : ?>
        <template id="tmpl-teksttv-ticker-<?php echo esc_attr($ticker_type); ?>">
            <?php AdminPage::render_block_generic(0, ['type' => $ticker_type], 'teksttv_ticker'); ?>
        </template>
        <?php endforeach; ?>

        <?php AdminPage::render_form_actions(); ?>
    </form>

    <!-- Block templates (generated from registry) -->
    <?php foreach (BlockRegistry::all('loop') as $block_slug => $block_meta) : ?>
    <template id="tmpl-teksttv-block-<?php echo esc_attr($block_slug); ?>">
        <?php AdminPage::render_block_generic(0, ['type' => $block_slug]); ?>
    </template>
    <?php endforeach; ?>
</div>
<?php

echo '</div>';
