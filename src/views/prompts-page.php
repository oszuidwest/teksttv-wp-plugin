<?php
/**
 * Content & AI settings page template.
 *
 * @var array{system: string, prompt_title: string, prompt_body: string, word_limit: int, title_char_limit: int, min_input_words: int, max_retries: int, rate_limit: int, region_taxonomy: string, provider: string, model: string, temperature: string|float, top_p: string|float, max_tokens: int} $prompts
 * @var list<array{name: string, label: string, terms: array<int, string>}> $all_taxonomies
 * @var array<string, array{label: string, models: array<string, string>}> $ai_models
 */

namespace TekstTV;

echo '<div class="wrap">';
echo '<h1>Content & AI</h1>';

?>
<div class="teksttv-tab-content">
    <form method="post" action="options.php" class="teksttv-settings-form">
        <?php settings_fields('teksttv_content'); ?>

        <div class="teksttv-card">
            <h3>Systeem instructie</h3>
            <p class="description">De systeem instructie bepaalt de rol en stijl van de AI. Dit wordt bij elke generatie meegegeven.</p>
            <textarea name="teksttv_ai_prompts[system]" rows="4" class="large-text"><?php echo esc_textarea($prompts['system']); ?></textarea>
        </div>

        <div class="teksttv-card">
            <h3>Prompt: Kop</h3>
            <p class="description">Instructie voor het genereren van de titel. De artikeltitel en inhoud worden automatisch toegevoegd.</p>
            <textarea name="teksttv_ai_prompts[prompt_title]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_title']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_title_char_limit">Tekenlimiet</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_title_char_limit" name="teksttv_ai_prompts[title_char_limit]" value="<?php echo esc_attr((string) $prompts['title_char_limit']); ?>" min="10" max="100" class="small-text" /> tekens
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3>Prompt: Tekst</h3>
            <p class="description">Instructie voor het genereren van de body tekst. De artikeltitel en inhoud worden automatisch toegevoegd.</p>
            <textarea name="teksttv_ai_prompts[prompt_body]" rows="3" class="large-text"><?php echo esc_textarea($prompts['prompt_body']); ?></textarea>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_word_limit">Woordlimiet</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_word_limit" name="teksttv_ai_prompts[word_limit]" value="<?php echo esc_attr((string) $prompts['word_limit']); ?>" min="10" max="500" class="small-text" /> woorden
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3>Overig</h3>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_min_input">Minimum input</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_min_input" name="teksttv_ai_prompts[min_input_words]" value="<?php echo esc_attr((string) $prompts['min_input_words']); ?>" min="0" max="500" class="small-text" /> woorden
                        <p class="description">Minimum aantal woorden in het bronartikel. Stel 0 in om uit te schakelen.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_max_retries">Max pogingen</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_max_retries" name="teksttv_ai_prompts[max_retries]" value="<?php echo esc_attr((string) $prompts['max_retries']); ?>" min="1" max="5" class="small-text" />
                        <p class="description">Aantal pogingen als de output niet binnen het limiet valt. Elke extra poging kost een API-call.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_rate_limit">Rate limit</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_rate_limit" name="teksttv_ai_prompts[rate_limit]" value="<?php echo esc_attr((string) $prompts['rate_limit']); ?>" min="1" max="60" class="small-text" /> per minuut
                        <p class="description">Maximaal aantal AI-verzoeken per gebruiker per minuut.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (current_user_can('manage_teksttv')) : ?>
        <div class="teksttv-card">
            <h3>Regio-prefix</h3>
            <p class="description">Voeg automatisch een regio-prefix toe aan de gegenereerde kop, bijv. <code>LEIDEN - Kop hier</code>.</p>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_region_taxonomy">Taxonomy</label></th>
                    <td>
                        <?php $region_tax = $prompts['region_taxonomy']; ?>
                        <select id="teksttv_ai_region_taxonomy" name="teksttv_ai_prompts[region_taxonomy]">
                            <option value="">Geen regio-prefix</option>
                            <?php foreach ($all_taxonomies as $tax) : ?>
                                <option value="<?php echo esc_attr($tax['name']); ?>" <?php selected($region_tax, $tax['name']); ?>><?php echo esc_html($tax['label']); ?> (<?php echo esc_html($tax['name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Kies de taxonomy waarvan de terms als regio-prefix worden gebruikt. Bij meerdere terms worden ze samengevoegd met <code>/</code>.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="teksttv-card">
            <h3>Technisch</h3>
            <?php if (!empty($ai_models)) : ?>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_provider">Provider</label></th>
                    <td>
                        <select id="teksttv_ai_provider" name="teksttv_ai_prompts[provider]">
                            <option value="">Automatisch</option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($prompts['provider'], $provider_id); ?>><?php echo esc_html($provider_data['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Forceer een specifieke AI-provider. Bij "Automatisch" kiest WordPress de beste beschikbare provider.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_model">Model</label></th>
                    <td>
                        <select id="teksttv_ai_model" name="teksttv_ai_prompts[model]">
                            <option value="">Automatisch</option>
                            <?php foreach ($ai_models as $provider_id => $provider_data) : ?>
                                <optgroup label="<?php echo esc_attr($provider_data['label']); ?>">
                                    <?php foreach ($provider_data['models'] as $model_id => $model_name) : ?>
                                        <?php $value = $provider_id . '/' . $model_id; ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($prompts['model'], $value); ?>><?php echo esc_html($model_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Forceer een specifiek model. Overschrijft de provider-keuze hierboven.</p>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <p class="description">Geen AI-providers beschikbaar. Configureer een provider via <a href="<?php echo esc_url(admin_url('options-connectors.php')); ?>">WordPress Connectors</a>.</p>
            <?php endif; ?>
            <h4>Model parameters</h4>
            <table class="form-table teksttv-form-table">
                <tr>
                    <th scope="row"><label for="teksttv_ai_temperature">Temperature</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_temperature" name="teksttv_ai_prompts[temperature]" value="<?php echo esc_attr($prompts['temperature']); ?>" min="0" max="2" step="0.1" class="small-text" />
                        <p class="description">Creativiteit van de output. 0 = deterministisch, 1 = standaard, 2 = zeer creatief. Leeg = provider default.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_top_p">Top P</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_top_p" name="teksttv_ai_prompts[top_p]" value="<?php echo esc_attr($prompts['top_p']); ?>" min="0" max="1" step="0.05" class="small-text" />
                        <p class="description">Nucleus sampling. Lagere waarde = meer gefocust. Leeg = provider default.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="teksttv_ai_max_tokens">Max tokens</label></th>
                    <td>
                        <input type="number" id="teksttv_ai_max_tokens" name="teksttv_ai_prompts[max_tokens]" value="<?php echo esc_attr((string) $prompts['max_tokens']); ?>" min="64" max="8192" step="1" class="small-text" />
                        <p class="description">Maximaal aantal tokens in de AI-response. Standaard 2048.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php submit_button('Instellingen opslaan'); ?>
    </form>
</div>
<?php

echo '</div>';
