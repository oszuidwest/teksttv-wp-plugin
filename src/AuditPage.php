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
        if (!Helpers::has_feature('ai_generate')) {
            return;
        }

        add_submenu_page(
            'teksttv',
            __('AI Audit', 'teksttv'),
            __('AI Audit', 'teksttv'),
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
        $stats = self::compute_stats($posts);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('AI Audit', 'teksttv') . '</h1>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-audit-stats">
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $total_posts); ?></span>
                    <span class="teksttv-audit-stat-label"><?php esc_html_e('Posts met AI', 'teksttv'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['title_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php esc_html_e('Koppen bewerkt', 'teksttv'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['body_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php esc_html_e('Teksten bewerkt', 'teksttv'); ?></span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['any_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label"><?php esc_html_e('Totaal bewerkt', 'teksttv'); ?></span>
                </div>
            </div>

            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=teksttv_export_training_data'), 'teksttv_export_training_data')); ?>" class="button"><span class="dashicons dashicons-download teksttv-button-icon"></span> <?php esc_html_e('Exporteer trainingsdata (JSONL)', 'teksttv'); ?></a>
                <span class="description"><?php esc_html_e('Exporteert alle bewerkte AI-teksten als DPO trainingsdata voor fine-tuning.', 'teksttv'); ?></span>
            </p>

            <?php if (empty($posts)) : ?>
                <div class="teksttv-card">
                    <p><?php esc_html_e('Nog geen posts met AI-gegenereerde content.', 'teksttv'); ?></p>
                </div>
            <?php else : ?>
                <table class="widefat teksttv-audit-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Post', 'teksttv'); ?></th>
                            <th><?php esc_html_e('Kop', 'teksttv'); ?></th>
                            <th><?php esc_html_e('Tekst', 'teksttv'); ?></th>
                            <th><?php esc_html_e('Datum', 'teksttv'); ?></th>
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
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=teksttv-audit&post_id=' . $post_data['id'])); ?>" class="button button-small"><?php esc_html_e('Bekijk', 'teksttv'); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf(/* translators: %d: number of items */ __('%d items', 'teksttv'), $total_posts)); ?></span>
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
            echo '<div class="wrap"><h1>' . esc_html__('Post niet gevonden', 'teksttv') . '</h1></div>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only toggle, no action taken
        $split = !isset($_GET['view']) || $_GET['view'] !== 'inline';
        $toggle_view = $split ? 'inline' : 'split';
        $toggle_label = $split ? __('Inline weergave', 'teksttv') : __('Side-by-side weergave', 'teksttv');
        $toggle_url = admin_url('admin.php?page=teksttv-audit&post_id=' . $post_id . '&view=' . $toggle_view);

        $ai_title = get_post_meta($post_id, '_teksttv_ai_title', true);
        $ai_body = get_post_meta($post_id, '_teksttv_ai_body', true);
        $current_title = get_post_meta($post_id, '_teksttv_title', true);
        $current_body = get_post_meta($post_id, '_teksttv_content', true);

        echo '<div class="wrap">';
        echo '<h1>AI Audit: ' . esc_html($post->post_title) . '</h1>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=teksttv-audit')) . '">&larr; ' . esc_html__('Terug naar overzicht', 'teksttv') . '</a>';
        echo ' | <a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html__('Post bewerken', 'teksttv') . '</a>';
        echo ' | <a href="' . esc_url($toggle_url) . '">' . esc_html($toggle_label) . '</a>';
        echo '</p>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-card">
                <h3><?php esc_html_e('Kop', 'teksttv'); ?> <?php echo self::render_status_badge(self::compare($ai_title, $current_title)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                <?php
                $title_diff = self::render_diff($ai_title ?: '', $current_title ?: '', $split);
                if ($title_diff) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output from wp_text_diff
                    echo $title_diff;
                } else {
                    echo '<p class="description">' . esc_html__('Geen wijzigingen.', 'teksttv') . '</p>';
                }
                ?>
            </div>

            <div class="teksttv-card">
                <h3><?php esc_html_e('Tekst', 'teksttv'); ?> <?php echo self::render_status_badge(self::compare($ai_body, $current_body)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
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
                    echo '<p class="description">' . esc_html__('Geen wijzigingen.', 'teksttv') . '</p>';
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
            'title_left' => __('AI-versie', 'teksttv'),
            'title_right' => __('Huidige versie', 'teksttv'),
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
        $query = new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => self::PER_PAGE,
            'paged' => $paged,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_teksttv_ai_title', 'compare' => 'EXISTS'],
                ['key' => '_teksttv_ai_body', 'compare' => 'EXISTS'],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $results = [];
        foreach ($query->posts as $post) {
            $ai_title = get_post_meta($post->ID, '_teksttv_ai_title', true);
            $ai_body = get_post_meta($post->ID, '_teksttv_ai_body', true);
            $current_title = get_post_meta($post->ID, '_teksttv_title', true);
            $current_body = get_post_meta($post->ID, '_teksttv_content', true);

            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'title_status' => self::compare($ai_title, $current_title),
                'body_status' => self::compare($ai_body, $current_body),
                'date' => get_the_modified_date('j M Y H:i', $post),
            ];
        }

        return [
            'posts' => $results,
            'total' => $query->found_posts,
        ];
    }

    /**
     * Compute stats from an already-fetched posts array.
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
                return '<span class="teksttv-audit-badge teksttv-audit-badge--ok">' . esc_html__('Ongewijzigd', 'teksttv') . '</span>';
            case 'modified':
                return '<span class="teksttv-audit-badge teksttv-audit-badge--edited">' . esc_html__('Bewerkt', 'teksttv') . '</span>';
            default:
                return '<span class="teksttv-audit-badge teksttv-audit-badge--none">' . esc_html__('Geen AI', 'teksttv') . '</span>';
        }
    }
}
