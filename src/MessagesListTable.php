<?php

namespace TekstTV;

/**
 * Air status column and filters on the message overview, so expired messages do not bury current ones.
 */
final class MessagesListTable
{
    private const COLUMN = 'teksttv_status';
    private const QUERY_VAR = 'teksttv_state';

    /** @var array<string, string> Filter key => view label. */
    private const VIEWS = [
        'active' => 'Actief',
        'upcoming' => 'Komt nog',
        'expired' => 'Verlopen',
    ];

    public static function init(): void
    {
        add_filter('manage_' . Messages::POST_TYPE . '_posts_columns', [self::class, 'add_column']);
        add_action('manage_' . Messages::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_filter('views_edit-' . Messages::POST_TYPE, [self::class, 'add_views']);
        add_action('pre_get_posts', [self::class, 'filter_query']);
    }

    /**
     * Air status of a message: unpublished, active, upcoming, expired or invalid.
     */
    public static function state(\WP_Post $post): string
    {
        if ($post->post_status !== 'publish') {
            return 'unpublished';
        }
        if (!Helpers::has_feature('scheduling')) {
            return 'active';
        }

        $schedule = ArticleContent::schedule_meta($post->ID);
        if ($schedule === null) {
            return 'invalid';
        }

        // Y-m-d strings compare chronologically.
        $today = current_datetime()->format('Y-m-d');
        if ($schedule['date_start'] !== '' && $schedule['date_start'] > $today) {
            return 'upcoming';
        }
        if ($schedule['date_end'] !== '' && $schedule['date_end'] < $today) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Meta query selecting published messages in a filter state.
     *
     * @return array<int|string, mixed>
     */
    public static function meta_query(string $state): array
    {
        $today = current_datetime()->format('Y-m-d');

        return match ($state) {
            'upcoming' => [
                ['key' => '_teksttv_date_start', 'value' => $today, 'compare' => '>', 'type' => 'DATE'],
            ],
            // A start date after the end date makes a message upcoming, as in state().
            'expired' => [
                'relation' => 'AND',
                ['key' => '_teksttv_date_end', 'value' => '', 'compare' => '!='],
                ['key' => '_teksttv_date_end', 'value' => $today, 'compare' => '<', 'type' => 'DATE'],
                [
                    'relation' => 'OR',
                    ['key' => '_teksttv_date_start', 'compare' => 'NOT EXISTS'],
                    ['key' => '_teksttv_date_start', 'value' => '', 'compare' => '='],
                    ['key' => '_teksttv_date_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE'],
                ],
            ],
            default => Helpers::get_date_range_meta_query(),
        };
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function add_column(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result[self::COLUMN] = 'Tekst TV';
            }
        }
        // Keep the column when another plugin removed the title.
        $result[self::COLUMN] ??= 'Tekst TV';

        return $result;
    }

    public static function render_column(string $column, int $post_id): void
    {
        $post = get_post($post_id);
        if ($column !== self::COLUMN || !$post) {
            return;
        }

        $state = self::state($post);
        $label = match ($state) {
            'unpublished' => get_post_status_object($post->post_status)->label ?? $post->post_status,
            'active' => 'Op Tekst TV',
            'upcoming' => 'Komt nog',
            'expired' => 'Verlopen',
            default => 'Ongeldige planning',
        };

        printf('<span class="teksttv-message-state teksttv-message-state--%s">%s</span>', esc_attr($state), esc_html($label));

        if (!Helpers::has_feature('scheduling') || $state === 'invalid') {
            return;
        }

        $details = array_filter([self::period_label($post->ID), self::days_label($post->ID)]);
        if ($details !== []) {
            printf('<br /><span class="description">%s</span>', esc_html(implode(' · ', $details)));
        }
    }

    /**
     * @param array<string, string> $views
     * @return array<string, string>
     */
    public static function add_views(array $views): array
    {
        if (!Helpers::has_feature('scheduling')) {
            return $views;
        }

        $current = self::requested_state();
        foreach (self::VIEWS as $state => $label) {
            $url = add_query_arg(
                ['post_type' => Messages::POST_TYPE, self::QUERY_VAR => $state],
                admin_url('edit.php')
            );
            $views[self::QUERY_VAR . '_' . $state] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $current === $state ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                self::count($state)
            );
        }

        return $views;
    }

    public static function filter_query(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== Messages::POST_TYPE) {
            return;
        }

        $state = self::requested_state();
        if ($state === null || !Helpers::has_feature('scheduling')) {
            return;
        }

        $query->set('post_status', 'publish');
        $query->set('meta_query', self::meta_query($state));
    }

    private static function requested_state(): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
        $state = isset($_GET[self::QUERY_VAR]) ? sanitize_key(wp_unslash($_GET[self::QUERY_VAR])) : '';

        return isset(self::VIEWS[$state]) ? $state : null;
    }

    private static function count(string $state): int
    {
        $query = new \WP_Query([
            'post_type' => Messages::POST_TYPE,
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => self::meta_query($state),
        ]);

        return (int) $query->found_posts;
    }

    private static function period_label(int $post_id): string
    {
        $start = ArticleContent::string_meta($post_id, '_teksttv_date_start') ?? '';
        $end = ArticleContent::string_meta($post_id, '_teksttv_date_end') ?? '';
        $format = static fn(string $date): string => date_i18n('j M Y', (int) strtotime($date));

        return match (true) {
            $start !== '' && $end !== '' => sprintf('%s t/m %s', $format($start), $format($end)),
            $start !== '' => sprintf('Vanaf %s', $format($start)),
            $end !== '' => sprintf('T/m %s', $format($end)),
            default => '',
        };
    }

    private static function days_label(int $post_id): string
    {
        $days = Helpers::normalize_days(get_post_meta($post_id, '_teksttv_days', true));
        if ($days === null || count($days) === 7) {
            return '';
        }

        $labels = array_intersect_key(Helpers::get_day_labels(), array_flip(array_map('intval', $days)));
        return $labels === [] ? 'Geen dagen' : 'Alleen ' . implode(', ', $labels);
    }
}
