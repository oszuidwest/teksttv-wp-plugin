<?php

namespace TekstTV;

/**
 * Update the plugin from GitHub Releases through core's Update URI support.
 */
class Updater
{
    private const MANIFEST_URL = 'https://github.com/oszuidwest/teksttv-wp-plugin/releases/latest/download/teksttv.json';

    public static function init(string $plugin_file): void
    {
        $plugin = plugin_basename($plugin_file);

        // Every plugin with a github.com Update URI shares this hook.
        add_filter(
            'update_plugins_github.com',
            static fn($update, $data, $file) => $file === $plugin ? self::release() : $update,
            10,
            3
        );

        // Without this the details popup asks WordPress.org about the slug.
        add_filter(
            'plugins_api',
            static fn($result, $action, $args) =>
                ($args->slug ?? '') === 'teksttv' && ($release = self::release()) ? (object) $release : $result,
            10,
            3
        );
    }

    /**
     * @return array<string, mixed>|false The latest release, unless this site is too old for it.
     */
    private static function release(): array|false
    {
        $release = json_decode(wp_remote_retrieve_body(wp_remote_get(self::MANIFEST_URL)), true);

        // Core checks these minimums on some upgrade paths only.
        return isset($release['version'], $release['requires'], $release['requires_php'])
            && is_wp_version_compatible($release['requires'])
            && is_php_version_compatible($release['requires_php']) ? $release : false;
    }
}
