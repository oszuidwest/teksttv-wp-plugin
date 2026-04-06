<?php

namespace TekstTV;

/**
 * Registers all built-in loop and ticker block types.
 */
class BuiltinBlocks
{
    public static function init(): void
    {
        add_action('init', [self::class, 'register'], 5);
    }

    public static function register(): void
    {
        self::register_articles();
        self::register_image();
        self::register_commercial();
        self::register_weather();
        self::register_ticker_text();
        self::register_ticker_headlines();
    }

    /**
     * Sanitize taxonomy filters from raw POST data.
     *
     * @param array<string, mixed> $raw Raw block data containing 'taxonomy_filters' key.
     * @return array<string, list<int>> Sanitized taxonomy filters keyed by taxonomy name.
     */
    public static function sanitize_taxonomy_filters(array $raw): array
    {
        $tax_filters = [];
        if (!empty($raw['taxonomy_filters']) && is_array($raw['taxonomy_filters'])) {
            foreach ($raw['taxonomy_filters'] as $tax_name => $term_ids) {
                $tax_name = sanitize_key($tax_name);
                if (!is_array($term_ids)) {
                    $term_ids = [$term_ids];
                }
                $term_ids = array_filter(array_map('absint', $term_ids));
                if (!empty($term_ids)) {
                    $tax_filters[$tax_name] = $term_ids;
                }
            }
        }

        return $tax_filters;
    }

    // =========================================================================
    // Articles block (loop)
    // =========================================================================

    private static function register_articles(): void
    {
        BlockRegistry::register('articles', [
            'label' => 'Artikelen',
            'icon' => 'admin-post',
            'color' => '#2271b1',
            'context' => 'loop',
            'render' => [self::class, 'render_articles'],
            'save' => [self::class, 'save_articles'],
            'build' => [SlidesBuilder::class, 'build_article_slides'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_articles(int|string $index, array $block, string $prefix): void
    {
        $count = $block['count'] ?? 3;
        $filters = $block['taxonomy_filters'] ?? [];
        $dur_text = $block['duration_text'] ?? '';
        $dur_image = $block['duration_image'] ?? '';
        $default_text = (int) get_option('teksttv_duration_text', 20);
        $default_image = (int) get_option('teksttv_duration_image', 7);

        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = AdminPage::get_post_taxonomies_static();
        $taxonomies = array_filter($all_taxonomies, fn($t) => in_array($t['name'], $enabled_tax, true));

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label>Aantal</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][count]" value="<?php echo esc_attr((string) $count); ?>" min="1" max="50" class="small-text" />
            </div>
            <?php foreach ($taxonomies as $tax) :
                $selected_terms = array_map('intval', (array) ($filters[$tax['name']] ?? []));
                ?>
            <div class="teksttv-block-field">
                <label><?php echo esc_html($tax['label']); ?></label>
                <select name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][taxonomy_filters][<?php echo esc_attr($tax['name']); ?>][]" class="teksttv-tomselect" data-placeholder="Filter..." multiple>
                    <?php foreach ($tax['terms'] as $term_id => $term_name) : ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php echo in_array($term_id, $selected_terms, true) ? 'selected' : ''; ?>><?php echo esc_html($term_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="teksttv-block-fields teksttv-block-fields--duration">
            <div class="teksttv-block-field">
                <label>Duur tekst</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][duration_text]" value="<?php echo esc_attr($dur_text); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_text); ?>" /> <span class="teksttv-unit">sec</span>
            </div>
            <div class="teksttv-block-field">
                <label>Duur afbeelding</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][duration_image]" value="<?php echo esc_attr($dur_image); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_image); ?>" /> <span class="teksttv-unit">sec</span>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_articles(array $raw): ?array
    {
        $saved = [
            'count' => absint($raw['count'] ?? 3),
            'taxonomy_filters' => self::sanitize_taxonomy_filters($raw),
        ];

        $dt = $raw['duration_text'] ?? '';
        $di = $raw['duration_image'] ?? '';
        if ($dt !== '') {
            $saved['duration_text'] = absint($dt);
        }
        if ($di !== '') {
            $saved['duration_image'] = absint($di);
        }

        return $saved;
    }

