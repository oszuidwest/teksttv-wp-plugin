<?php
/**
 * Content & AI settings page template.
 *
 * @var array{system: string, prompt_title: string, prompt_body: string, word_limit: int, title_char_limit: int, min_input_words: int, max_retries: int, rate_limit: int, region_taxonomy: string, provider: string, model: string, temperature: string|float, top_p: string|float, max_tokens: int} $prompts
 * @var list<array{name: string, label: string, terms: array<int, string>}> $all_taxonomies
 * @var array<string, array{label: string, models: array<string, string>}> $ai_models
 */

namespace TekstTV;

defined('ABSPATH') || exit;

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Content & AI', 'teksttv-wp-plugin') . '</h1>';

?>
<div class="teksttv-tab-content">
    <form method="post" action="options.php" class="teksttv-settings-form">
        <?php settings_fields('teksttv_content'); ?>

        <div class="teksttv-card">
            <h3><?php esc_html_e('Systeem instructie', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('De systeem instructie bepaalt de rol en stijl van de AI. Dit wordt bij elke generatie meegegeven.', 'teksttv-wp-plugin'); ?></p>
            <textarea name="teksttv_ai_prompts[system]" rows="4" class="large-text"><?php echo esc_textarea($prompts['system']); ?></textarea>
        </div>

        <div class="teksttv-card">
            <h3><?php esc_html_e('Prompt: Kop', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Instructie voor het genereren van de titel. De artikeltitel en inhoud worden automatisch toegevoegd.', 'teksttv-wp-plugin'); ?></p>
            <textarea name="teksttv_ai_prompts[prompt_title]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_title']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_title_char_limit"><?php esc_html_e('Tekenlimiet', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_title_char_limit" name="teksttv_ai_prompts[title_char_limit]" value="<?php echo esc_attr((string) $prompts['title_char_limit']); ?>" min="10" max="100" class="small-text" /> <?php esc_html_e('tekens', 'teksttv-wp-plugin'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3><?php esc_html_e('Prompt: Tekst', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php esc_html_e('Instructie voor het genereren van de body tekst. De artikeltitel en inhoud worden automatisch toegevoegd.', 'teksttv-wp-plugin'); ?></p>
            <textarea name="teksttv_ai_prompts[prompt_body]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_body']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_word_limit"><?php esc_html_e('Woordlimiet', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_word_limit" name="teksttv_ai_prompts[word_limit]" value="<?php echo esc_attr((string) $prompts['word_limit']); ?>" min="10" max="500" class="small-text" /> <?php esc_html_e('woorden', 'teksttv-wp-plugin'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3><?php esc_html_e('Overig', 'teksttv-wp-plugin'); ?></h3>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_min_input"><?php esc_html_e('Minimum input', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_min_input" name="teksttv_ai_prompts[min_input_words]" value="<?php echo esc_attr((string) $prompts['min_input_words']); ?>" min="0" max="500" class="small-text" /> <?php esc_html_e('woorden', 'teksttv-wp-plugin'); ?>
                        <p class="description"><?php esc_html_e('Minimum aantal woorden in het bronartikel. Stel 0 in om uit te schakelen.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_max_retries"><?php esc_html_e('Max pogingen', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_max_retries" name="teksttv_ai_prompts[max_retries]" value="<?php echo esc_attr((string) $prompts['max_retries']); ?>" min="1" max="5" class="small-text" />
                        <p class="description"><?php esc_html_e('Aantal pogingen als de output niet binnen het limiet valt. Elke extra poging kost een API-call.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_rate_limit"><?php esc_html_e('Rate limit', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_rate_limit" name="teksttv_ai_prompts[rate_limit]" value="<?php echo esc_attr((string) $prompts['rate_limit']); ?>" min="1" max="60" class="small-text" /> <?php esc_html_e('per minuut', 'teksttv-wp-plugin'); ?>
                        <p class="description"><?php esc_html_e('Maximaal aantal AI-verzoeken per gebruiker per minuut.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (current_user_can('manage_teksttv')) : ?>
        <div class="teksttv-card">
            <h3><?php esc_html_e('Regio-prefix', 'teksttv-wp-plugin'); ?></h3>
            <p class="description"><?php echo wp_kses(__('Voeg automatisch een regio-prefix toe aan de gegenereerde kop, bijv. <code>LEIDEN - Kop hier</code>.', 'teksttv-wp-plugin'), ['code' => []]); ?></p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_region_taxonomy"><?php esc_html_e('Taxonomy', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <?php $region_tax = $prompts['region_taxonomy']; ?>
                        <select id="teksttv_ai_region_taxonomy" name="teksttv_ai_prompts[region_taxonomy]">
                            <option value=""><?php esc_html_e('Geen regio-prefix', 'teksttv-wp-plugin'); ?></option>
                            <?php foreach ($all_taxonomies as $tax) : ?>
                                <option value="<?php echo esc_attr($tax['name']); ?>" <?php selected($region_tax, $tax['name']); ?>><?php echo esc_html($tax['label']); ?> (<?php echo esc_html($tax['name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo wp_kses(__('Kies de taxonomy waarvan de terms als regio-prefix worden gebruikt. Bij meerdere terms worden ze samengevoegd met <code>/</code>.', 'teksttv-wp-plugin'), ['code' => []]); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3><?php esc_html_e('Technisch', 'teksttv-wp-plugin'); ?></h3>
            <?php if (!empty($ai_models)) : ?>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_provider"><?php esc_html_e('Provider', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <select id="teksttv_ai_provider" name="teksttv_ai_prompts[provider]">
                            <option value=""><?php esc_html_e('Automatisch', 'teksttv-wp-plugin'); ?></option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($prompts['provider'], $provider_id); ?>><?php echo esc_html($provider_data['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Forceer een specifieke AI-provider. Bij "Automatisch" kiest WordPress de beste beschikbare provider.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_model"><?php esc_html_e('Model', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <select id="teksttv_ai_model" name="teksttv_ai_prompts[model]">
                            <option value=""><?php esc_html_e('Automatisch', 'teksttv-wp-plugin'); ?></option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <optgroup label="<?php echo esc_attr($provider_data['label']); ?>">
                                    <?php foreach ($provider_data['models'] as $model_id => $model_name) : ?>
                                        <?php $value = $provider_id . '/' . $model_id; ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($prompts['model'], $value); ?>><?php echo esc_html($model_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Forceer een specifiek model. Overschrijft de provider-keuze hierboven.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <p class="description"><?php echo wp_kses(sprintf(/* translators: %s: WordPress Connectors admin URL */ __('Geen AI-providers beschikbaar. Configureer een provider via <a href="%s">WordPress Connectors</a>.', 'teksttv-wp-plugin'), esc_url(admin_url('options-connectors.php'))), ['a' => ['href' => []]]); ?></p>
            <?php endif; ?>
            <h4><?php esc_html_e('Model parameters', 'teksttv-wp-plugin'); ?></h4>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_temperature"><?php esc_html_e('Temperature', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_temperature" name="teksttv_ai_prompts[temperature]" value="<?php echo esc_attr($prompts['temperature']); ?>" min="0" max="2" step="0.1" class="small-text" />
                        <p class="description"><?php esc_html_e('Creativiteit van de output. 0 = deterministisch, 1 = standaard, 2 = zeer creatief. Leeg = provider default.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_top_p"><?php esc_html_e('Top P', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_top_p" name="teksttv_ai_prompts[top_p]" value="<?php echo esc_attr($prompts['top_p']); ?>" min="0" max="1" step="0.05" class="small-text" />
                        <p class="description"><?php esc_html_e('Nucleus sampling. Lagere waarde = meer gefocust. Leeg = provider default.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_max_tokens"><?php esc_html_e('Max tokens', 'teksttv-wp-plugin'); ?></label></th>
                    <td>
                        <input type="number" id="teksttv_ai_max_tokens" name="teksttv_ai_prompts[max_tokens]" value="<?php echo esc_attr((string) $prompts['max_tokens']); ?>" min="64" max="8192" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e('Maximaal aantal tokens in de AI-response. Standaard 2048.', 'teksttv-wp-plugin'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php submit_button(__('Instellingen opslaan', 'teksttv-wp-plugin')); ?>
    </form>
</div>
<?php

echo '</div>';
