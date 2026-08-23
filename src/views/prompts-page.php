<?php
/**
 * Inhoud & AI settings page template.
 *
 * @var array{system: string, prompt_title: string, prompt_body: string, word_limit: int, word_limit_photo: int, title_char_limit: int, min_input_words: int, ensure_terminal_period: bool, region_taxonomy: string, provider: string, model: string} $prompts
 * @var list<array{name: string, label: string, terms: array<int, string>}> $all_taxonomies
 * @var array<string, array{label: string, models: array<string, string>}> $ai_models
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap teksttv-admin">';
echo '<h1>' . esc_html('Inhoud & AI') . '</h1>';

?>
<div class="teksttv-tab-content">
    <form method="post" action="options.php" class="teksttv-admin-column teksttv-settings-form">
        <?php settings_fields('teksttv_content'); ?>

        <div class="teksttv-card">
            <h2><?php echo esc_html('Systeeminstructie'); ?></h2>
            <p class="description"><?php echo esc_html('De systeeminstructie bepaalt de rol en stijl van de AI. Deze wordt bij elke generatie meegegeven.'); ?></p>
            <label class="screen-reader-text" for="teksttv_ai_system"><?php echo esc_html('Systeeminstructie'); ?></label>
            <textarea id="teksttv_ai_system" name="teksttv_ai_prompts[system]" rows="4" class="large-text" autocomplete="off"><?php echo esc_textarea($prompts['system']); ?></textarea>
        </div>

        <div class="teksttv-card">
            <h2><?php echo esc_html('Prompt: kop'); ?></h2>
            <p class="description">
                <?php echo esc_html('Instructie voor het genereren van de titel. De artikeltitel en inhoud worden automatisch toegevoegd.'); ?>
                <?php
                printf(
                    esc_html('Gebruik %s om de tekenlimiet in te vullen.'),
                    '<code>{{chars}}</code>'
                );
                ?>
            </p>
            <label class="screen-reader-text" for="teksttv_ai_prompt_title"><?php echo esc_html('Prompt voor de kop'); ?></label>
            <textarea id="teksttv_ai_prompt_title" name="teksttv_ai_prompts[prompt_title]" rows="3" class="large-text" autocomplete="off"><?php echo esc_textarea($prompts['prompt_title']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_title_char_limit"><?php echo esc_html('Tekenlimiet'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_title_char_limit" name="teksttv_ai_prompts[title_char_limit]" value="<?php echo esc_attr((string) $prompts['title_char_limit']); ?>" min="10" max="100" class="small-text" /> <?php echo esc_html('tekens'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h2><?php echo esc_html('Prompt: tekst'); ?></h2>
            <p class="description">
                <?php echo esc_html('Instructie voor het genereren van de tekst. De artikeltitel en inhoud worden automatisch toegevoegd.'); ?>
                <?php
                printf(
                    esc_html('Gebruik %s om de woordlimiet in te vullen.'),
                    '<code>{{words}}</code>'
                );
                ?>
            </p>
            <label class="screen-reader-text" for="teksttv_ai_prompt_body"><?php echo esc_html('Prompt voor de tekst'); ?></label>
            <textarea id="teksttv_ai_prompt_body" name="teksttv_ai_prompts[prompt_body]" rows="3" class="large-text" autocomplete="off"><?php echo esc_textarea($prompts['prompt_body']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_word_limit"><?php echo esc_html('Woordlimiet zonder foto'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_word_limit" name="teksttv_ai_prompts[word_limit]" value="<?php echo esc_attr((string) $prompts['word_limit']); ?>" min="10" max="500" class="small-text" /> <?php echo esc_html('woorden'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_word_limit_photo"><?php echo esc_html('Woordlimiet met foto'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_word_limit_photo" name="teksttv_ai_prompts[word_limit_photo]" value="<?php echo esc_attr((string) $prompts['word_limit_photo']); ?>" min="10" max="500" class="small-text" /> <?php echo esc_html('woorden'); ?>
                        <p class="description"><?php echo esc_html('Aantal woorden wanneer er een foto naast de tekst staat. De juiste limiet vult automatisch de {{words}}-placeholder.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('Afsluitende punt'); ?></th>
                    <td>
                        <input type="hidden" name="teksttv_ai_prompts[ensure_terminal_period]" value="0" />
                        <label for="teksttv_ai_ensure_terminal_period">
                            <input type="checkbox" id="teksttv_ai_ensure_terminal_period" name="teksttv_ai_prompts[ensure_terminal_period]" value="1" <?php checked($prompts['ensure_terminal_period']); ?> />
                            <?php echo esc_html('Voeg automatisch een punt toe als de gegenereerde tekst geen afsluitend leesteken heeft.'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h2><?php echo esc_html('Overig'); ?></h2>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_min_input"><?php echo esc_html('Minimale invoer'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_min_input" name="teksttv_ai_prompts[min_input_words]" value="<?php echo esc_attr((string) $prompts['min_input_words']); ?>" min="0" max="500" class="small-text" /> <?php echo esc_html('woorden'); ?>
                        <p class="description"><?php echo esc_html('Minimum aantal woorden in het bronartikel. Stel 0 in om uit te schakelen.'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (current_user_can('manage_teksttv')) : ?>
        <div class="teksttv-card">
            <h2><?php echo esc_html('Regiovoorvoegsel'); ?></h2>
            <p class="description"><?php echo wp_kses('Voeg automatisch een regio-prefix toe aan de gegenereerde tekst, bijv. <code>LEIDEN - Tekst hier</code>.', ['code' => []]); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_region_taxonomy"><?php echo esc_html('Taxonomie'); ?></label></th>
                    <td>
                        <?php $region_tax = $prompts['region_taxonomy']; ?>
                        <select id="teksttv_ai_region_taxonomy" name="teksttv_ai_prompts[region_taxonomy]">
                            <option value=""><?php echo esc_html('Geen regio-prefix'); ?></option>
                            <?php foreach ($all_taxonomies as $tax) : ?>
                                <option value="<?php echo esc_attr($tax['name']); ?>" <?php selected($region_tax, $tax['name']); ?>><?php echo esc_html($tax['label']); ?> (<?php echo esc_html($tax['name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo wp_kses('Kies de taxonomy waarvan de terms als regio-prefix worden gebruikt. Bij meerdere terms worden ze samengevoegd met <code>/</code>.', ['code' => []]); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h2><?php echo esc_html('Technisch'); ?></h2>
            <?php if (!empty($ai_models)) : ?>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_provider"><?php echo esc_html('Provider'); ?></label></th>
                    <td>
                        <select id="teksttv_ai_provider" name="teksttv_ai_prompts[provider]">
                            <option value=""><?php echo esc_html('Automatisch'); ?></option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($prompts['provider'], $provider_id); ?>><?php echo esc_html($provider_data['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html('Forceer een specifieke AI-provider. Bij "Automatisch" kiest WordPress de beste beschikbare provider.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_model"><?php echo esc_html('Model'); ?></label></th>
                    <td>
                        <select id="teksttv_ai_model" name="teksttv_ai_prompts[model]">
                            <option value=""><?php echo esc_html('Automatisch'); ?></option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <optgroup label="<?php echo esc_attr($provider_data['label']); ?>">
                                    <?php foreach ($provider_data['models'] as $model_id => $model_name) : ?>
                                        <?php $value = $provider_id . '/' . $model_id; ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($prompts['model'], $value); ?>><?php echo esc_html($model_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html('Forceer een specifiek model. Overschrijft de provider-keuze hierboven.'); ?></p>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <p class="description"><?php echo wp_kses(sprintf('Geen AI-providers beschikbaar. Configureer een provider via <a href="%s">WordPress Connectors</a>.', esc_url(admin_url('options-connectors.php'))), ['a' => ['href' => []]]); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php AdminPage::render_form_actions(); ?>
    </form>
</div>
<?php

echo '</div>';
