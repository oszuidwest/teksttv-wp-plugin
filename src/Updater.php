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
