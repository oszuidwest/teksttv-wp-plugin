<?php

namespace TekstTV;

class AuditPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void
    {
        // Model discovery may call remote providers.
        if (!current_user_can('manage_teksttv') || !Helpers::ai_supported()) {
            return;
        }

        add_submenu_page(
            'teksttv',
            'AI-audit',
            'AI-audit',
            'manage_teksttv',
            'teksttv-audit',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        ['month' => $selected_month, 'invalid' => $invalid_month] = self::selected_month();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page, no action taken
        $detail_post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if ($detail_post_id > 0) {
            self::render_detail_page($detail_post_id, $selected_month);
            return;
        }

        ['posts' => $posts, 'error' => $query_failed] = self::query_ai_posts($selected_month);
        $shown_posts = count($posts);
        $stats = self::compute_stats($posts);

        echo '<div class="wrap teksttv-admin">';
        echo '<h1>' . esc_html('AI-audit') . '</h1>';

        $format_pct = static fn(int|float $pct): string => $shown_posts > 0 ? $pct . '%' : '—';
        $stat_cards = [
            'Berichten met AI' => (string) $shown_posts,
            'Koppen bewerkt' => $format_pct($stats['title_modified_pct']),
            'Teksten bewerkt' => $format_pct($stats['body_modified_pct']),
            'Totaal bewerkt' => $format_pct($stats['any_modified_pct']),
        ];

        ?>
        <div class="teksttv-tab-content teksttv-admin-column teksttv-admin-column--wide">
            <?php if ($invalid_month) : ?>
            <div class="notice notice-warning inline">
                <p><?php echo esc_html('De opgegeven maand is ongeldig; de huidige maand wordt getoond.'); ?></p>
            </div>
            <?php endif; ?>
            <form method="get" class="teksttv-audit-month-filter">
                <input type="hidden" name="page" value="teksttv-audit" />
                <label for="teksttv-audit-month"><?php echo esc_html('Maand van laatste wijziging'); ?></label>
                <?php // Fallback for browsers without a native month input. ?>
                <input type="month" id="teksttv-audit-month" name="month" value="<?php echo esc_attr($selected_month); ?>" pattern="[0-9]{4}-(0[1-9]|1[0-2])" placeholder="JJJJ-MM" title="<?php echo esc_attr('Gebruik JJJJ-MM'); ?>" required />
                <?php submit_button('Tonen', 'secondary', '', false); ?>
            </form>

            <dl class="teksttv-audit-stats">
                <?php foreach ($stat_cards as $stat_label => $stat_value) : ?>
                <div class="teksttv-audit-stat-card">
                    <dt class="teksttv-audit-stat-label"><?php echo esc_html($stat_label); ?></dt>
                    <dd class="teksttv-audit-stat-number"><?php echo esc_html($stat_value); ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>

            <section class="teksttv-card teksttv-workbench-section teksttv-audit-results">
                <h2><?php echo esc_html('Berichten'); ?></h2>
            <?php if ($query_failed) : ?>
                <div class="notice notice-error inline">
                    <p><?php echo esc_html('De auditgegevens konden niet worden opgehaald. Probeer het later opnieuw.'); ?></p>
                </div>
            <?php elseif (empty($posts)) : ?>
                <?php AdminPage::render_empty_state('chart-bar', 'Geen AI-auditgegevens in deze maand', 'Er zijn in deze maand geen berichten met AI-gegenereerde inhoud gewijzigd.'); ?>
            <?php else : ?>
                <div class="teksttv-table-scroll">
                <table class="widefat teksttv-audit-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html('Bericht'); ?></th>
                            <th><?php echo esc_html('Kop'); ?></th>
                            <th><?php echo esc_html('Tekst'); ?></th>
                            <th><?php echo esc_html('Datum'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post_data) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($post_data['title']); ?></strong></td>
                            <td><?php echo self::render_status_badge($post_data['title_status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></td>
                            <td><?php echo self::render_status_badge($post_data['body_status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></td>
                            <td><?php echo esc_html($post_data['date']); ?></td>
                            <td><a href="<?php echo esc_url(self::page_url($selected_month, ['post_id' => $post_data['id']])); ?>" class="button button-small"><?php echo esc_html('Bekijk'); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
            </section>
        </div>
        <?php

        echo '</div>';
    }

    private static function render_detail_page(int $post_id, string $selected_month): void
    {
        $post = get_post($post_id);
        if (!$post) {
            echo '<div class="wrap teksttv-admin"><h1>' . esc_html('Post niet gevonden') . '</h1></div>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only toggle, no action taken
        $split = !isset($_GET['view']) || $_GET['view'] !== 'inline';
        $toggle_view = $split ? 'inline' : 'split';
        $toggle_label = $split ? 'Inline weergave' : 'Side-by-side weergave';
        $overview_url = self::page_url($selected_month);
        $toggle_url = self::page_url($selected_month, ['post_id' => $post_id, 'view' => $toggle_view]);

        $ai_title = get_post_meta($post_id, '_teksttv_ai_title', true);
        $ai_body = get_post_meta($post_id, '_teksttv_ai_body', true);
        $current_title = get_post_meta($post_id, '_teksttv_title', true);
        $current_body = get_post_meta($post_id, '_teksttv_content', true);

        echo '<div class="wrap teksttv-admin">';
        echo '<h1>AI-audit: ' . esc_html($post->post_title) . '</h1>';
        echo '<p>';
        echo '<a href="' . esc_url($overview_url) . '">&larr; ' . esc_html('Terug naar overzicht') . '</a>';
        echo ' | <a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html('Bericht bewerken') . '</a>';
        echo ' | <a href="' . esc_url($toggle_url) . '">' . esc_html($toggle_label) . '</a>';
        echo '</p>';

        ?>
        <div class="teksttv-tab-content teksttv-admin-column teksttv-admin-column--wide">
            <div class="teksttv-card">
                <h2><?php echo esc_html('Kop'); ?> <?php echo self::render_status_badge(self::compare($ai_title, $current_title)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></h2>
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
                <h2><?php echo esc_html('Tekst'); ?> <?php echo self::render_status_badge(self::compare($ai_body, $current_body)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup with escaped labels ?></h2>
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
     * Query posts with AI-generated content modified in one calendar month.
     *
     * @return array{posts: list<array{id: int, title: string, title_status: string, body_status: string, date: string}>, error: bool}
     */
    private static function query_ai_posts(string $selected_month): array
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        // WP_Query exposes failures only through a fresh $wpdb error.
        $query_count = $wpdb->num_queries;
        $query = new \WP_Query(self::ai_post_query_args($selected_month));
        $query_failed = $wpdb->num_queries > $query_count && $wpdb->last_error !== '';

        $results = [];
        foreach ($query->posts as $post) {
            $statuses = self::get_post_statuses($post->ID);

            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'title_status' => $statuses['title_status'],
                'body_status' => $statuses['body_status'],
                'date' => get_the_modified_date('j M Y H:i', $post) ?: '—',
            ];
        }

        return [
            'posts' => $results,
            'error' => $query_failed,
        ];
    }

    /**
     * Build an audit-page URL that carries the month selection along.
     *
     * @param array<string, int|string> $args
     */
    private static function page_url(string $selected_month, array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => 'teksttv-audit', 'month' => $selected_month], $args),
            admin_url('admin.php')
        );
    }

    /**
     * Resolve a valid YYYY-MM value and flag rejected input.
     *
     * @return array{month: string, invalid: bool}
     */
    private static function selected_month(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only filter; the exact format is validated below.
        $raw_month = $_GET['month'] ?? null;
        $requested_month = is_string($raw_month) ? wp_unslash($raw_month) : '';

        // WP_Date_Query drops year zero and would match every January.
        if (preg_match('/\A(?!0000-)[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $requested_month) === 1) {
            return ['month' => $requested_month, 'invalid' => false];
        }

        return [
            'month' => current_datetime()->format('Y-m'),
            'invalid' => $raw_month !== null && $raw_month !== '',
        ];
    }

    /**
     * Query constraints for AI-audited posts in one month.
     *
     * @return array<string, mixed>
     */
    private static function ai_post_query_args(string $selected_month): array
    {
        [$year, $month] = array_map('intval', explode('-', $selected_month));

        return [
            'post_type' => 'post',
            'posts_per_page' => -1,
            'update_post_term_cache' => false,
            'orderby' => 'modified',
            'order' => 'DESC',
            'date_query' => [
                [
                    'year' => $year,
                    'month' => $month,
                    'column' => 'post_modified',
                ],
            ],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_teksttv_ai_title', 'compare' => 'EXISTS'],
                ['key' => '_teksttv_ai_body', 'compare' => 'EXISTS'],
            ],
        ];
    }

    /**
     * @return array{title_status: string, body_status: string}
     */
    private static function get_post_statuses(int $post_id): array
    {
        return [
            'title_status' => self::compare(
                get_post_meta($post_id, '_teksttv_ai_title', true),
                get_post_meta($post_id, '_teksttv_title', true)
            ),
            'body_status' => self::compare(
                get_post_meta($post_id, '_teksttv_ai_body', true),
                get_post_meta($post_id, '_teksttv_content', true)
            ),
        ];
    }

    /**
     * Compute percentages from fetched audit statuses.
     *
     * @param list<array{title_status: string, body_status: string, ...}> $posts
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
    public static function compare(string $ai_version, string $current_version): string
    {
        if (empty($ai_version)) {
            return 'no_ai';
        }

        return trim($ai_version) === trim($current_version) ? 'unmodified' : 'modified';
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
}
