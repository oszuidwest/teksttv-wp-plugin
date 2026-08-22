<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\AdminPage;
use TekstTV\BlockRegistry;
use TekstTV\Helpers;

final class CommercialLoopBlock
{
    private const TRANSITION_DURATION = 5000;

    public static function register(): void
    {
        BlockRegistry::register('commercial', [
            'label' => 'Reclame',
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
        $selected_commercial_blocks = (array) ($block['commercial_block_ids'] ?? []);
        $available_commercial_blocks = Helpers::get_commercial_blocks();
        $intro_id = $block['intro_image_id'] ?? 0;
        $outro_id = $block['outro_image_id'] ?? 0;
        $intro_url = $intro_id ? wp_get_attachment_image_url((int) $intro_id, 'thumbnail') : '';
        $outro_url = $outro_id ? wp_get_attachment_image_url((int) $outro_id, 'thumbnail') : '';
        $limit = $block['limit'] ?? '';
        $commercial_blocks_id = Helpers::field_id($prefix, $index, 'commercial_block_ids');

        ?>
        <?php AdminPage::render_block_section_start('Inhoud', 'Welke reclameblokken komen in de loop?', 'content'); ?>
        <div class="teksttv-field-grid teksttv-field-grid--commercial-main">
            <div class="teksttv-field teksttv-field--primary">
                <?php if (!empty($available_commercial_blocks)) : ?>
                <label for="<?php echo esc_attr($commercial_blocks_id); ?>"><?php echo esc_html('Reclameblokken'); ?></label>
                <select id="<?php echo esc_attr($commercial_blocks_id); ?>" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][commercial_block_ids][]" class="teksttv-tomselect" data-placeholder="<?php echo esc_attr('Kies reclameblokken…'); ?>" data-summary data-summary-empty="<?php echo esc_attr('Geen reclameblok'); ?>" multiple>
                    <?php foreach ($available_commercial_blocks as $commercial_block) : ?>
                    <option value="<?php echo esc_attr($commercial_block['id']); ?>" <?php echo in_array($commercial_block['id'], $selected_commercial_blocks, true) ? 'selected' : ''; ?>><?php echo esc_html($commercial_block['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else : ?>
                <span class="teksttv-field-label"><?php echo esc_html('Reclameblokken'); ?></span>
                <p class="description"><?php echo wp_kses(sprintf('Geen reclameblokken geconfigureerd. <a href="%s">Reclameblokken beheren</a>', esc_url(admin_url('admin.php?page=teksttv-commercials'))), ['a' => ['href' => []]]); ?></p>
                <?php endif; ?>
            </div>
            <div class="teksttv-field teksttv-field--primary">
                <label <?php Helpers::field_for($prefix, $index, 'limit'); ?>><?php echo esc_html('Maximaal aantal slides'); ?></label>
                <input type="number" <?php Helpers::field_attrs($prefix, $index, 'limit'); ?> value="<?php echo esc_attr((string) $limit); ?>" min="1" max="100" class="small-text" placeholder="<?php echo esc_attr('Alle'); ?>" data-summary="max. %s" />
                <p class="description"><?php echo esc_html('Beperk het aantal slides dat tegelijk getoond wordt. Roteert automatisch door alle beschikbare slides. Laat leeg om alles te tonen.'); ?></p>
            </div>
        </div>
        <?php AdminPage::render_block_section_end(); ?>
        <?php AdminPage::render_block_section_start('Overgangen', 'Optionele beelden voor en na de campagneslides.', 'transitions'); ?>
        <div class="teksttv-field-grid teksttv-field-grid--transitions">
            <?php
            self::render_transition_picker('Introafbeelding', $prefix . '[' . $index . '][intro_image_id]', (int) $intro_id, $intro_url ?: '');
            self::render_transition_picker('Outroafbeelding', $prefix . '[' . $index . '][outro_image_id]', (int) $outro_id, $outro_url ?: '');
            ?>
        </div>
        <?php AdminPage::render_block_section_end(); ?>
        <?php
    }

    /**
     * Render an image picker bound to the admin-JS class contract.
     */
    private static function render_transition_picker(string $label, string $field_name, int $image_id, string $image_url): void
    {
        ?>
        <div class="teksttv-field teksttv-field--primary teksttv-image-picker">
            <span class="teksttv-field-label"><?php echo esc_html($label); ?></span>
            <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($image_id ? (string) $image_id : ''); ?>" class="teksttv-block-image-id" />
            <div class="teksttv-block-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="" class="teksttv-block-image-thumb" width="80" height="50" loading="lazy" />
            </div>
            <button type="button" class="button button-small teksttv-block-image-select" aria-label="<?php echo esc_attr($label . ' kiezen'); ?>"><?php echo esc_html('Kiezen'); ?></button>
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
        $commercial_block_ids = [];
        if (!empty($raw['commercial_block_ids']) && is_array($raw['commercial_block_ids'])) {
            // Commercial blocks use stable IDs, not mutable labels.
            $commercial_block_ids = array_map('sanitize_key', $raw['commercial_block_ids']);
            $commercial_block_ids = array_filter($commercial_block_ids, fn ($id) => $id !== '');
        }

        $saved = [
            'commercial_block_ids' => array_values($commercial_block_ids),
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
        $commercial_block_ids = (array) ($block['commercial_block_ids'] ?? []);
        if (empty($commercial_block_ids)) {
            return [];
        }

        $campaigns = Helpers::get_active_campaigns($channel);
        $slides = [];

        foreach ($campaigns as $campaign) {
            $campaign_commercial_block_id = (string) ($campaign['commercial_block_id'] ?? '');
            if (!in_array($campaign_commercial_block_id, $commercial_block_ids, true)) {
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