    // =========================================================================
    // Image block (loop)
    // =========================================================================

    private static function register_image(): void
    {
        BlockRegistry::register('image', [
            'label' => 'Afbeelding',
            'icon' => 'format-image',
            'color' => '#8c8f94',
            'context' => 'loop',
            'render' => [self::class, 'render_image'],
            'save' => [self::class, 'save_image'],
            'build' => [SlidesBuilder::class, 'build_image_slide'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_image(int|string $index, array $block, string $prefix): void
    {
        $image_id = $block['image_id'] ?? 0;
        $duration = $block['duration'] ?? '';
        $default_image = (int) get_option('teksttv_duration_image', 7);
        $image_url = $image_id ? wp_get_attachment_image_url((int) $image_id, 'medium') : '';

        ?>
        <div class="teksttv-block-image-row">
            <div class="teksttv-block-image-preview <?php echo $image_url ? '' : 'is-hidden'; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="" class="teksttv-block-image-thumb" />
            </div>
            <div class="teksttv-block-image-fields">
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][image_id]" value="<?php echo esc_attr($image_id); ?>" class="teksttv-block-image-id" />
                <p>
                    <button type="button" class="button teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> Afbeelding kiezen</button>
                    <button type="button" class="button-link teksttv-block-image-remove <?php echo $image_url ? '' : 'is-hidden'; ?>">Verwijderen</button>
                </p>
                <div class="teksttv-block-fields">
                    <div class="teksttv-block-field">
                        <label>Duur</label>
                        <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][duration]" value="<?php echo esc_attr($duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) $default_image); ?>" /> <span class="teksttv-unit">sec</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_image(array $raw): ?array
    {
        $saved = [
            'image_id' => absint($raw['image_id'] ?? 0),
        ];

        $dur = $raw['duration'] ?? '';
        if ($dur !== '') {
            $saved['duration'] = absint($dur);
        }

        return $saved;
    }

    // =========================================================================
    // Commercial block (loop)
    // =========================================================================

    private static function register_commercial(): void
    {
        BlockRegistry::register('commercial', [
            'label' => 'Reclame',
            'icon' => 'megaphone',
            'color' => '#d63638',
            'context' => 'loop',
            'render' => [self::class, 'render_commercial'],
            'save' => [self::class, 'save_commercial'],
            'build' => [SlidesBuilder::class, 'build_commercial_slides'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_commercial(int|string $index, array $block, string $prefix): void
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
                <label>Groep(en)</label>
                <?php if (!empty($available_groups)) : ?>
                <select name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][groups][]" class="teksttv-tomselect" data-placeholder="Kies groep(en)..." multiple>
                    <?php foreach ($available_groups as $group_label) : ?>
                    <option value="<?php echo esc_attr($group_label); ?>" <?php echo in_array($group_label, $selected_groups, true) ? 'selected' : ''; ?>><?php echo esc_html($group_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else : ?>
                <p class="description">Geen groepen geconfigureerd. <a href="<?php echo esc_url(admin_url('admin.php?page=teksttv-campaigns')); ?>">Groepen beheren</a></p>
                <?php endif; ?>
            </div>
            <div class="teksttv-block-field">
                <label>Max. slides</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][limit]" value="<?php echo esc_attr($limit); ?>" min="1" max="100" class="small-text" placeholder="Alle" />
                <p class="description">Beperk het aantal slides dat tegelijk getoond wordt. Roteert automatisch door alle beschikbare slides. Laat leeg om alles te tonen.</p>
            </div>
        </div>
        <div class="teksttv-block-fields teksttv-block-fields--transitions">
            <div class="teksttv-block-field">
                <label>Intro afbeelding</label>
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][intro_image_id]" value="<?php echo esc_attr($intro_id); ?>" class="teksttv-block-image-id" />
                <div class="teksttv-block-image-preview <?php echo $intro_url ? '' : 'is-hidden'; ?>">
                    <img src="<?php echo esc_url($intro_url); ?>" alt="" class="teksttv-block-image-thumb" />
                </div>
                <button type="button" class="button button-small teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> Kiezen</button>
                <button type="button" class="button-link teksttv-block-image-remove <?php echo $intro_url ? '' : 'is-hidden'; ?>">Verwijderen</button>
            </div>
            <div class="teksttv-block-field">
                <label>Outro afbeelding</label>
                <input type="hidden" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][outro_image_id]" value="<?php echo esc_attr($outro_id); ?>" class="teksttv-block-image-id" />
                <div class="teksttv-block-image-preview <?php echo $outro_url ? '' : 'is-hidden'; ?>">
                    <img src="<?php echo esc_url($outro_url); ?>" alt="" class="teksttv-block-image-thumb" />
                </div>
                <button type="button" class="button button-small teksttv-block-image-select"><span class="dashicons dashicons-upload teksttv-button-icon"></span> Kiezen</button>
                <button type="button" class="button-link teksttv-block-image-remove <?php echo $outro_url ? '' : 'is-hidden'; ?>">Verwijderen</button>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_commercial(array $raw): ?array
    {
        $groups = [];
        if (!empty($raw['groups']) && is_array($raw['groups'])) {
            $groups = array_map('sanitize_text_field', $raw['groups']);
            $groups = array_filter($groups, fn($g) => $g !== '');
        }

        $saved = [
            'groups' => array_values($groups),
            'intro_image_id' => absint($raw['intro_image_id'] ?? 0),
            'outro_image_id' => absint($raw['outro_image_id'] ?? 0),
        ];

        $limit = $raw['limit'] ?? '';
        if ($limit !== '') {
            $saved['limit'] = absint($limit);
        }

        return $saved;
    }

    // =========================================================================
    // Weather block (loop)
    // =========================================================================

    private static function register_weather(): void
    {
        BlockRegistry::register('weather', [
            'label' => 'Weer',
            'icon' => 'cloud',
            'color' => '#72aee6',
            'context' => 'loop',
            'render' => [self::class, 'render_weather'],
            'save' => [self::class, 'save_weather'],
            'build' => [SlidesBuilder::class, 'build_weather_slide'],
        ]);
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_weather(int|string $index, array $block, string $prefix): void
    {
        $location = $block['location'] ?? '';
        $title = $block['title'] ?? '';
        $duration = $block['duration'] ?? '';

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label>Locatie</label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][location]" value="<?php echo esc_attr($location); ?>" class="regular-text" placeholder="Breda,NL" />
            </div>
            <div class="teksttv-block-field">
                <label>Titel</label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($title); ?>" class="regular-text" placeholder="Het weer" />
            </div>
            <div class="teksttv-block-field">
                <label>Duur</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][duration]" value="<?php echo esc_attr($duration); ?>" min="1" max="120" class="small-text" placeholder="15" /> <span class="teksttv-unit">sec</span>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_weather(array $raw): ?array
    {
        $saved = [
            'location' => sanitize_text_field($raw['location'] ?? ''),
            'title' => sanitize_text_field($raw['title'] ?? ''),
        ];

        $dur = $raw['duration'] ?? '';
        if ($dur !== '') {
            $saved['duration'] = absint($dur);
        }

        return $saved;
    }

    // =========================================================================
    // Ticker: text message
    // =========================================================================

    private static function register_ticker_text(): void
    {
        BlockRegistry::register('ticker_text', [
            'label' => 'Tekst',
            'icon' => 'editor-textcolor',
            'color' => '#e65100',
            'context' => 'ticker',
            'render' => [self::class, 'render_ticker_text'],
            'save' => [self::class, 'save_ticker_text'],
            'build' => [self::class, 'build_ticker_text'],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function render_ticker_text(int|string $index, array $item, string $prefix): void
    {
        $message = $item['message'] ?? '';

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field" style="flex:1;">
                <label>Bericht</label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][message]" value="<?php echo esc_attr($message); ?>" class="large-text" placeholder="Ticker tekst..." />
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_ticker_text(array $raw): ?array
    {
        $message = sanitize_text_field($raw['message'] ?? '');
        if (empty($message)) {
            return null;
        }

        return ['message' => $message];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{message: string}>
     */
    public static function build_ticker_text(array $item, string $channel): array
    {
        $text = $item['message'] ?? '';
        if (empty($text)) {
            return [];
        }

        return [['message' => $text]];
    }

    // =========================================================================
    // Ticker: headlines (latest post titles)
    // =========================================================================

    private static function register_ticker_headlines(): void
    {
        BlockRegistry::register('ticker_headlines', [
            'label' => 'Koppen',
            'icon' => 'list-view',
            'color' => '#2271b1',
            'context' => 'ticker',
            'render' => [self::class, 'render_ticker_headlines'],
            'save' => [self::class, 'save_ticker_headlines'],
            'build' => [self::class, 'build_ticker_headlines'],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function render_ticker_headlines(int|string $index, array $item, string $prefix): void
    {
        $count = $item['count'] ?? 5;
        $item_prefix = $item['prefix'] ?? '';
        $filters = $item['taxonomy_filters'] ?? [];

        $enabled_tax = get_option('teksttv_enabled_taxonomies', ['category']);
        $all_taxonomies = AdminPage::get_post_taxonomies_static();
        $taxonomies = array_filter($all_taxonomies, fn($t) => in_array($t['name'], $enabled_tax, true));

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label>Aantal</label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][count]" value="<?php echo esc_attr((string) $count); ?>" min="1" max="20" class="small-text" />
            </div>
            <div class="teksttv-block-field">
                <label>Prefix</label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][prefix]" value="<?php echo esc_attr($item_prefix); ?>" class="regular-text" placeholder="bijv. Nieuws:" />
            </div>
            <?php foreach ($taxonomies as $tax) :
                $selected_terms = array_map('intval', (array) ($filters[$tax['name']] ?? []));
                ?>
            <div class="teksttv-block-field">
                <label><?php echo esc_html($tax['label']); ?></label>
                <select name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr($index); ?>][taxonomy_filters][<?php echo esc_attr($tax['name']); ?>][]" class="teksttv-tomselect" data-placeholder="Filter..." multiple>
                    <?php foreach ($tax['terms'] as $term_id => $term_name) : ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php echo in_array($term_id, $selected_terms, true) ? 'selected' : ''; ?>><?php echo esc_html($term_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function save_ticker_headlines(array $raw): ?array
    {
        $saved = [
            'count' => max(1, min(20, absint($raw['count'] ?? 5))),
        ];

        $item_prefix = sanitize_text_field($raw['prefix'] ?? '');
        if ($item_prefix !== '') {
            $saved['prefix'] = $item_prefix;
        }

        $tax_filters = self::sanitize_taxonomy_filters($raw);
        if (!empty($tax_filters)) {
            $saved['taxonomy_filters'] = $tax_filters;
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{message: string}>
     */
    public static function build_ticker_headlines(array $item, string $channel): array
    {
        $count = $item['count'] ?? 5;
        $item_prefix = $item['prefix'] ?? '';
        $taxonomy_filters = $item['taxonomy_filters'] ?? [];

        $args = [
            'post_type' => 'post',
            'posts_per_page' => $count,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_teksttv_active', 'value' => '1', 'compare' => '='],
                Helpers::get_date_end_meta_query(),
            ],
        ];

        // Apply max age
        $max_age = (int) get_option('teksttv_max_post_age', 30);
        if ($max_age > 0) {
            $args['date_query'] = [
                ['after' => $max_age . ' days ago'],
            ];
        }

        // Apply taxonomy filters
        $tax_query = Helpers::build_tax_query($taxonomy_filters);
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $messages = [];

        foreach ($query->posts as $post_id) {
            // Check day restrictions
            $days = get_post_meta($post_id, '_teksttv_days', true);
            if (!empty($days) && is_array($days)) {
                if (!Helpers::is_allowed_on_day($days)) {
                    continue;
                }
            }

            // Check date range
            $date_start = get_post_meta($post_id, '_teksttv_date_start', true);
            $date_end = get_post_meta($post_id, '_teksttv_date_end', true);
            if (!Helpers::is_within_date_range($date_start, $date_end)) {
                continue;
            }

            $title = get_the_title($post_id);
            if (!empty($title)) {
                $message = !empty($item_prefix) ? $item_prefix . ' ' . $title : $title;
                $messages[] = ['message' => $message];
            }
        }

        return $messages;
    }
}
