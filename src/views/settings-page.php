<?php
/**
 * Settings page template.
 *
 * @var list<array{slug: string, label: string}> $channels
 * @var list<string> $features
 * @var list<array{name: string, label: string, terms: array<int, string>}> $all_taxonomies
 * @var list<string> $enabled_taxonomies
 */

namespace TekstTV;

echo '<div class="wrap">';
echo '<h1>Tekst TV Instellingen</h1>';

?>
<div class="teksttv-tab-content">
    <form method="post" action="options.php" class="teksttv-settings-form">
        <?php settings_fields('teksttv_settings'); ?>

        <!-- Channels -->
        <div class="teksttv-card">
            <h3>Kanalen</h3>
            <p class="description">Beheer de kanalen waarvoor Tekst TV slides worden gegenereerd. Elk kanaal krijgt een eigen loop en API endpoint.</p>
            <table class="widefat teksttv-channels-table" id="teksttv-channels">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Naam</th>
                        <th class="teksttv-channel-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($channels as $i => $ch) : ?>
                    <tr class="teksttv-channel-row">
                        <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][slug]" value="<?php echo esc_attr($ch['slug']); ?>" class="regular-text" pattern="[a-z0-9\-]+" required placeholder="bijv. tv1" /></td>
                        <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][label]" value="<?php echo esc_attr($ch['label']); ?>" class="regular-text" required placeholder="bijv. TV 1" /></td>
                        <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-channel"><span class="dashicons dashicons-trash"></span></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="teksttv-card-actions">
                <button type="button" class="button" id="teksttv-add-channel"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> Kanaal toevoegen</button>
            </p>
        </div>

        <!-- Features -->
        <div class="teksttv-card">
            <h3>Post editor features</h3>
            <p class="description">Bepaal welke opties beschikbaar zijn bij het bewerken van een post.</p>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="custom_title" <?php checked(in_array('custom_title', $features, true)); ?> />
                    Kop overschrijven
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="sidebar_image" <?php checked(in_array('sidebar_image', $features, true)); ?> />
                    Sidebar afbeelding kiezen
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="extra_images" <?php checked(in_array('extra_images', $features, true)); ?> />
                    Extra afbeeldingen
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="scheduling" <?php checked(in_array('scheduling', $features, true)); ?> />
                    Planning (datums &amp; weekdagen)
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="page_separator" <?php checked(in_array('page_separator', $features, true)); ?> />
                    Paginascheiding (meerdere slides)
                </label>
            </fieldset>
            <h4>Tekst opmaak</h4>
            <fieldset class="teksttv-checkbox-list teksttv-checkbox-list--inline">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="bold" <?php checked(in_array('bold', $features, true)); ?> />
                    <strong>Vet</strong>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="italic" <?php checked(in_array('italic', $features, true)); ?> />
                    <em>Cursief</em>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="underline" <?php checked(in_array('underline', $features, true)); ?> />
                    <u>Onderstreept</u>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="lists" <?php checked(in_array('lists', $features, true)); ?> />
                    Lijsten
                </label>
            </fieldset>
            <h4>AI</h4>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="ai_generate" <?php checked(in_array('ai_generate', $features, true)); ?> />
                    AI tekst genereren
                </label>
            </fieldset>
        </div>

        <!-- Slide duur -->
        <div class="teksttv-card">
            <h3>Slide duur</h3>
            <p class="description">Standaard weergaveduur per slide type. Kan per post worden overschreven.</p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_duration_text">Tekstslide</label></th>
                    <td>
                        <input type="number" id="teksttv_duration_text" name="teksttv_duration_text" value="<?php echo esc_attr(get_option('teksttv_duration_text', 20)); ?>" min="1" max="120" class="small-text" /> seconden
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_duration_image">Afbeeldingslide</label></th>
                    <td>
                        <input type="number" id="teksttv_duration_image" name="teksttv_duration_image" value="<?php echo esc_attr(get_option('teksttv_duration_image', 7)); ?>" min="1" max="120" class="small-text" /> seconden
                    </td>
                </tr>
            </table>
        </div>

        <!-- Taxonomies -->
        <div class="teksttv-card">
            <h3>Taxonomy filters</h3>
            <p class="description">Kies welke taxonomies beschikbaar zijn als filter in de loop-blokken.</p>
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
            <h3>Standaardwaarden</h3>
            <p class="description">Standaard instellingen voor nieuwe Tekst TV items op posts.</p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_default_end_days">Standaard einddatum</label></th>
                    <td>
                        <input type="number" id="teksttv_default_end_days" name="teksttv_default_end_days" value="<?php echo esc_attr(get_option('teksttv_default_end_days', 7)); ?>" min="0" max="365" class="small-text" />
                        dagen na publicatiedatum
                        <p class="description">Stel 0 in om geen standaard einddatum te gebruiken.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_max_post_age">Maximale leeftijd berichten</label></th>
                    <td>
                        <input type="number" id="teksttv_max_post_age" name="teksttv_max_post_age" value="<?php echo esc_attr(get_option('teksttv_max_post_age', 30)); ?>" min="0" max="365" class="small-text" />
                        dagen
                        <p class="description">Berichten ouder dan dit worden niet meegenomen. Stel 0 in voor geen limiet.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Weather -->
        <div class="teksttv-card">
            <h3>Weer</h3>
            <p class="description">OpenWeather API configuratie voor weer-slides.</p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_openweather_api_key">API key</label></th>
                    <td>
                        <input type="text" id="teksttv_openweather_api_key" name="teksttv_openweather_api_key" value="<?php echo esc_attr(get_option('teksttv_openweather_api_key', '')); ?>" class="regular-text" />
                        <p class="description">OneCall API 3.0 key van <a href="https://openweathermap.org/api" target="_blank" rel="noopener">openweathermap.org</a>.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Preview -->
        <div class="teksttv-card">
            <h3>Preview</h3>
            <p class="description">Configureer de live preview die getoond wordt bij het bewerken van posts.</p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_preview_url">Preview URL</label></th>
                    <td>
                        <input type="url" id="teksttv_preview_url" name="teksttv_preview_url" value="<?php echo esc_attr(Helpers::get_preview_url()); ?>" class="large-text" placeholder="https://teksttv.example.com/zuidwest-1/preview" />
                        <p class="description">De volledige URL naar de TekstTV frontend preview pagina.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('Instellingen opslaan'); ?>
    </form>
</div>
<?php

echo '</div>';
