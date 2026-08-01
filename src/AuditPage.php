<?php

namespace TekstTV;

class AuditPage
{
    private const PER_PAGE = 50;

    private const STATS_CHUNK = 500;

    private const AUDIT_META_KEYS = [
        '_teksttv_ai_title',
        '_teksttv_title',
        '_teksttv_ai_body',
        '_teksttv_content',
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

        $query_result = self::query_ai_posts($paged);
        $posts = $query_result['posts'];
        $total_posts = $query_result['total'];
        $total_pages = (int) ceil($total_posts / self::PER_PAGE);
        $stats = self::compute_stats(self::query_ai_post_statuses());

        echo '<div class="wrap">';
        echo '<h1>' . esc_html('AI Audit') . '</h1>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-audit-stats">
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $total_posts); ?></span>
                    <span class="teksttv-audit-stat-label"><?php echo esc_html('Posts met AI'); ?></span>
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
                    <p><?php echo esc_html('Nog geen posts met AI-gegenereerde content.'); ?></p>
                </div>
            <?php else : ?>
                <table class="widefat teksttv-audit-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html('Post'); ?></th>
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
                            <td><?php echo self::render_status_badge($post_data['title_status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td><?php echo self::render_status_badge($post_data['body_status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
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
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]);
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
                <h3><?php echo esc_html('Kop'); ?> <?php echo self::render_status_badge(self::compare($ai_title, $current_title)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
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
                <h3><?php echo esc_html('Tekst'); ?> <?php echo self::render_status_badge(self::compare($ai_body, $current_body)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
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
     * Stream audit statuses for every matching post. Only the ID list is held
     * in full; post objects are never loaded and metadata is read per chunk
     * of only the audited keys, so neither the query cache nor the
     * request-wide meta cache grows with the dataset.
     *
     * @return \Generator<int, array{title_status: string, body_status: string}>
     */
    private static function query_ai_post_statuses(): \Generator
    {
        $query = new \WP_Query(array_merge(self::ai_post_query_args(), [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'cache_results' => false,
            'orderby' => 'none',
        ]));
        $post_ids = $query->posts;
        unset($query);

        for ($offset = 0; $offset < count($post_ids); $offset += self::STATS_CHUNK) {
            $chunk = array_slice($post_ids, $offset, self::STATS_CHUNK);
            $meta = self::first_meta_values(self::query_audit_meta_rows($chunk));

            foreach ($chunk as $post_id) {
                yield self::statuses_from_meta($meta[$post_id] ?? []);
            }
        }
    }

    /**
     * Read only the audited meta keys for a chunk of posts, bypassing the
     * meta API so the object cache is not filled with every key of every post.
     *
     * @param list<int> $post_ids
     * @return list<array{post_id: string, meta_key: string, meta_value: string}>
     */
    private static function query_audit_meta_rows(array $post_ids): array
    {
        global $wpdb;

        if ($post_ids === []) {
            return [];
        }

        $id_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $key_placeholders = implode(',', array_fill(0, count(self::AUDIT_META_KEYS), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- the IN() lists contain only placeholders; all values go through prepare()
        $sql = $wpdb->prepare(
            'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta
                . ' WHERE post_id IN (' . $id_placeholders . ') AND meta_key IN (' . $key_placeholders . ')'
                . ' ORDER BY meta_id',
            array_merge($post_ids, self::AUDIT_META_KEYS)
        );

        return $wpdb->get_results($sql, ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * First value per post and key, deserialized - rows arrive ordered by
     * meta_id, matching get_post_meta's single-value semantics for duplicate
     * keys. This is the only place raw database values are normalized.
     *
     * @param list<array{post_id: string|int, meta_key: string, meta_value: string}> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function first_meta_values(array $rows): array
    {
        $meta = [];
        foreach ($rows as $row) {
            $post_id = (int) $row['post_id'];
            if (!isset($meta[$post_id][$row['meta_key']])) {
                $meta[$post_id][$row['meta_key']] = maybe_unserialize($row['meta_value']);
            }
        }

        return $meta;
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
        $meta = [];
        foreach (self::AUDIT_META_KEYS as $key) {
            $meta[$key] = get_post_meta($post_id, $key, true);
        }

        return self::statuses_from_meta($meta);
    }

    /**
     * Pair each AI meta value with its current counterpart; the single source
     * of which keys the audit compares.
     *
     * @param array<string, mixed> $meta Already-deserialized meta values keyed by meta key.
     * @return array{title_status: string, body_status: string}
     */
    private static function statuses_from_meta(array $meta): array
    {
        return [
            'title_status' => self::compare(
                self::meta_string($meta['_teksttv_ai_title'] ?? ''),
                self::meta_string($meta['_teksttv_title'] ?? '')
            ),
            'body_status' => self::compare(
                self::meta_string($meta['_teksttv_ai_body'] ?? ''),
                self::meta_string($meta['_teksttv_content'] ?? '')
            ),
        ];
    }

    /**
     * Non-scalar meta (deserialized arrays or objects) audits as absent.
     */
    private static function meta_string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Compute stats from streamed post statuses.
     *
     * @param iterable<array{title_status: string, body_status: string}> $posts
     * @return array{title_modified_pct: int|float, body_modified_pct: int|float, any_modified_pct: int|float}
     */
    public static function compute_stats(iterable $posts): array
    {
        $total = 0;
        $title_modified = 0;
        $body_modified = 0;
        $any_modified = 0;

        foreach ($posts as $post_data) {
            $total++;
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
