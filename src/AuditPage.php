<?php

namespace TekstTV;

class AuditPage
{
    private const PER_PAGE = 50;

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void
    {
        // Avoid potentially remote provider discovery for users who cannot access this page.
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page, no action taken
        $detail_post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if ($detail_post_id > 0) {
            self::render_detail_page($detail_post_id);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no action taken
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $query_result = self::query_ai_posts($paged);
        $posts = $query_result['posts'];
        $total_posts = $query_result['total'];
        $total_pages = (int) ceil($total_posts / self::PER_PAGE);
        $stats = self::compute_stats(self::query_ai_post_statuses());

        echo '<div class="wrap teksttv-admin">';
        echo '<h1>' . esc_html('AI-audit') . '</h1>';

        $format_pct = static fn(int|float $pct): string => $total_posts > 0 ? (string) $pct . '%' : '—';
        $stat_cards = [
            'Berichten met AI' => (string) $total_posts,
            'Koppen bewerkt' => $format_pct($stats['title_modified_pct']),
            'Teksten bewerkt' => $format_pct($stats['body_modified_pct']),
            'Totaal bewerkt' => $format_pct($stats['any_modified_pct']),
        ];

        ?>
        <div class="teksttv-tab-content teksttv-admin-column teksttv-admin-column--wide">
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
            <?php if (empty($posts)) : ?>
                <div class="teksttv-empty-state">
                    <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                    <p><?php echo esc_html('Nog geen berichten met AI-gegenereerde inhoud.'); ?></p>
                </div>
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
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=teksttv-audit&post_id=' . $post_data['id'])); ?>" class="button button-small"><?php echo esc_html('Bekijk'); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf('%d items', $total_posts)); ?></span>
                        <?php
                        echo wp_kses_post((string) paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
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
            </section>
        </div>
        <?php

        echo '</div>';
    }

    private static function render_detail_page(int $post_id): void
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
        $toggle_url = admin_url('admin.php?page=teksttv-audit&post_id=' . $post_id . '&view=' . $toggle_view);

        $ai_title = get_post_meta($post_id, '_teksttv_ai_title', true);
        $ai_body = get_post_meta($post_id, '_teksttv_ai_body', true);
        $current_title = get_post_meta($post_id, '_teksttv_title', true);
        $current_body = get_post_meta($post_id, '_teksttv_content', true);

        echo '<div class="wrap teksttv-admin">';
        echo '<h1>AI-audit: ' . esc_html($post->post_title) . '</h1>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=teksttv-audit')) . '">&larr; ' . esc_html('Terug naar overzicht') . '</a>';
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
     * Query posts with AI-generated content, paginated.
     *
     * @return array{posts: list<array{id: int, title: string, title_status: string, body_status: string, date: string}>, total: int}
     */
    private static function query_ai_posts(int $paged = 1): array
    {
        $query = new \WP_Query(array_merge(self::ai_post_query_args(), [
            'posts_per_page' => self::PER_PAGE,
            'paged' => $paged,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]));

        $results = [];
        foreach ($query->posts as $post) {
            $statuses = self::get_post_statuses($post->ID);

            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'title_status' => $statuses['title_status'],
                'body_status' => $statuses['body_status'],
                'date' => get_the_modified_date('j M Y H:i', $post),
            ];
        }

        return [
            'posts' => $results,
            'total' => $query->found_posts,
        ];
    }

    /**
     * Fetch audit statuses for every matching post without loading post
     * objects or writing the ID result to the query cache.
     *
     * @return list<array{title_status: string, body_status: string}>
     */
    private static function query_ai_post_statuses(): array
    {
        $query = new \WP_Query(array_merge(self::ai_post_query_args(), [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'cache_results' => false,
            'orderby' => 'none',
        ]));
        update_meta_cache('post', $query->posts);

        return array_map([self::class, 'get_post_statuses'], $query->posts);
    }

    /**
     * Which posts count as AI-audited; shared by the table and the statistics.
     *
     * @return array<string, mixed>
     */
    private static function ai_post_query_args(): array
    {
        return [
            'post_type' => 'post',
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
