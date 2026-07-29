<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Updater;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class UpdaterTest extends TestCase
{
    public function test_release_without_matching_asset_is_not_offered(): void
    {
        $api = $this->make_github_api('teksttv.zip');

        self::callPrivate(Updater::class, 'configure_release_assets', [$api]);

        $this->assertNull($api->getLatestRelease());
    }

    public function test_release_with_matching_asset_uses_that_asset(): void
    {
        $asset_name = 'teksttv-0.0.4.zip';
        $asset_url = "https://github.com/oszuidwest/teksttv-wp-plugin/releases/download/0.0.4/$asset_name";
        $api = $this->make_github_api($asset_name);

        self::callPrivate(Updater::class, 'configure_release_assets', [$api]);

        $release = $api->getLatestRelease();
        $this->assertNotNull($release);
        $this->assertSame($asset_url, $release->downloadUrl);
    }

    private function make_github_api(string $asset_name): object
    {
        Functions\when('wp_parse_url')->alias(
            static fn(string $url, int $component) => parse_url($url, $component)
        );
        Functions\when('add_filter')->justReturn(true);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_doing_cron')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'tag_name' => '0.0.4',
            'zipball_url' => 'https://api.github.com/repos/oszuidwest/teksttv-wp-plugin/zipball/0.0.4',
            'created_at' => '2026-07-29T00:00:00Z',
            'draft' => false,
            'prerelease' => false,
            'assets' => [
                [
                    'name' => $asset_name,
                    'url' => 'https://api.github.com/repos/oszuidwest/teksttv-wp-plugin/releases/assets/1',
                    'browser_download_url' => "https://github.com/oszuidwest/teksttv-wp-plugin/releases/download/0.0.4/$asset_name",
                    'download_count' => 0,
                ],
            ],
            'body' => '',
        ], JSON_THROW_ON_ERROR));
        Functions\when('wp_remote_get')->justReturn([]);

        $api_class = PucFactory::getLatestClassVersion('GitHubApi');
        if (!is_string($api_class)) {
            throw new \RuntimeException('No compatible GitHub API class is registered.');
        }
        return new $api_class('https://github.com/oszuidwest/teksttv-wp-plugin/');
    }
}
