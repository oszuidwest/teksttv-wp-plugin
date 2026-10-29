<?php

namespace TekstTV;

/**
 * Planning column and filters on the message overview, so expired messages do not bury current ones.
 */
final class MessagesListTable
{
    private const COLUMN = 'teksttv_status';
    private const QUERY_VAR = 'teksttv_state';

    /** @var array<int, string> ISO day number => full Dutch name. */
    private const DAY_NAMES = [
        1 => 'maandag',
        2 => 'dinsdag',
        3 => 'woensdag',
        4 => 'donderdag',
        5 => 'vrijdag',
        6 => 'zaterdag',
        7 => 'zondag',
    ];

    /** @var array<string, string> Filter key => view label. */
    private const VIEWS = [
        'active' => 'Actief',
        'upcoming' => 'Gepland',
        'expired' => 'Verlopen',
    ];

    public static function init(): void
    {
        add_filter('manage_' . Messages::POST_TYPE . '_posts_columns', [self::class, 'add_column']);
        add_action('manage_' . Messages::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_filter('views_edit-' . Messages::POST_TYPE, [self::class, 'add_views']);
        add_action('pre_get_posts', [self::class, 'filter_query']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_style']);
    }

    /**
     * List tables do not mute descriptions, and the plugin stylesheet is not loaded here.
     */
    public static function enqueue_style(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'edit-' . Messages::POST_TYPE) {
            // Core's heading and description colors.
            $column = '.column-' . self::COLUMN;
            wp_add_inline_style('list-tables', $column . ' .teksttv-planning-state { color: #1d2327; } ' . $column . ' .description { color: #646970; }');
        }
    }

    /**
     * Planning state of a message: active, upcoming, expired or invalid.
     * It describes the period, not today's playout, so it matches the date-based views.
     */
    public static function state(\WP_Post $post): string
    {
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
     * Meta query for a filter state; callers restrict it to published messages.
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
            // state() checks a future start before a past end, so a message that has not started is never expired.
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
        if (!Helpers::has_feature('scheduling')) {
            return $columns;
        }

        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result[self::COLUMN] = 'Planning';
            }
        }
        // Keep the column when another plugin removed the title.
        $result[self::COLUMN] ??= 'Planning';

        return $result;
    }

    public static function render_column(string $column, int $post_id): void
    {
        $post = get_post($post_id);
        // Planning only matters once a message is published; core labels the other statuses next to the title.
        if ($column !== self::COLUMN || !$post || $post->post_status !== 'publish') {
            return;
        }

        // Laid out like core's Date column: status, then the date; the details are muted, as core does in the plugin list.
        $schedule = ArticleContent::schedule_meta($post->ID);
        if ($schedule === null) {
            // Core flags a missed schedule the same way.
            echo '<strong class="error-message">' . esc_html('Ongeldige planning') . '</strong>';
            return;
        }

        $state = self::state($post);
        $days = Helpers::normalize_days($schedule['days']);
        $lines = [
            // The view words, except on a weekday playout skips: the views stay stable across the week.
            '<span class="teksttv-planning-state">' . esc_html($state === 'active' && !Helpers::is_allowed_on_day($days) ? 'Vandaag niet' : self::VIEWS[$state]) . '</span>',
            '<span class="description">' . esc_html(self::period_label($state, $schedule['date_start'], $schedule['date_end'])) . '</span>',
        ];

        $days_label = self::days_label($days);
        if ($days_label !== null) {
            [$visible, $spoken] = $days_label;
            // Abbreviations are for the eye; screen readers get the full day names.
            $text = $visible === $spoken ? esc_html($visible) : sprintf('<span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span>', esc_html($visible), esc_html($spoken));
            $lines[] = '<span class="description">' . $text . '</span>';
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each line is escaped above.
        echo implode('<br />', $lines);
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

    /**
     * The date that matters for the state: the start before it begins, otherwise the end.
     */
    private static function period_label(string $state, string $start, string $end): string
    {
        // The site's date format, so it reads like the Date column next to it.
        $format = static fn(string $date): string => (string) wp_date(
            (string) get_option('date_format', 'j F Y'),
            // schedule_meta() only passes valid Y-m-d dates.
            (new \DateTimeImmutable($date, wp_timezone()))->getTimestamp()
        );

        return match (true) {
            $state === 'upcoming' => sprintf('Vanaf %s', $format($start)),
            $end === '' => 'Zonder einddatum',
            default => sprintf('t/m %s', $format($end)),
        };
    }

    /**
     * Restricted weekdays as [visible, spoken] text; null when every day is allowed.
     *
     * @param list<string>|null $days
     * @return array{string, string}|null
     */
    private static function days_label(?array $days): ?array
    {
        if ($days === null) {
            return null;
        }
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);
        if (count($days) === 7) {
            return null;
        }
        if ($days === []) {
            return ['Geen dagen geselecteerd', 'Geen dagen geselecteerd'];
        }
        if ($days === [6, 7]) {
            return ['Alleen weekend', 'Alleen weekend'];
        }
        if (count($days) === 1) {
            $name = self::DAY_NAMES[$days[0]];
            return ['Alleen ' . $name, 'Alleen ' . $name];
        }

        $short = static fn(int $day): string => strtolower(Helpers::get_day_labels()[(string) $day]);
        $long = static fn(int $day): string => self::DAY_NAMES[$day];
        // Three or more consecutive days read as a range, such as "ma t/m vr".
        if (count($days) > 2 && end($days) - $days[0] === count($days) - 1) {
            return [
                sprintf('Alleen %s t/m %s', $short($days[0]), $short(end($days))),
                sprintf('Alleen %s tot en met %s', $long($days[0]), $long(end($days))),
            ];
        }

        $list = static fn(array $names): string => implode(', ', array_slice($names, 0, -1)) . ' en ' . end($names);
        return ['Alleen ' . $list(array_map($short, $days)), 'Alleen ' . $list(array_map($long, $days))];
    }
}
