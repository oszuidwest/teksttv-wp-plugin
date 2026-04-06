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

        $stats = self::get_stats();

        echo '<div class="wrap">';
        echo '<h1>AI Audit</h1>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-audit-stats">
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['total']); ?></span>
                    <span class="teksttv-audit-stat-label">Posts met AI</span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['title_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label">Koppen bewerkt</span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['body_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label">Teksten bewerkt</span>
                </div>
                <div class="teksttv-audit-stat-card">
                    <span class="teksttv-audit-stat-number"><?php echo esc_html((string) $stats['any_modified_pct']); ?>%</span>
                    <span class="teksttv-audit-stat-label">Totaal bewerkt</span>
                </div>
            </div>

            <p>
                <a href="<?php echo esc_url(wp_nonce_url(rest_url('teksttv/v1/export-training-data'), 'wp_rest')); ?>" class="button"><span class="dashicons dashicons-download teksttv-button-icon"></span> Exporteer trainingsdata (JSONL)</a>
                <span class="description">Exporteert alle bewerkte AI-teksten als DPO trainingsdata voor fine-tuning.</span>
            </p>

            <?php
            $posts = self::get_ai_posts();
            if (empty($posts)) :
                ?>
                <div class="teksttv-card">
                    <p>Nog geen posts met AI-gegenereerde content.</p>
                </div>
            <?php else : ?>
                <table class="widefat teksttv-audit-table">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Kop</th>
                            <th>Tekst</th>
                            <th>Datum</th>
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
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=teksttv-audit&post_id=' . $post_data['id'])); ?>" class="button button-small">Bekijk</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        echo '</div>';
    }

    private static function render_detail_page(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post) {
            echo '<div class="wrap"><h1>Post niet gevonden</h1></div>';
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
        echo '<a href="' . esc_url(admin_url('admin.php?page=teksttv-audit')) . '">&larr; Terug naar overzicht</a>';
        echo ' | <a href="' . esc_url(get_edit_post_link($post_id)) . '">Post bewerken</a>';
        echo ' | <a href="' . esc_url($toggle_url) . '">' . esc_html($toggle_label) . '</a>';
        echo '</p>';

        ?>
        <div class="teksttv-tab-content">
            <div class="teksttv-card">
                <h3>Kop <?php echo self::render_status_badge(self::compare($ai_title, $current_title)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                <?php
                $title_diff = self::render_diff($ai_title ?: '', $current_title ?: '', $split);
                if ($title_diff) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output from wp_text_diff
                    echo $title_diff;
                } else {
                    echo '<p class="description">Geen wijzigingen.</p>';
                }
                ?>
            </div>

            <div class="teksttv-card">
                <h3>Tekst <?php echo self::render_status_badge(self::compare($ai_body, $current_body)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
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
                    echo '<p class="description">Geen wijzigingen.</p>';
                }
                ?>
            </div>
        </div>
        <?php

        echo '</div>';
    }

    /**
     * Render a diff using WordPress built-in wp_text_diff.
     */
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
     * Get all posts that have AI-generated content.
     *
     * @return list<array{id: int, title: string, title_status: string, body_status: string, date: string}>
     */
    private static function get_ai_posts(): array
    {
        $query = new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => 100,
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

        return $results;
    }

    /**
     * Get aggregate statistics with separate title/body breakdowns.
     *
     * @return array{total: int, title_modified_pct: int|float, body_modified_pct: int|float, any_modified_pct: int|float}
     */
    private static function get_stats(): array
    {
        $posts = self::get_ai_posts();
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
            'total' => $total,
            'title_modified_pct' => $pct($title_modified),
            'body_modified_pct' => $pct($body_modified),
            'any_modified_pct' => $pct($any_modified),
        ];
    }

    /**
     * Compare AI version with current version.
     *
     * @return string 'unmodified', 'modified', or 'no_ai'
     */
    private static function compare(string $ai_version, string $current_version): string
    {
        if (empty($ai_version)) {
            return 'no_ai';
        }

        return trim($ai_version) === trim($current_version) ? 'unmodified' : 'modified';
    }

    /**
     * Render a status badge.
     */
    private static function render_status_badge(string $status): string
    {
        switch ($status) {
            case 'unmodified':
                return '<span class="teksttv-audit-badge teksttv-audit-badge--ok">Ongewijzigd</span>';
            case 'modified':
                return '<span class="teksttv-audit-badge teksttv-audit-badge--edited">Bewerkt</span>';
            default:
                return '<span class="teksttv-audit-badge teksttv-audit-badge--none">Geen AI</span>';
        }
    }
}
