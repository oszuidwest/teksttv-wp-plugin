<?php
/**
 * Campaigns page template.
 *
 * @var list<array<string, mixed>> $campaigns
 * @var list<array{slug: string, label: string}> $channels
 * @var list<string> $groups
 */

namespace TekstTV;

echo '<div class="wrap">';
echo '<h1>Campagnes</h1>';
settings_errors('teksttv_campaigns');

?>
<form method="post">
    <?php wp_nonce_field('teksttv_save_campaigns', 'teksttv_campaigns_nonce'); ?>

    <!-- Groups management -->
    <div class="teksttv-card" style="margin-bottom:24px;">
        <h3>Groepen</h3>
        <p class="description">Definieer groepen om campagnes te organiseren. In de loop kies je per reclame-blok welke groepen getoond worden.</p>
        <table class="widefat teksttv-channels-table" id="teksttv-groups">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th class="teksttv-channel-actions"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $gi => $group_label) : ?>
                <tr class="teksttv-group-row">
                    <td><input type="text" name="teksttv_campaign_groups[]" value="<?php echo esc_attr($group_label); ?>" class="regular-text" required placeholder="Bijv. Reclame" /></td>
                    <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-group"><span class="dashicons dashicons-trash"></span></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="teksttv-card-actions">
            <button type="button" class="button" id="teksttv-add-group"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Groep toevoegen</button>
        </p>
    </div>

    <!-- Campaigns -->
    <h2>Campagnes</h2>
    <div id="teksttv-campaigns" class="teksttv-blocks">
        <?php
        if (!empty($campaigns)) {
            foreach ($campaigns as $i => $campaign) {
                CampaignsPage::render_campaign($i, $campaign, $channels, $groups);
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
    <?php CampaignsPage::render_campaign('__INDEX__', [], $channels, $groups); ?>
</script>

</div>
