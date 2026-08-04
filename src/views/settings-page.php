<?php
/**
 * Settings page template.
 *
 * @var list<array{slug: string, label: string}> $channels
 * @var string $api_base_url
 * @var list<string> $features
 * @var list<array{name: string, label: string, terms: array<int, string>}> $all_taxonomies
 * @var list<string> $enabled_taxonomies
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap teksttv-admin">';
echo '<h1>' . esc_html('Tekst TV-instellingen') . '</h1>';

/**
 * One renderer for both the saved rows and the add-row template, so the row
 * markup exists in exactly one place.
 *
 * @var callable(int|string, array{slug: string, label: string}): void $render_channel_row
 */
$render_channel_row = static function (int|string $i, array $ch) use ($api_base_url): void {
    $api_url = $ch['slug'] !== '' ? add_query_arg('channel', $ch['slug'], $api_base_url) : '';
    ?>
    <tr class="teksttv-channel-row">
        <td><label><span class="screen-reader-text teksttv-mobile-field-label"><?php echo esc_html('Slug'); ?></span><input type="text" name="teksttv_channels[<?php echo esc_attr((string) $i); ?>][slug]" value="<?php echo esc_attr($ch['slug']); ?>" class="regular-text" pattern="[a-z0-9\-]+" required placeholder="<?php echo esc_attr('bijv. tv1'); ?>" autocomplete="off" spellcheck="false" /></label></td>
        <td><label><span class="screen-reader-text teksttv-mobile-field-label"><?php echo esc_html('Naam'); ?></span><input type="text" name="teksttv_channels[<?php echo esc_attr((string) $i); ?>][label]" value="<?php echo esc_attr($ch['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr('bijv. TV 1'); ?>" autocomplete="off" /></label></td>
        <td class="teksttv-channel-endpoint"><button type="button" class="button teksttv-copy-endpoint" data-endpoint="<?php echo esc_url($api_url); ?>" <?php disabled($api_url === ''); ?>><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><span class="teksttv-copy-endpoint-label" aria-live="polite"><?php echo esc_html('Link kopiëren'); ?></span></button></td>
        <td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-channel" aria-label="<?php echo esc_attr('Kanaal verwijderen'); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>
    </tr>
    <?php
};

