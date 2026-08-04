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

        if (!self::migrate_commercial_blocks() || !self::migrate_capabilities()) {
            return;
        }

        delete_option('teksttv_campaign_groups');
        self::store_option(self::DATA_VERSION_OPTION, self::CURRENT_DATA_VERSION);
    }

    private static function migrate_commercial_blocks(): bool
    {
        $legacy_blocks = get_option('teksttv_campaign_groups', null);
        $current_blocks = get_option('teksttv_commercial_blocks', null);

        if (is_array($legacy_blocks)) {
            [$commercial_blocks, $id_map] = self::convert_commercial_blocks($legacy_blocks);
        } elseif (is_array($current_blocks)) {
            $commercial_blocks = $current_blocks;
            $id_map = [];
            foreach ($commercial_blocks as $block) {
                if (is_array($block) && !empty($block['id'])) {
                    $id_map[(string) $block['id']] = (string) $block['id'];
                }
            }
        } else {
            $commercial_blocks = [];
            $id_map = [];
        }

        if (!self::store_option('teksttv_commercial_blocks', $commercial_blocks)) {
            return false;
        }

        $campaigns = get_option('teksttv_campaigns', []);
        if (is_array($campaigns)) {
            if (!self::store_option('teksttv_campaigns', self::convert_campaigns($campaigns, $id_map))) {
                return false;
            }
        }

        foreach (self::loop_option_names() as $option_name) {
            $loop = get_option($option_name, []);
            if (is_array($loop)) {
                if (!self::store_option($option_name, self::convert_loop($loop, $id_map))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $legacy_blocks
     * @return array{0: list<array{id: string, label: string}>, 1: array<string, string>}
     */
    public static function convert_commercial_blocks(array $legacy_blocks): array
    {
        $commercial_blocks = [];
        $id_map = [];
        $seen = [];

        foreach ($legacy_blocks as $legacy_block) {
            if (!is_array($legacy_block)) {
                continue;
            }

            $label = sanitize_text_field($legacy_block['label'] ?? '');
            if ($label === '') {
                continue;
            }

            $legacy_id = sanitize_key($legacy_block['id'] ?? '');
            $new_id = self::converted_commercial_block_id($legacy_id, $label, $seen);
            $seen[$new_id] = true;
            if ($legacy_id !== '' && !isset($id_map[$legacy_id])) {
                $id_map[$legacy_id] = $new_id;
            }
            $commercial_blocks[] = ['id' => $new_id, 'label' => $label];
        }

        return [$commercial_blocks, $id_map];
    }

    /**
     * @param array<int|string, mixed> $campaigns
     * @param array<string, string>     $id_map
     * @return list<array<string, mixed>>
     */
    public static function convert_campaigns(array $campaigns, array $id_map): array
    {
        $converted = [];
        foreach ($campaigns as $campaign) {
            if (!is_array($campaign)) {
                continue;
            }

            if (!array_key_exists('commercial_block_id', $campaign)) {
                $legacy_id = sanitize_key($campaign['group'] ?? '');
                $campaign['commercial_block_id'] = self::convert_reference_id($legacy_id, $id_map);
            }
            unset($campaign['group']);
            $converted[] = $campaign;
        }

        return $converted;
    }

    /**
     * @param array<int|string, mixed> $loop
     * @param array<string, string>     $id_map
     * @return list<array<string, mixed>>
     */
    public static function convert_loop(array $loop, array $id_map): array
    {
        $converted = [];
        foreach ($loop as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'campaign') {
                $item['type'] = 'commercial';
            }
            if (($item['type'] ?? '') === 'commercial' && !array_key_exists('commercial_block_ids', $item)) {
                $legacy_ids = isset($item['groups']) && is_array($item['groups']) ? $item['groups'] : [];
                $item['commercial_block_ids'] = array_values(array_filter(array_map(
                    static fn($id): string => self::convert_reference_id(sanitize_key($id), $id_map),
                    $legacy_ids
                )));
            }
            if (($item['type'] ?? '') === 'commercial') {
                unset($item['groups']);
            }
            $converted[] = $item;
        }

        return $converted;
    }

    /**
     * @param array<string, bool> $seen
     */
    private static function converted_commercial_block_id(string $legacy_id, string $label, array $seen): string
    {
        $source = $legacy_id !== '' ? $legacy_id : $label;
        $suffix = substr(md5($source), 0, 12);
        $id = 'cblock_' . $suffix;
        $collision = 0;
        while (isset($seen[$id])) {
            $collision++;
            $id = 'cblock_' . substr(md5($source . ':' . $collision), 0, 12);
        }
        return $id;
    }

    /**
     * @param array<string, string> $id_map
     */
    private static function convert_reference_id(string $legacy_id, array $id_map): string
    {
        if ($legacy_id === '') {
            return '';
        }
        if (isset($id_map[$legacy_id])) {
            return $id_map[$legacy_id];
        }
        if (str_starts_with($legacy_id, 'cblock_')) {
            return $legacy_id;
        }
        return 'cblock_' . substr(md5($legacy_id), 0, 12);
    }

    /**
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

    private static function migrate_capabilities(): bool
    {
        foreach (array_keys(wp_roles()->roles) as $role_name) {
            $role = get_role($role_name);
            if ($role && $role->has_cap('manage_teksttv_campaigns')) {
                $role->add_cap('manage_teksttv_commercials');
                if (!$role->has_cap('manage_teksttv_commercials')) {
                    return false;
                }
                $role->remove_cap('manage_teksttv_campaigns');
            }
        }

        return true;
    }

    private static function store_option(string $name, mixed $value): bool
    {
        update_option($name, $value, false);
        return get_option($name, null) === $value;
    }
}
