<?php

namespace TekstTV;

class AuditPage
{
    private const PER_PAGE = 50;

    private const CHANGE_BANDS = [
        'unchanged' => [0, 0],
        'minor' => [1, 25],
        'substantial' => [26, 50],
        'extensive' => [51, 100],
    ];

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void
    {
        if (!Helpers::has_feature('ai_generate')) {
            return;
        }

        add_submenu_page(
            'teksttv',
            'AI Audit',
            'AI Audit',
            'manage_teksttv',
            'teksttv-audit',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page, no action taken
        $detail_post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if ($detail_post_id > 0) {
            self::render_detail_page($detail_post_id);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no action taken
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters, no action taken
        $filters = self::sanitize_filters(wp_unslash($_GET));

        $query_result = self::query_audit_posts($paged, $filters);
        $posts = $query_result['posts'];
        $total_posts = $query_result['total'];
        $total_pages = (int) ceil($total_posts / self::PER_PAGE);
        $stats = self::compute_stats(self::query_audit_post_statuses($filters));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html('AI Audit') . '</h1>';

        ?>
        <div class="teksttv-tab-content">
            <form method="get" class="teksttv-audit-filters">
                <input type="hidden" name="page" value="teksttv-audit" />
                <label><?php echo esc_html('Maand'); ?> <input type="month" name="month" value="<?php echo esc_attr($filters['month']); ?>" /></label>
                <label><?php echo esc_html('Herkomst'); ?>
                    <select name="generation_status">
                        <option value=""><?php echo esc_html('Alle'); ?></option>
                        <option value="human" <?php selected($filters['generation_status'], 'human'); ?>><?php echo esc_html('Menselijk geschreven'); ?></option>
                        <option value="ai_unmodified" <?php selected($filters['generation_status'], 'ai_unmodified'); ?>><?php echo esc_html('AI ongewijzigd'); ?></option>
                        <option value="ai_edited" <?php selected($filters['generation_status'], 'ai_edited'); ?>><?php echo esc_html('AI bewerkt'); ?></option>
                    </select>
                </label>
                <label><?php echo esc_html('Wijziging'); ?>
                    <select name="change_band">
                        <option value=""><?php echo esc_html('Alle'); ?></option>
                        <option value="unchanged" <?php selected($filters['change_band'], 'unchanged'); ?>><?php echo esc_html('0%'); ?></option>
                        <option value="minor" <?php selected($filters['change_band'], 'minor'); ?>><?php echo esc_html('1–25%'); ?></option>
                        <option value="substantial" <?php selected($filters['change_band'], 'substantial'); ?>><?php echo esc_html('26–50%'); ?></option>
                        <option value="extensive" <?php selected($filters['change_band'], 'extensive'); ?>><?php echo esc_html('51–100%'); ?></option>
                    </select>
                </label>
                <?php submit_button('Filteren', 'secondary', 'filter_action', false); ?>
            </form>
            <p class="description"><?php echo esc_html('Wijzigingspercentage: woordbewerkingsafstand gedeeld door de langste versie. HTML, witruimte en een voorloop-prefix vóór “ - ” tellen niet mee.'); ?></p>
            <div class="teksttv-audit-stats">
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $total_posts); ?></span>
                    <span class="teksttv-audit-stat-label"><?php echo esc_html('Posts in audit'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['title_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php echo esc_html('Koppen bewerkt'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['body_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php echo esc_html('Teksten bewerkt'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['any_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php echo esc_html('Totaal bewerkt'); ?></span>
                </div>
            </div>

            <?php if (empty($posts)) : ?>
                <div class="teksttv-card">
                    <p><?php echo esc_html('Geen posts gevonden voor deze auditselectie.'); ?></p>
                </div>
            <?php else : ?>
                <table class="widefat teksttv-audit-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html('Post'); ?></th>
                            <th><?php echo esc_html('Kop'); ?></th>
                            <th><?php echo esc_html('Tekst'); ?></th>
                            <th><?php echo esc_html('Auteur'); ?></th>
                            <th><?php echo esc_html('Laatst bewerkt door'); ?></th>
                            <th><?php echo esc_html('Datum'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post_data) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($post_data['title']); ?></strong></td>
                            <td><?php echo self::render_status_with_change($post_data['title_status'], $post_data['title_change']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></td>
                            <td><?php echo self::render_status_with_change($post_data['body_status'], $post_data['body_change']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></td>
                            <td><?php echo esc_html($post_data['author']); ?></td>
                            <td><?php echo esc_html($post_data['last_editor']); ?></td>
                            <td><?php echo esc_html($post_data['date']); ?></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=teksttv-audit&post_id=' . $post_data['id'])); ?>" class="button button-small"><?php echo esc_html('Bekijk'); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf('%d items', $total_posts)); ?></span>
                        <?php
                        echo wp_kses_post((string) paginate_links([
                            'base' => add_query_arg(array_filter([
                                'paged' => '%#%',
                                'month' => $filters['month'],
                                'generation_status' => $filters['generation_status'],
                                'change_band' => $filters['change_band'],
                            ])),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php

        echo '</div>';
    }

    private static function render_detail_page(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post) {
            echo '<div class="wrap"><h1>' . esc_html('Post niet gevonden') . '</h1></div>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only toggle, no action taken
        $split = !isset($_GET['view']) || $_GET['view'] !== 'inline';
        $toggle_view = $split ? 'inline' : 'split';
        $toggle_label = $split ? 'Inline weergave' : 'Side-by-side weergave';
        $toggle_url = admin_url('admin.php?page=teksttv-audit&post_id=' . $post_id . '&view=' . $toggle_view);

        $ai_title = get_post_meta($post_id, '_teksttv_ai_title', true);
        $ai_body = get_post_meta($post_id, '_teksttv_ai_body', true);
        $current_title = get_post_meta($post_id, '_teksttv_title', true);
        $current_body = get_post_meta($post_id, '_teksttv_content', true);

        echo '<div class="wrap">';
        echo '<h1>AI Audit: ' . esc_html($post->post_title) . '</h1>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=teksttv-audit')) . '">&larr; ' . esc_html('Terug naar overzicht') . '</a>';
        echo ' | <a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html('Post bewerken') . '</a>';
        echo ' | <a href="' . esc_url($toggle_url) . '">' . esc_html($toggle_label) . '</a>';
        echo '</p>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-card">
                <h3><?php echo esc_html('Kop'); ?> <?php echo self::render_status_badge(self::compare($ai_title, $current_title)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></h3>
                <?php
                $title_diff = self::render_diff($ai_title ?: '', $current_title ?: '', $split);
                if ($title_diff) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output from wp_text_diff
                    echo $title_diff;
                } else {
                    echo '<p class="description">' . esc_html('Geen wijzigingen.') . '</p>';
                }
                ?>
            </div>

            <div class="teksttv-card">
                <h3><?php echo esc_html('Tekst'); ?> <?php echo self::render_status_badge(self::compare($ai_body, $current_body, true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></h3>
                <?php
                $body_diff = self::render_diff(
                    wp_strip_all_tags($ai_body ?: ''),
                    wp_strip_all_tags($current_body ?: ''),
                    $split
                );
                if ($body_diff) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output from wp_text_diff
                    echo $body_diff;
                } else {
                    echo '<p class="description">' . esc_html('Geen wijzigingen.') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php

        echo '</div>';
    }

    private static function render_diff(string $left, string $right, bool $split_view = true): string
    {
        if (empty($left) && empty($right)) {
            return '';
        }

        return wp_text_diff($left, $right, [
            'title_left' => 'AI-versie',
            'title_right' => 'Huidige versie',
            'show_split_view' => $split_view,
        ]);
    }

    /**
     * Keep the object query paginated. Status/change filters need a lightweight
     * ID pass because their values are derived from two metadata fields.
     *
     * @param array{month: string, generation_status: string, change_band: string} $filters
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    private static function query_audit_posts(int $paged, array $filters): array
    {
        $query_args = self::audit_post_query_args($filters);
        if ($filters['generation_status'] === '' && $filters['change_band'] === '') {
            $query = new \WP_Query(array_merge($query_args, [
                'posts_per_page' => self::PER_PAGE,
                'paged' => $paged,
                'orderby' => 'modified',
                'order' => 'DESC',
            ]));

            return ['posts' => self::build_post_rows($query->posts), 'total' => $query->found_posts];
        }

        $id_query = new \WP_Query(array_merge($query_args, [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]));
        $candidate_ids = array_map('intval', $id_query->posts);
        update_meta_cache('post', $candidate_ids);
        $matching_ids = array_values(array_filter(
            $candidate_ids,
            fn (int $post_id): bool => self::matches_filters(self::get_post_audit_data($post_id), $filters)
        ));
        $page_ids = array_slice($matching_ids, ($paged - 1) * self::PER_PAGE, self::PER_PAGE);
        if ($page_ids === []) {
            return ['posts' => [], 'total' => count($matching_ids)];
        }

        $query = new \WP_Query([
            'post_type' => 'post',
            'post_status' => 'any',
            'post__in' => $page_ids,
            'posts_per_page' => self::PER_PAGE,
            'orderby' => 'post__in',
            'no_found_rows' => true,
        ]);

        return ['posts' => self::build_post_rows($query->posts), 'total' => count($matching_ids)];
    }

    /**
     * @param list<\WP_Post> $posts
     * @return list<array<string, mixed>>
     */
    private static function build_post_rows(array $posts): array
    {
        $post_ids = array_map(fn (\WP_Post $post): int => $post->ID, $posts);
        update_meta_cache('post', $post_ids);

        $user_ids = [];
        foreach ($posts as $post) {
            $user_ids[] = (int) $post->post_author;
            $user_ids[] = (int) get_post_meta($post->ID, '_edit_last', true);
        }
        $user_ids = array_values(array_filter(array_unique($user_ids)));
        if ($user_ids !== []) {
            cache_users($user_ids);
        }

        $results = [];
        foreach ($posts as $post) {
            $audit = self::get_post_audit_data($post->ID);
            $author = get_userdata((int) $post->post_author);
            $last_editor = get_userdata((int) get_post_meta($post->ID, '_edit_last', true));
            $results[] = array_merge($audit, [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author' => $author ? $author->display_name : '—',
                'last_editor' => $last_editor ? $last_editor->display_name : '—',
                'date' => get_the_modified_date('j M Y H:i', $post),
            ]);
        }

        return $results;
    }

    /**
     * Fetch audit statuses for every matching post without loading post
     * objects or writing the ID result to the query cache.
     *
     * @param array{month: string, generation_status: string, change_band: string} $filters
     * @return list<array{title_status: string, body_status: string}>
     */
    private static function query_audit_post_statuses(array $filters): array
    {
        $query = new \WP_Query(array_merge(self::audit_post_query_args($filters), [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'cache_results' => false,
            'orderby' => 'none',
        ]));
        $post_ids = array_map('intval', $query->posts);
        update_meta_cache('post', $post_ids);

        $results = [];
        foreach ($post_ids as $post_id) {
            $audit = self::get_post_audit_data($post_id);
            if (self::matches_filters($audit, $filters)) {
                $results[] = [
                    'title_status' => $audit['title_status'],
                    'body_status' => $audit['body_status'],
                ];
            }
        }

        return $results;
    }

    /**
     * Which posts count as AI-audited; shared by the table and the statistics.
     *
     * @param array{month: string, generation_status: string, change_band: string} $filters
     * @return array<string, mixed>
     */
    private static function audit_post_query_args(array $filters): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_teksttv_ai_title', 'compare' => 'EXISTS'],
                ['key' => '_teksttv_ai_body', 'compare' => 'EXISTS'],
                ['key' => '_teksttv_active', 'value' => '1'],
            ],
        ];
        if ($filters['month'] !== '') {
            [$year, $month] = array_map('intval', explode('-', $filters['month']));
            $args['date_query'] = [
                [
                    'year' => $year,
                    'monthnum' => $month,
                    'column' => 'post_modified',
                ],
            ];
        }

        return $args;
    }

    /** @return array{title_status: string, body_status: string, title_change: float|null, body_change: float|null, generation_status: string, max_change: float|null} */
    private static function get_post_audit_data(int $post_id): array
    {
        $ai_title = (string) get_post_meta($post_id, '_teksttv_ai_title', true);
        $current_title = (string) get_post_meta($post_id, '_teksttv_title', true);
        $ai_body = (string) get_post_meta($post_id, '_teksttv_ai_body', true);
        $current_body = (string) get_post_meta($post_id, '_teksttv_content', true);
        $title_status = self::compare($ai_title, $current_title);
        $body_status = self::compare($ai_body, $current_body, true);
        $title_change = $ai_title === '' ? null : self::change_percentage($ai_title, $current_title);
        $body_change = $ai_body === '' ? null : self::change_percentage($ai_body, $current_body, true);
        $changes = array_filter([$title_change, $body_change], fn ($value): bool => $value !== null);
        $has_ai = $changes !== [];

        return [
            'title_status' => $title_status,
            'body_status' => $body_status,
            'title_change' => $title_change,
            'body_change' => $body_change,
            'generation_status' => self::classify_generation_status($title_status, $body_status),
            'max_change' => $has_ai ? max($changes) : null,
        ];
    }

    /**
     * @param array<string, mixed> $audit
     * @param array{month: string, generation_status: string, change_band: string} $filters
     */
    private static function matches_filters(array $audit, array $filters): bool
    {
        if ($filters['generation_status'] !== '' && $audit['generation_status'] !== $filters['generation_status']) {
            return false;
        }
        if ($filters['change_band'] === '') {
            return true;
        }
        if ($audit['max_change'] === null) {
            return false;
        }
        [$minimum, $maximum] = self::CHANGE_BANDS[$filters['change_band']];

        return $audit['max_change'] >= $minimum && $audit['max_change'] <= $maximum;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{month: string, generation_status: string, change_band: string}
     */
    public static function sanitize_filters(array $input): array
    {
        $month = isset($input['month']) && is_string($input['month']) ? $input['month'] : '';
        $generation_status = isset($input['generation_status']) && is_string($input['generation_status']) ? sanitize_key($input['generation_status']) : '';
        $change_band = isset($input['change_band']) && is_string($input['change_band']) ? sanitize_key($input['change_band']) : '';
        if (!in_array($generation_status, ['human', 'ai_unmodified', 'ai_edited'], true)) {
            $generation_status = '';
        }

        return [
            'month' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : '',
            'generation_status' => $generation_status,
            'change_band' => isset(self::CHANGE_BANDS[$change_band]) ? $change_band : '',
        ];
    }

    public static function classify_generation_status(string $title_status, string $body_status): string
    {
        if ($title_status === 'no_ai' && $body_status === 'no_ai') {
            return 'human';
        }

        return $title_status === 'modified' || $body_status === 'modified' ? 'ai_edited' : 'ai_unmodified';
    }

    /**
     * Compute stats from an already-fetched post statuses array.
     *
     * @param list<array{title_status: string, body_status: string}> $posts
     * @return array{title_modified_pct: int|float, body_modified_pct: int|float, any_modified_pct: int|float}
     */
    public static function compute_stats(array $posts): array
    {
        $total = count($posts);
        $title_modified = 0;
        $body_modified = 0;
        $any_modified = 0;

        foreach ($posts as $post_data) {
            $t = $post_data['title_status'] === 'modified';
            $b = $post_data['body_status'] === 'modified';
            if ($t) {
                $title_modified++;
            }
            if ($b) {
                $body_modified++;
            }
            if ($t || $b) {
                $any_modified++;
            }
        }

        $pct = fn($n) => $total > 0 ? round(($n / $total) * 100) : 0;

        return [
            'title_modified_pct' => $pct($title_modified),
            'body_modified_pct' => $pct($body_modified),
            'any_modified_pct' => $pct($any_modified),
        ];
    }

    /**
     * @return string 'unmodified', 'modified', or 'no_ai'
     */
    public static function compare(string $ai_version, string $current_version, bool $strip_region_prefix = false): string
    {
        if (empty($ai_version)) {
            return 'no_ai';
        }

        $same = self::normalize_for_comparison($ai_version, $strip_region_prefix) === self::normalize_for_comparison($current_version, $strip_region_prefix);

        return $same ? 'unmodified' : 'modified';
    }

    /** Percentage of word insertions, deletions, and substitutions. */
    public static function change_percentage(
        string $ai_version,
        string $current_version,
        bool $strip_region_prefix = false
    ): float {
        $left = self::comparison_words($ai_version, $strip_region_prefix);
        $right = self::comparison_words($current_version, $strip_region_prefix);
        $maximum = max(count($left), count($right));
        if ($maximum === 0) {
            return 0.0;
        }

        $previous = range(0, count($right));
        foreach ($left as $left_index => $left_word) {
            $current = [$left_index + 1];
            foreach ($right as $right_index => $right_word) {
                $current[] = min(
                    $current[$right_index] + 1,
                    $previous[$right_index + 1] + 1,
                    $previous[$right_index] + ($left_word === $right_word ? 0 : 1)
                );
            }
            $previous = $current;
        }

        return round(($previous[count($right)] / $maximum) * 100, 1);
    }

    /** @return list<string> */
    private static function comparison_words(string $value, bool $strip_region_prefix): array
    {
        $normalized = self::normalize_for_comparison($value, $strip_region_prefix);

        return $normalized === '' ? [] : (preg_split('/\s+/u', $normalized) ?: []);
    }

    private static function normalize_for_comparison(string $value, bool $strip_region_prefix): string
    {
        $value = (string) preg_replace('/<\s*\/?(?:p|div|br|li|h[1-6])\b[^>]*>/i', ' ', $value);
        $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($strip_region_prefix && preg_match('/^([^.!?]{1,80})\s+-\s+(.+)$/u', $value, $matches)) {
            $prefix_words = preg_split('/\s+/u', trim($matches[1])) ?: [];
            if (count($prefix_words) <= 8) {
                $value = $matches[2];
            }
        }

        return $value;
    }

    private static function render_status_badge(string $status): string
    {
        switch ($status) {
            case 'unmodified':
                return '<span class="teksttv-audit-badge teksttv-audit-badge--ok">' . esc_html('Ongewijzigd') . '</span>';
            case 'modified':
                return '<span class="teksttv-audit-badge teksttv-audit-badge--edited">' . esc_html('Bewerkt') . '</span>';
            default:
                return '<span class="teksttv-audit-badge teksttv-audit-badge--none">' . esc_html('Geen AI') . '</span>';
        }
    }

    private static function render_status_with_change(string $status, ?float $change): string
    {
        $metric = $change === null ? '' : ' <span class="teksttv-audit-change">' . esc_html($change . '%') . '</span>';

        return self::render_status_badge($status) . $metric;
    }
}
