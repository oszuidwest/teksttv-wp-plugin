<?php
/**
 * Loop page template.
 *
 * @var string $channel_slug
 * @var string $channel_label
 * @var list<array{slug: string, label: string}> $channels
 * @var list<array<string, mixed>> $blocks
 * @var string $api_url
 * @var string $page_title
 * @var list<array<string, mixed>> $ticker_items
 */

namespace TekstTV;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- all output is escaped inline

echo '<div class="wrap">';
echo '<h1>' . esc_html($page_title) . '</h1>';
settings_errors('teksttv');

?>
<div class="teksttv-tab-content">
    <div class="teksttv-loop-header">
        <span class="teksttv-api-url">
            <span class="dashicons dashicons-rest-api"></span>
            API: <code><a href="<?php echo esc_url($api_url); ?>" target="_blank"><?php echo esc_html($api_url); ?></a></code>
        </span>
    </div>

    <form method="post">
        <?php wp_nonce_field('teksttv_save_loop', 'teksttv_loop_nonce'); ?>
        <input type="hidden" name="teksttv_loop_channel" value="<?php echo esc_attr($channel_slug); ?>" />

        <div id="teksttv-blocks" class="teksttv-blocks">
            <?php
            if (!empty($blocks)) {
                foreach ($blocks as $i => $block) {
                    AdminPage::render_block_generic($i, $block);
                }
            } else {
                ?>
                <div class="teksttv-empty-state" id="teksttv-empty-state">
                    <span class="dashicons dashicons-playlist-video"></span><br />
                    Nog geen blokken. Voeg een artikelen-blok toe om te beginnen.
                </div>
                <?php
            }
            ?>
        </div>

        <div class="teksttv-add-block-bar">
            <div class="teksttv-dropdown-button">
                <button type="button" class="button" id="teksttv-add-block-toggle"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Blok toevoegen <span class="dashicons dashicons-arrow-down-alt2 teksttv-button-icon"></span></button>
                <div class="teksttv-dropdown-menu" id="teksttv-add-block-menu">
                    <?php foreach (BlockRegistry::all('loop') as $block_slug => $block_meta) : ?>
                    <button type="button" data-type="<?php echo esc_attr($block_slug); ?>"><span class="dashicons dashicons-<?php echo esc_attr($block_meta['icon']); ?>"></span> <?php echo esc_html($block_meta['label']); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <span class="teksttv-bar-spacer"></span>
            <button type="button" class="button-link" id="teksttv-expand-all">Alles openklappen</button>
            <button type="button" class="button-link" id="teksttv-collapse-all">Alles dichtklappen</button>
        </div>

        <!-- Ticker -->
        <h2 class="teksttv-ticker-heading">Ticker berichten</h2>
        <div id="teksttv-ticker" class="teksttv-blocks">
            <?php if (!empty($ticker_items)) :
                foreach ($ticker_items as $ti => $ticker_item) :
                    AdminPage::render_block_generic($ti, $ticker_item, 'teksttv_ticker');
                endforeach;
            endif; ?>
        </div>
        <?php $ticker_types = BlockRegistry::all('ticker'); ?>
        <div class="teksttv-add-block-bar">
            <?php if (count($ticker_types) > 1) : ?>
            <div class="teksttv-dropdown-button">
                <button type="button" class="button" id="teksttv-add-ticker-toggle"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Ticker toevoegen <span class="dashicons dashicons-arrow-down-alt2 teksttv-button-icon"></span></button>
                <div class="teksttv-dropdown-menu" id="teksttv-add-ticker-menu">
                    <?php foreach ($ticker_types as $ticker_slug => $ticker_meta) : ?>
                    <button type="button" data-type="<?php echo esc_attr($ticker_slug); ?>"><span class="dashicons dashicons-<?php echo esc_attr($ticker_meta['icon']); ?>"></span> <?php echo esc_html($ticker_meta['label']); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else :
                $single_ticker = array_key_first($ticker_types);
                ?>
            <button type="button" class="button" id="teksttv-add-ticker-single" data-type="<?php echo esc_attr((string) $single_ticker); ?>"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Ticker toevoegen</button>
            <?php endif; ?>
        </div>

        <?php
        // Ticker templates per type
        $ticker_types = BlockRegistry::all('ticker');
        foreach ($ticker_types as $ticker_type => $ticker_meta) : ?>
        <script type="text/html" id="tmpl-teksttv-ticker-<?php echo esc_attr($ticker_type); ?>">
            <?php AdminPage::render_block_generic('__TINDEX__', ['type' => $ticker_type], 'teksttv_ticker'); ?>
        </script>
        <?php endforeach; ?>

        <div class="teksttv-add-block-bar">
            <span class="teksttv-bar-spacer"></span>
            <button type="button" class="button-link" id="teksttv-expand-all">Alles openklappen</button>
            <button type="button" class="button-link" id="teksttv-collapse-all">Alles dichtklappen</button>
            <span class="teksttv-bar-spacer"></span>
            <?php submit_button('Loop opslaan', 'primary', 'submit', false); ?>
        </div>
    </form>

    <!-- Block templates (generated from registry) -->
    <?php foreach (BlockRegistry::all('loop') as $block_slug => $block_meta) : ?>
    <script type="text/html" id="tmpl-teksttv-block-<?php echo esc_attr($block_slug); ?>">
        <?php AdminPage::render_block_generic('__INDEX__', ['type' => $block_slug]); ?>
    </script>
    <?php endforeach; ?>
</div>
<?php

echo '</div>';
