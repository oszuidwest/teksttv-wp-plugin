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

    public static function init(string $plugin_file): void
    {
        // WordPress only performs plugin-update checks from admin, cron, and
        // WP-CLI contexts; skip constructing the checker on frontend/REST
        // requests (the continuously polled slides endpoint in particular).
        if (!is_admin() && !wp_doing_cron() && !(defined('WP_CLI') && WP_CLI)) {
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
        self::configure_release_assets($api);
        $checker->addFilter(
            'vcs_update_detection_strategies',
            static fn(array $strategies): array => self::require_release_strategy($strategies)
        );
    }

    private static function configure_release_assets(object $api): void
    {
        if (method_exists($api, 'enableReleaseAssets')) {
            $api->enableReleaseAssets(
                '/^' . self::SLUG . '-[0-9].*\.zip$/',
                $api::REQUIRE_RELEASE_ASSETS
            );
        }
    }

    /**
     * Prevent Plugin Update Checker from falling back to tag or branch archives.
     *
     * @param array<string, callable> $strategies
     * @return array<string, callable>
     */
    private static function require_release_strategy(array $strategies): array
    {
        if (!isset($strategies['latest_release'])) {
            return [];
        }

        return ['latest_release' => $strategies['latest_release']];
    }
}
