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

        if (!self::migrate_commercial_blocks()) {
            return;
        }
        self::migrate_capabilities();

        delete_option('teksttv_campaign_groups');
        // Autoloaded: this option gates every request, so it must ride along
        // in the alloptions query instead of costing its own SELECT.
        update_option(self::DATA_VERSION_OPTION, self::CURRENT_DATA_VERSION, true);
    }

    private static function migrate_commercial_blocks(): bool
    {
        // Block ids are opaque and survive the migration verbatim, so campaign
        // and loop references keep resolving without any id rewriting.
        $legacy_blocks = get_option('teksttv_campaign_groups', null);
        if (is_array($legacy_blocks)) {
            $commercial_blocks = CommercialsPage::sanitize_commercial_blocks($legacy_blocks);
            if (!self::store_option('teksttv_commercial_blocks', $commercial_blocks)) {
                return false;
            }
        }

        $campaigns = get_option('teksttv_campaigns', null);
        if (is_array($campaigns)) {
            if (!self::store_option('teksttv_campaigns', self::convert_campaigns($campaigns))) {
                return false;
            }
        }

        foreach (self::loop_option_names() as $option_name) {
            $loop = get_option($option_name, []);
            if (is_array($loop)) {
                if (!self::store_option($option_name, self::convert_loop($loop))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $campaigns
     * @return list<array<string, mixed>>
     */
    public static function convert_campaigns(array $campaigns): array
    {
        $converted = [];
        foreach ($campaigns as $campaign) {
            if (!is_array($campaign)) {
                continue;
            }

            if (!array_key_exists('commercial_block_id', $campaign)) {
                $campaign['commercial_block_id'] = sanitize_key($campaign['group'] ?? '');
            }
            unset($campaign['group']);
            $converted[] = $campaign;
        }

        return $converted;
    }

    /**
     * @param array<int|string, mixed> $loop
     * @return list<array<string, mixed>>
     */
    public static function convert_loop(array $loop): array
    {
        $converted = [];
        foreach ($loop as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'campaign') {
                $item['type'] = 'commercial';
            }
            if (($item['type'] ?? '') === 'commercial') {
                if (!array_key_exists('commercial_block_ids', $item)) {
                    $legacy_ids = isset($item['groups']) && is_array($item['groups']) ? $item['groups'] : [];
                    $item['commercial_block_ids'] = array_values(array_filter(array_map('sanitize_key', $legacy_ids)));
                }
                unset($item['groups']);
            }
            $converted[] = $item;
        }

        return $converted;
    }

    /**
     * Every stored loop option, including ones for channels no longer in the
     * configuration - those must migrate too, or they resurface legacy-shaped
     * when their channel is re-added.
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
        update_option($name, $value);
        return get_option($name, null) === $value;
    }
}
