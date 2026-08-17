<?php

namespace TekstTV;

final class Migrations
{
    private const DATA_VERSION_OPTION = 'teksttv_data_version';
    private const CURRENT_DATA_VERSION = 1;

    public static function run(): void
    {
        if ((int) get_option(self::DATA_VERSION_OPTION, 0) >= self::CURRENT_DATA_VERSION) {
            return;
        }

        // Grant renamed capabilities even if data migration fails.
        self::migrate_capabilities();

        if (!self::migrate_commercial_blocks()) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV: commercials data migration failed to persist; retrying on next request.');
            return;
        }

        if (get_option('teksttv_campaign_groups', null) !== null && !delete_option('teksttv_campaign_groups')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV: could not remove legacy campaign groups; retrying on next request.');
            return;
        }
        // Autoload the migration version checked on every request.
        update_option(self::DATA_VERSION_OPTION, self::CURRENT_DATA_VERSION, true);
    }

    private static function migrate_commercial_blocks(): bool
    {
        // Preserve opaque IDs so stored references keep resolving.
        $legacy_blocks = get_option('teksttv_campaign_groups', null);
        $label_map = is_array($legacy_blocks) ? self::legacy_label_map($legacy_blocks) : [];
        // A retry must not overwrite newly saved canonical blocks.
        if (
            is_array($legacy_blocks)
            && get_option('teksttv_commercial_blocks', null) === null
            && !self::store_option('teksttv_commercial_blocks', CommercialsPage::sanitize_commercial_blocks($legacy_blocks))
        ) {
            return false;
        }

        $campaigns = get_option('teksttv_campaigns', null);
        if (is_array($campaigns) && !self::store_option('teksttv_campaigns', self::convert_campaigns($campaigns, $label_map))) {
            return false;
        }

        foreach (self::loop_option_names() as $option_name) {
            $loop = get_option($option_name, []);
            if (is_array($loop) && !self::store_option($option_name, self::convert_loop($loop, $label_map))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map legacy labels to the stable IDs derived by the current sanitizer.
     *
     * @param array<int|string, mixed> $legacy_blocks
     * @return array<string, string>
     */
    private static function legacy_label_map(array $legacy_blocks): array
    {
        $ids_by_label = array_column(CommercialsPage::sanitize_commercial_blocks($legacy_blocks), 'id', 'label');
        $map = [];
        foreach ($legacy_blocks as $row) {
            if (!is_string($row) || $row === '') {
                continue;
            }
            $label = sanitize_text_field($row);
            if ($label !== '' && isset($ids_by_label[$label])) {
                $map[$row] = $ids_by_label[$label];
            }
        }

        return $map;
    }

    /**
     * @param array<int|string, mixed>  $campaigns
     * @param array<string, string>     $label_map Legacy label to block ID.
     * @return list<array<string, mixed>>
     */
    public static function convert_campaigns(array $campaigns, array $label_map = []): array
    {
        $converted = [];
        foreach ($campaigns as $campaign) {
            if (!is_array($campaign)) {
                continue;
            }

            // Canonical fields win in mixed or imported records.
            if (array_key_exists('group', $campaign)) {
                if (!array_key_exists('commercial_block_id', $campaign)) {
                    $group = $campaign['group'] ?? '';
                    $group = is_scalar($group) ? (string) $group : '';
                    $campaign['commercial_block_id'] = sanitize_key($label_map[$group] ?? $group);
                }
                unset($campaign['group']);
            }
            $converted[] = $campaign;
        }

        return $converted;
    }

    /**
     * @param array<int|string, mixed> $loop
     * @param array<string, string>    $label_map Legacy label to block ID.
     * @return list<array<string, mixed>>
     */
    public static function convert_loop(array $loop, array $label_map = []): array
    {
        $converted = [];
        foreach ($loop as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Canonical fields win in mixed or imported records.
            $type = $item['type'] ?? '';
            if ($type === 'campaign' || $type === 'commercial') {
                $item['type'] = 'commercial';
                if (!array_key_exists('commercial_block_ids', $item)) {
                    $legacy_ids = isset($item['groups']) && is_array($item['groups']) ? $item['groups'] : [];
                    $legacy_ids = array_map(
                        static fn ($ref) => sanitize_key((string) ($label_map[(string) $ref] ?? $ref)),
                        array_filter($legacy_ids, 'is_scalar')
                    );
                    $item['commercial_block_ids'] = array_values(array_filter($legacy_ids));
                }
                unset($item['groups']);
            }
            $converted[] = $item;
        }

        return $converted;
    }

    /**
     * Include removed channels so legacy data cannot resurface when re-added.
     *
     * @return list<string>
     */
    private static function loop_option_names(): array
    {
        global $wpdb;

        $like = $wpdb->esc_like('teksttv_loop_') . '%';
        $query = $wpdb->prepare('SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $like);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above; one-time versioned migration across dynamic channel options.
        $option_names = $wpdb->get_col($query);

        return array_values(array_filter((array) $option_names, 'is_string'));
    }

    private static function migrate_capabilities(): void
    {
        foreach (wp_roles()->role_objects as $role) {
            if ($role->has_cap('manage_teksttv_campaigns')) {
                $role->add_cap('manage_teksttv_commercials');
                $role->remove_cap('manage_teksttv_campaigns');
            }
        }
    }

    private static function store_option(string $name, mixed $value): bool
    {
        // update_option() returns false for unchanged data; verify by reading back.
        return update_option($name, $value) || get_option($name, null) === $value;
    }
}