?>
<div class="teksttv-tab-content" x-data="teksttvSettingsPage">
    <form method="post" action="options.php" class="teksttv-admin-column teksttv-settings-form">
        <?php settings_fields('teksttv_settings'); ?>

        <!-- Channels -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Kanalen'); ?></h2>
            <p class="description"><?php echo esc_html('Beheer de kanalen waarvoor Tekst TV-slides worden gegenereerd. Elk kanaal krijgt een eigen loop en API-endpoint.'); ?></p>
            <div class="teksttv-table-scroll">
                <table class="widefat teksttv-management-table" id="teksttv-channels" data-api-base="<?php echo esc_url($api_base_url); ?>" @click="channelsClick($event)" @input="channelsInput($event)">
                    <thead>
                        <tr>
                            <th><?php echo esc_html('Slug'); ?></th>
                            <th><?php echo esc_html('Naam'); ?></th>
                            <th><?php echo esc_html('API'); ?></th>
                            <th class="teksttv-table-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels as $i => $ch) :
                            $render_channel_row($i, $ch);
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="teksttv-add-block-bar teksttv-section-actions">
                <button type="button" class="button teksttv-add-action" id="teksttv-add-channel" @click.prevent="addChannelRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Kanaal toevoegen'); ?></button>
            </div>
        </div>

        <!-- Features -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Berichteditor'); ?></h2>
            <p class="description"><?php echo esc_html('Bepaal welke opties beschikbaar zijn bij het bewerken van een bericht.'); ?></p>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="custom_title" <?php checked(in_array('custom_title', $features, true)); ?> />
                    <?php echo esc_html('Kop overschrijven'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="sidebar_image" <?php checked(in_array('sidebar_image', $features, true)); ?> />
                    <?php echo esc_html('Sidebar afbeelding kiezen'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="extra_images" <?php checked(in_array('extra_images', $features, true)); ?> />
                    <?php echo esc_html('Extra afbeeldingen'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="scheduling" <?php checked(in_array('scheduling', $features, true)); ?> />
                    <?php echo esc_html('Planning (datums & weekdagen)'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="page_separator" <?php checked(in_array('page_separator', $features, true)); ?> />
                    <?php echo esc_html('Paginascheiding (meerdere slides)'); ?>
                </label>
            </fieldset>
            <h3><?php echo esc_html('Tekstopmaak'); ?></h3>
            <fieldset class="teksttv-checkbox-list teksttv-checkbox-list--inline">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="bold" <?php checked(in_array('bold', $features, true)); ?> />
                    <strong><?php echo esc_html('Vet'); ?></strong>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="italic" <?php checked(in_array('italic', $features, true)); ?> />
                    <em><?php echo esc_html('Cursief'); ?></em>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="underline" <?php checked(in_array('underline', $features, true)); ?> />
                    <u><?php echo esc_html('Onderstreept'); ?></u>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="lists" <?php checked(in_array('lists', $features, true)); ?> />
                    <?php echo esc_html('Lijsten'); ?>
                </label>
            </fieldset>
            <h3><?php echo esc_html('AI'); ?></h3>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="ai_generate" <?php checked(in_array('ai_generate', $features, true)); ?> />
                    <?php echo esc_html('AI tekst genereren'); ?>
                </label>
            </fieldset>
        </div>

        <!-- Slide duur -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Slideduur'); ?></h2>
            <p class="description"><?php echo esc_html('Standaard weergaveduur per type slide. Kan per bericht worden overschreven.'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_duration_text"><?php echo esc_html('Tekstslide'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_text" name="teksttv_duration_text" value="<?php echo esc_attr(get_option('teksttv_duration_text', Helpers::DURATION_DEFAULTS['teksttv_duration_text'])); ?>" min="<?php echo esc_attr((string) Helpers::DURATION_MIN_SECONDS); ?>" max="<?php echo esc_attr((string) Helpers::DURATION_MAX_SECONDS); ?>" class="small-text" /> <?php echo esc_html('seconden'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_duration_image"><?php echo esc_html('Afbeeldingslide'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_image" name="teksttv_duration_image" value="<?php echo esc_attr(get_option('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image'])); ?>" min="<?php echo esc_attr((string) Helpers::DURATION_MIN_SECONDS); ?>" max="<?php echo esc_attr((string) Helpers::DURATION_MAX_SECONDS); ?>" class="small-text" /> <?php echo esc_html('seconden'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_duration_iframe"><?php echo esc_html('Iframe-slide'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_iframe" name="teksttv_duration_iframe" value="<?php echo esc_attr(get_option('teksttv_duration_iframe', Helpers::DURATION_DEFAULTS['teksttv_duration_iframe'])); ?>" min="<?php echo esc_attr((string) Helpers::DURATION_MIN_SECONDS); ?>" max="<?php echo esc_attr((string) Helpers::DURATION_MAX_SECONDS); ?>" class="small-text" /> <?php echo esc_html('seconden'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Taxonomies -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Taxonomiefilters'); ?></h2>
            <p class="description"><?php echo esc_html('Kies welke taxonomieën beschikbaar zijn als filter in de loopblokken.'); ?></p>
            <fieldset class="teksttv-checkbox-list">
                <?php foreach ($all_taxonomies as $tax) : ?>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_enabled_taxonomies[]" value="<?php echo esc_attr($tax['name']); ?>" <?php checked(in_array($tax['name'], $enabled_taxonomies, true)); ?> />
                    <?php echo esc_html($tax['label']); ?> <code><?php echo esc_html($tax['name']); ?></code>
                </label>
                <?php endforeach; ?>
            </fieldset>
        </div>

        <!-- Standaardwaarden -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Standaardwaarden'); ?></h2>
            <p class="description"><?php echo esc_html('Standaardinstellingen voor nieuwe Tekst TV-items bij berichten.'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_default_end_days"><?php echo esc_html('Standaard einddatum'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_default_end_days" name="teksttv_default_end_days" value="<?php echo esc_attr(get_option('teksttv_default_end_days', 7)); ?>" min="0" max="365" class="small-text" />
                        <?php echo esc_html('dagen na publicatiedatum'); ?>
                        <p class="description"><?php echo esc_html('Stel 0 in om geen standaard einddatum te gebruiken.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_max_post_age"><?php echo esc_html('Maximale leeftijd berichten'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_max_post_age" name="teksttv_max_post_age" value="<?php echo esc_attr(get_option('teksttv_max_post_age', 30)); ?>" min="0" max="365" class="small-text" />
                        <?php echo esc_html('dagen'); ?>
                        <p class="description"><?php echo esc_html('Berichten ouder dan dit worden niet meegenomen. Stel 0 in voor geen limiet.'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Weather -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Weer'); ?></h2>
            <p class="description"><?php echo esc_html('OpenWeather-API-configuratie voor weerslides.'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_openweather_api_key"><?php echo esc_html('API-sleutel'); ?></label></th>
                    <td>
                        <input type="text" id="teksttv_openweather_api_key" name="teksttv_openweather_api_key" value="<?php echo esc_attr(get_option('teksttv_openweather_api_key', '')); ?>" class="regular-text" autocomplete="off" spellcheck="false" />
                        <p class="description"><?php echo wp_kses('OneCall API 3.0 key van <a href="https://openweathermap.org/api" target="_blank" rel="noopener">openweathermap.org</a>.', ['a' => ['href' => [], 'target' => [], 'rel' => []]]); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Preview -->
        <div class="teksttv-card">
            <h2><?php echo esc_html('Preview'); ?></h2>
            <p class="description"><?php echo esc_html('Configureer de live preview die getoond wordt bij het bewerken van posts.'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_preview_url"><?php echo esc_html('Preview-URL'); ?></label></th>
                    <td>
                        <input type="url" id="teksttv_preview_url" name="teksttv_preview_url" value="<?php echo esc_attr(Helpers::get_preview_url()); ?>" class="large-text" placeholder="https://teksttv.example.com/zuidwest-1/preview" spellcheck="false" />
                        <p class="description"><?php echo esc_html('De volledige URL naar de previewpagina van de Tekst TV-frontend.'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php AdminPage::render_form_actions(); ?>
    </form>

    <template id="tmpl-teksttv-channel-row">
        <?php $render_channel_row(0, ['slug' => '', 'label' => '']); ?>
    </template>
</div>
<?php

echo '</div>';
