<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Helpers;

final class CampaignLoopBlock
{
    private const TRANSITION_DURATION = 5000;

    public static function register(): void
    {
        BlockRegistry::register('campaign', [
            'label' => 'Campagne',
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
        $groups_id = Helpers::field_id($prefix, $index, 'groups');
        $limit_id = Helpers::field_id($prefix, $index, 'limit');

        ?>
        <div class="teksttv-field-grid teksttv-field-grid--campaign-main">
            <div class="teksttv-field teksttv-field--primary">
                <?php if (!empty($available_groups)) : ?>
                <label for="<?php echo esc_attr($groups_id); ?>"><?php echo esc_html('Groep(en)'); ?></label>
                <select id="<?php echo esc_attr($groups_id); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][groups][]" class="teksttv-tomselect" data-placeholder="<?php echo esc_attr('Kies groep(en)…'); ?>" data-summary data-summary-empty="<?php echo esc_attr('Geen groep'); ?>" multiple>
                    <?php foreach ($available_groups as $group_option) : ?>
                    <option value="<?php echo esc_attr($group_option['id']); ?>" <?php echo in_array($group_option['id'], $selected_groups, true) ? 'selected' : ''; ?>><?php echo esc_html($group_option['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else : ?>
                <span class="teksttv-field-label"><?php echo esc_html('Groep(en)'); ?></span>
                <p class="description"><?php echo wp_kses(sprintf('Geen groepen geconfigureerd. <a href="%s">Groepen beheren</a>', esc_url(admin_url('admin.php?page=teksttv-campaigns'))), ['a' => ['href' => []]]); ?></p>
                <?php endif; ?>
            </div>
            <div class="teksttv-field teksttv-field--primary">
                <label for="<?php echo esc_attr($limit_id); ?>"><?php echo esc_html('Maximaal aantal slides'); ?></label>
                <input type="number" id="<?php echo esc_attr($limit_id); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][limit]" value="<?php echo esc_attr((string) $limit); ?>" min="1" max="100" class="small-text" placeholder="<?php echo esc_attr('Alle'); ?>" data-summary="max. %s" />
                <p class="description"><?php echo esc_html('Beperk het aantal slides dat tegelijk getoond wordt. Roteert automatisch door alle beschikbare slides. Laat leeg om alles te tonen.'); ?></p>
            </div>
        </div>
        <div class="teksttv-field-grid teksttv-field-grid--transitions">
            <?php
            self::render_transition_picker('Introafbeelding', $prefix . '[' . $index . '][intro_image_id]', (int) $intro_id, $intro_url ?: '');
            self::render_transition_picker('Outroafbeelding', $prefix . '[' . $index . '][outro_image_id]', (int) $outro_id, $outro_url ?: '');
            ?>
        </div>
        <?php
    }

    /**
     * Render one intro/outro image picker field. The class names are a contract
     * with the image-select handler in the admin JS: `.teksttv-image-picker`
     * wraps the id input, preview, and select/remove buttons.
     */
    private static function render_transition_picker(string $label, string $field_name, int $image_id, string $image_url): void
    {
        ?>
        <div class="teksttv-field teksttv-field--primary teksttv-image-picker">
            <span class="teksttv-field-label"><?php echo esc_html($label); ?></span>
            <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($image_id ? (string) $image_id : ''); ?>" class="teksttv-block-image-id" data-summary data-summary-label="<?php echo esc_attr($label); ?>" />
            <div class="teksttv-block-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="" class="teksttv-block-image-thumb" width="80" height="50" loading="lazy" />
            </div>
            <button type="button" class="button button-small teksttv-block-image-select" aria-label="<?php echo esc_attr($label . ' kiezen'); ?>"><span class="dashicons dashicons-upload teksttv-button-icon" aria-hidden="true"></span> <?php echo esc_html('Kiezen'); ?></button>
            <button type="button" class="button-link teksttv-block-image-remove <?php echo $image_url ? '' : 'is-hidden'; ?>" aria-label="<?php echo esc_attr($label . ' verwijderen'); ?>"><?php echo esc_html('Verwijderen'); ?></button>
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
            // Groups are referenced by stable id, not by their mutable label.
            $groups = array_map('sanitize_key', $raw['groups']);
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

            $duration = Helpers::duration_ms($campaign['duration'] ?? null, 'teksttv_duration_image');

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
            $intro = self::transition_slide((int) ($block['intro_image_id'] ?? 0));
            if ($intro) {
                array_unshift($slides, $intro);
            }

            $outro = self::transition_slide((int) ($block['outro_image_id'] ?? 0));
            if ($outro) {
                $slides[] = $outro;
            }
        }

        return $slides;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function transition_slide(int $attachment_id): ?array
    {
        $url = $attachment_id ? wp_get_attachment_url($attachment_id) : false;
        if (!$url) {
            return null;
        }

        return [
            'type' => 'commercial_transition',
            'duration' => self::TRANSITION_DURATION,
            'url' => $url,
        ];
    }
}
