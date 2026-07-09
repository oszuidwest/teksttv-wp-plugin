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

defined('ABSPATH') || exit;

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Tekst TV Instellingen', 'teksttv-wp-plugin') . '</h1>';

?>
<div class="teksttv-tab-content" x-data="teksttvSettingsPage">
    <form method="post" action="options.php" class="teksttv-settings-form">
        <?php settings_fields('teksttv_settings'); ?>

        <!-- Channels -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Kanalen', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Beheer de kanalen waarvoor Tekst TV slides worden gegenereerd. Elk kanaal krijgt een eigen loop en API endpoint.', 'teksttv-wp-plugin'); ?></p>
            <table class="widefat teksttv-channels-table" id="teksttv-channels" @click="channelsClick($event)">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Slug', 'teksttv-wp-plugin'); ?></th>
                        <th><?php esc_html_e('Naam', 'teksttv-wp-plugin'); ?></th>
                        <th class="teksttv-channel-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($channels as $i => $ch) : ?>
                    <tr class="teksttv-channel-row">
                        <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][slug]" value="<?php echo esc_attr($ch['slug']); ?>" class="regular-text" pattern="[a-z0-9\-]+" required placeholder="<?php echo esc_attr__('bijv. tv1', 'teksttv-wp-plugin'); ?>" /></td>
                        <td><input type="text" name="teksttv_channels[<?php echo $i; ?>][label]" value="<?php echo esc_attr($ch['label']); ?>" class="regular-text" required placeholder="<?php echo esc_attr__('bijv. TV 1', 'teksttv-wp-plugin'); ?>" /></td>
                        <td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-channel"><span class="dashicons dashicons-trash"></span></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="teksttv-card-actions">
                <button type="button" class="button" id="teksttv-add-channel" @click.prevent="addChannelRow()"><span class="dashicons dashicons-plus-alt2 teksttv-button-icon"></span> <?php esc_html_e('Kanaal toevoegen', 'teksttv-wp-plugin'); ?></button>
            </p>
        </div>

        <!-- Features -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Post editor features', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Bepaal welke opties beschikbaar zijn bij het bewerken van een post.', 'teksttv-wp-plugin'); ?></p>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="custom_title" <?php checked(in_array('custom_title', $features, true)); ?> />
                    <?php esc_html_e('Kop overschrijven', 'teksttv-wp-plugin'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="sidebar_image" <?php checked(in_array('sidebar_image', $features, true)); ?> />
                    <?php esc_html_e('Sidebar afbeelding kiezen', 'teksttv-wp-plugin'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="extra_images" <?php checked(in_array('extra_images', $features, true)); ?> />
                    <?php esc_html_e('Extra afbeeldingen', 'teksttv-wp-plugin'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="scheduling" <?php checked(in_array('scheduling', $features, true)); ?> />
                    <?php esc_html_e('Planning (datums & weekdagen)', 'teksttv-wp-plugin'); ?>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="page_separator" <?php checked(in_array('page_separator', $features, true)); ?> />
                    <?php esc_html_e('Paginascheiding (meerdere slides)', 'teksttv-wp-plugin'); ?>
                </label>
            </fieldset>
            <h4><?php esc_html_e('Tekst opmaak', 'teksttv-wp-plugin'); ?></h4>
            <fieldset class="teksttv-checkbox-list teksttv-checkbox-list--inline">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="bold" <?php checked(in_array('bold', $features, true)); ?> />
                    <strong><?php esc_html_e('Vet', 'teksttv-wp-plugin'); ?></strong>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="italic" <?php checked(in_array('italic', $features, true)); ?> />
                    <em><?php esc_html_e('Cursief', 'teksttv-wp-plugin'); ?></em>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="underline" <?php checked(in_array('underline', $features, true)); ?> />
                    <u><?php esc_html_e('Onderstreept', 'teksttv-wp-plugin'); ?></u>
                </label>
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="lists" <?php checked(in_array('lists', $features, true)); ?> />
                    <?php esc_html_e('Lijsten', 'teksttv-wp-plugin'); ?>
                </label>
            </fieldset>
            <h4><?php esc_html_e('AI', 'teksttv-wp-plugin'); ?></h4>
            <fieldset class="teksttv-checkbox-list">
                <label class="teksttv-checkbox-list-item">
                    <input type="checkbox" name="teksttv_features[]" value="ai_generate" <?php checked(in_array('ai_generate', $features, true)); ?> />
                    <?php esc_html_e('AI tekst genereren', 'teksttv-wp-plugin'); ?>
                </label>
            </fieldset>
        </div>

        <!-- Slide duur -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Slide duur', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Standaard weergaveduur per slide type. Kan per post worden overschreven.', 'teksttv-wp-plugin'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_duration_text"><?php esc_html_e('Tekstslide', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_text" name="teksttv_duration_text" value="<?php echo esc_attr(get_option('teksttv_duration_text', 20)); ?>" min="1" max="120" class="small-text" /> <?php esc_html_e('seconden', 'teksttv-wp-plugin'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_duration_image"><?php esc_html_e('Afbeeldingslide', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_image" name="teksttv_duration_image" value="<?php echo esc_attr(get_option('teksttv_duration_image', 7)); ?>" min="1" max="120" class="small-text" /> <?php esc_html_e('seconden', 'teksttv-wp-plugin'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_duration_iframe"><?php esc_html_e('Iframe-slide', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_duration_iframe" name="teksttv_duration_iframe" value="<?php echo esc_attr(get_option('teksttv_duration_iframe', 30)); ?>" min="1" max="120" class="small-text" /> <?php esc_html_e('seconden', 'teksttv-wp-plugin'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Taxonomies -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Taxonomy filters', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Kies welke taxonomies beschikbaar zijn als filter in de loop-blokken.', 'teksttv-wp-plugin'); ?></p>
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
            <h3><?php esc_html_e('Standaardwaarden', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Standaard instellingen voor nieuwe Tekst TV items op posts.', 'teksttv-wp-plugin'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_default_end_days"><?php esc_html_e('Standaard einddatum', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_default_end_days" name="teksttv_default_end_days" value="<?php echo esc_attr(get_option('teksttv_default_end_days', 7)); ?>" min="0" max="365" class="small-text" />
                        <?php esc_html_e('dagen na publicatiedatum', 'teksttv-wp-plugin'); ?>
                        <p class="description"><?php esc_html_e('Stel 0 in om geen standaard einddatum te gebruiken.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_max_post_age"><?php esc_html_e('Maximale leeftijd berichten', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_max_post_age" name="teksttv_max_post_age" value="<?php echo esc_attr(get_option('teksttv_max_post_age', 30)); ?>" min="0" max="365" class="small-text" />
                        <?php esc_html_e('dagen', 'teksttv-wp-plugin'); ?>
                        <p class="description"><?php esc_html_e('Berichten ouder dan dit worden niet meegenomen. Stel 0 in voor geen limiet.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Weather -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Weer', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('OpenWeather API configuratie voor weer-slides.', 'teksttv-wp-plugin'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_openweather_api_key"><?php esc_html_e('API key', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="text" id="teksttv_openweather_api_key" name="teksttv_openweather_api_key" value="<?php echo esc_attr(get_option('teksttv_openweather_api_key', '')); ?>" class="regular-text" />
                        <p class="description"><?php echo wp_kses(__('OneCall API 3.0 key van <a href="https://openweathermap.org/api" target="_blank" rel="noopener">openweathermap.org</a>.', 'teksttv-wp-plugin'), ['a' => ['href' => [], 'target' => [], 'rel' => []]]); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Preview -->
        <div class="teksttv-card">
            <h3><?php esc_html_e('Preview', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Configureer de live preview die getoond wordt bij het bewerken van posts.', 'teksttv-wp-plugin'); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_preview_url"><?php esc_html_e('Preview URL', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="url" id="teksttv_preview_url" name="teksttv_preview_url" value="<?php echo esc_attr(Helpers::get_preview_url()); ?>" class="large-text" placeholder="https://teksttv.example.com/zuidwest-1/preview" />
                        <p class="description"><?php esc_html_e('De volledige URL naar de TekstTV frontend preview pagina.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Instellingen opslaan', 'teksttv-wp-plugin')); ?>
    </form>
</div>
<?php

echo '</div>';
