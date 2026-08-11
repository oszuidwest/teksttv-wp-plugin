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

        // Capabilities first: on upgrades this is the only path that grants
        // the renamed capability, so a data-migration failure must not also
        // leave the Reclame page inaccessible.
        self::migrate_capabilities();

        if (!self::migrate_commercial_blocks()) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV: commercials data migration failed to persist; retrying on next request.');
            return;
        }

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
        $label_map = is_array($legacy_blocks) ? self::legacy_label_map($legacy_blocks) : [];
        // Only derive blocks while the canonical option is absent: a retry
        // after a partial failure must not clobber blocks the admin has since
        // saved through the Reclame page.
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
     * Pre-stable-id installs (before the one-time label-to-id migration that
     * commit 7f5d5d2 removed) stored blocks as plain label strings and
     * referenced them by label. Map those labels to the ids the sanitizer
     * derives for them, so such references keep resolving after migration.
     *
     * @param array<int|string, mixed> $legacy_blocks
     * @return array<string, string>
     */
    private static function legacy_label_map(array $legacy_blocks): array
    {
        $map = [];
        foreach ($legacy_blocks as $row) {
            if (!is_string($row) || $row === '') {
                continue;
            }
            $label = sanitize_text_field($row);
            if ($label !== '') {
                $map[$row] = Helpers::commercial_block_id($label);
            }
        }

        return $map;
    }

    /**
     * @param array<int|string, mixed>  $campaigns
     * @param array<string, string>     $label_map Legacy label to block id.
     * @return list<array<string, mixed>>
     */
    public static function convert_campaigns(array $campaigns, array $label_map = []): array
    {
        $converted = [];
        foreach ($campaigns as $campaign) {
            if (!is_array($campaign)) {
                continue;
            }

            // Canonical fields win over legacy ones so hand-imported or mixed
            // records never lose already-converted data.
            if (array_key_exists('group', $campaign)) {
                if (!array_key_exists('commercial_block_id', $campaign)) {
                    $group = $campaign['group'] ?? '';
                    if (is_string($group) && isset($label_map[$group])) {
                        $group = $label_map[$group];
                    }
                    $campaign['commercial_block_id'] = sanitize_key($group);
                }
                unset($campaign['group']);
            }
            $converted[] = $campaign;
        }

        return $converted;
    }

    /**
     * @param array<int|string, mixed> $loop
     * @param array<string, string>    $label_map Legacy label to block id.
     * @return list<array<string, mixed>>
     */
    public static function convert_loop(array $loop, array $label_map = []): array
    {
        $converted = [];
        foreach ($loop as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Canonical fields win over legacy ones so hand-imported or mixed
            // records never lose already-converted data.
            $type = $item['type'] ?? '';
            if ($type === 'campaign' || $type === 'commercial') {
                $item['type'] = 'commercial';
                if (!array_key_exists('commercial_block_ids', $item)) {
                    $legacy_ids = isset($item['groups']) && is_array($item['groups']) ? $item['groups'] : [];
                    $legacy_ids = array_map(
                        static fn ($ref) => is_string($ref) && isset($label_map[$ref]) ? $label_map[$ref] : $ref,
                        $legacy_ids
                    );
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
        // update_option() returns false for an unchanged value, so a re-run
        // after a partial failure falls back to a read-back that counts the
        // already-persisted value as success.
        return update_option($name, $value) || get_option($name, null) === $value;
    }
}
