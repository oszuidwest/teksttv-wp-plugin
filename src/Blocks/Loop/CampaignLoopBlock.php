<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Contracts\LoopBlock;
use TekstTV\Helpers;

final class CampaignLoopBlock implements LoopBlock
{
    private const TRANSITION_DURATION = 5000;

    public static function register(): void
    {
        BlockRegistry::register('campaign', [
            'label' => __('Campagne', 'teksttv-wp-plugin'),
            'icon' => 'megaphone',
            'color' => '#d63638',
            'context' => 'loop',
            'render' => [self::class, 'render_fields'],
            'save' => [self::class, 'save'],
            'build' => [self::class, 'build'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_fields(int|string $index, array $block, string $prefix): void
    {
        $selected_groups = (array) ($block['groups'] ?? []);
        $available_groups = Helpers::get_campaign_groups();
        $intro_id = $block['intro_image_id'] ?? 0;
        $outro_id = $block['outro_image_id'] ?? 0;
        $intro_url = $intro_id ? wp_get_attachment_image_url((int) $intro_id, 'thumbnail') : '';
        $outro_url = $outro_id ? wp_get_attachment_image_url((int) $outro_id, 'thumbnail') : '';
        $limit = $block['limit'] ?? '';

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Groep(en)', 'teksttv-wp-plugin'); ?></label>
                <?php if (!empty($available_groups)) : ?>
                <select name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][groups][]" class="teksttv-tomselect" data-placeholder="<?php echo esc_attr__('Kies groep(en)...', 'teksttv-wp-plugin'); ?>" multiple>
                    <?php foreach ($available_groups as $group_label) : ?>
                    <option value="<?php echo esc_attr($group_label); ?>" <?php echo in_array($group_label, $selected_groups, true) ? 'selected' : ''; ?>><?php echo esc_html($group_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else : ?>
                <p class="description"><?php echo wp_kses(sprintf(/* translators: %s: campaigns admin page URL */ __('Geen groepen geconfigureerd. <a href="%s">Groepen beheren</a>', 'teksttv-wp-plugin'), esc_url(admin_url('admin.php?page=teksttv-campaigns'))), ['a' => ['href' => []]]); ?></p>
                <?php endif; ?>
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Max. slides', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][limit]" value="<?php echo esc_attr((string) $limit); ?>" min="1" max="100" class="small-text" placeholder="<?php echo esc_attr__('Alle', 'teksttv-wp-plugin'); ?>" />
                <p class="description"><?php esc_html_e('Beperk het aantal slides dat tegelijk getoond wordt. Roteert automatisch door alle beschikbare slides. Laat leeg om alles te tonen.', 'teksttv-wp-plugin'); ?></p>
            </div>
        </div>
        <div class="teksttv-block-fields teksttv-block-fields--transitions">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Intro afbeelding', 'teksttv-wp-plugin'); ?></label>
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][intro_image_id]" value="<?php echo esc_attr((string) $intro_id); ?>" class="teksttv-block-image-id" />
                <div class="teksttv-block-image-preview <?php echo $intro_url ? '' : 'is-hidden'; ?>">
                    <img src="<?php echo esc_url($intro_url); ?>" alt="" class="teksttv-block-image-thumb" />
                </div>
                <button type="button" class="button button-small teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> <?php esc_html_e('Kiezen', 'teksttv-wp-plugin'); ?></button>
                <button type="button" class="button-link teksttv-block-image-remove <?php echo $intro_url ? '' : 'is-hidden'; ?>"><?php esc_html_e('Verwijderen', 'teksttv-wp-plugin'); ?></button>
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Outro afbeelding', 'teksttv-wp-plugin'); ?></label>
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][outro_image_id]" value="<?php echo esc_attr((string) $outro_id); ?>" class="teksttv-block-image-id" />
                <div class="teksttv-block-image-preview <?php echo $outro_url ? '' : 'is-hidden'; ?>">
                    <img src="<?php echo esc_url($outro_url); ?>" alt="" class="teksttv-block-image-thumb" />
                </div>
                <button type="button" class="button button-small teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> <?php esc_html_e('Kiezen', 'teksttv-wp-plugin'); ?></button>
                <button type="button" class="button-link teksttv-block-image-remove <?php echo $outro_url ? '' : 'is-hidden'; ?>"><?php esc_html_e('Verwijderen', 'teksttv-wp-plugin'); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function save(array $raw): array
    {
        $groups = [];
        if (!empty($raw['groups']) && is_array($raw['groups'])) {
            $groups = array_map('sanitize_text_field', $raw['groups']);
            $groups = array_filter($groups, fn ($g) => $g !== '');
        }

        $saved = [
            'groups' => array_values($groups),
            'intro_image_id' => absint($raw['intro_image_id'] ?? 0),
            'outro_image_id' => absint($raw['outro_image_id'] ?? 0),
        ];

        $limit = $raw['limit'] ?? '';
        if ($limit !== '') {
            $saved['limit'] = Helpers::clamp_int($limit, 1, 100);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

        $groups = (array) ($block['groups'] ?? []);
        if (empty($groups)) {
            return [];
        }

        $campaigns = Helpers::get_active_campaigns($channel);
        $slides = [];

        foreach ($campaigns as $campaign) {
            $campaign_group = (string) ($campaign['group'] ?? '');
            if (!in_array($campaign_group, $groups, true)) {
                continue;
            }

            $duration = !empty($campaign['duration']) ? (int) $campaign['duration'] * 1000 : (int) get_option('teksttv_duration_image', 7) * 1000;

            foreach ($campaign['slides'] ?? [] as $attachment_id) {
                $url = wp_get_attachment_url((int) $attachment_id);
                if ($url) {
                    $slides[] = [
                        'type' => 'commercial',
                        'duration' => $duration,
                        'url' => $url,
                    ];
                }
            }
        }

        $limit = !empty($block['limit']) ? (int) $block['limit'] : 0;
        if ($limit > 0 && count($slides) > $limit) {
            $offset = (int) floor(time() / 180) % count($slides);
            $rotated = [];
            for ($i = 0; $i < $limit; $i++) {
                $rotated[] = $slides[($offset + $i) % count($slides)];
            }
            $slides = $rotated;
        }

        if (!empty($slides)) {
            $intro_id = (int) ($block['intro_image_id'] ?? 0);
            if ($intro_id) {
                $intro_url = wp_get_attachment_url($intro_id);
                if ($intro_url) {
                    array_unshift($slides, [
                        'type' => 'commercial_transition',
                        'duration' => self::TRANSITION_DURATION,
                        'url' => $intro_url,
                    ]);
                }
            }

            $outro_id = (int) ($block['outro_image_id'] ?? 0);
            if ($outro_id) {
                $outro_url = wp_get_attachment_url($outro_id);
                if ($outro_url) {
                    $slides[] = [
                        'type' => 'commercial_transition',
                        'duration' => self::TRANSITION_DURATION,
                        'url' => $outro_url,
                    ];
                }
            }
        }

        return $slides;
    }
}
