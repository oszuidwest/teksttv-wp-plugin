<?php

namespace TekstTV;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Provides automatic updates from GitHub Releases.
 */
class Updater
{
    private const REPO_URL = 'https://github.com/oszuidwest/teksttv-wp-plugin/';
    private const SLUG = 'teksttv';

    /**
     * WordPress only performs plugin-update checks from admin, cron, and
     * WP-CLI contexts; frontend/REST requests (the continuously polled slides
     * endpoint in particular) should not pay for constructing the checker.
     */
    public static function should_check_for_updates(): bool
    {
        return is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI);
    }

    public static function init(string $plugin_file): void
    {
        if (!self::should_check_for_updates()) {
            return;
        }

        if (!class_exists(PucFactory::class)) {
            return;
        }

        $checker = PucFactory::buildUpdateChecker(
            self::REPO_URL,
            $plugin_file,
            self::SLUG
        );

        $api = $checker->getVcsApi();
        if (method_exists($api, 'enableReleaseAssets')) {
            $api->enableReleaseAssets('/teksttv-.+\.zip$/');
        }
    }
}
