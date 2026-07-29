<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Updater;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class UpdaterTest extends TestCase
{
    public function test_release_without_matching_asset_is_not_offered(): void
    {
        $api = $this->make_github_api([]);

        self::callPrivate(Updater::class, 'configure_release_assets', [$api]);

        $this->assertNull($api->getLatestRelease());
    }

    public function test_release_with_matching_asset_uses_that_asset(): void
    {
        $asset_url = 'https://github.com/oszuidwest/teksttv-wp-plugin/releases/download/0.0.4/teksttv-0.0.4.zip';
        $api = $this->make_github_api([
            [
                'name' => 'teksttv-0.0.4.zip',
                'url' => 'https://api.github.com/repos/oszuidwest/teksttv-wp-plugin/releases/assets/1',
                'browser_download_url' => $asset_url,
                'download_count' => 0,
            ],
        ]);

        self::callPrivate(Updater::class, 'configure_release_assets', [$api]);

        $release = $api->getLatestRelease();
        $this->assertNotNull($release);
        $this->assertSame($asset_url, $release->downloadUrl);
    }

    /**
     * @param list<array{name: string, url: string, browser_download_url: string, download_count: int}> $assets
     */
    private function make_github_api(array $assets): object
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
            'assets' => $assets,
            'body' => '',
        ], JSON_THROW_ON_ERROR));
        Functions\when('wp_remote_get')->justReturn([]);

        $factory_method = new \ReflectionMethod(PucFactory::class, 'getCompatibleClassVersion');
        $api_class = $factory_method->invoke(null, 'GitHubApi');
        if (!is_string($api_class)) {
            throw new \RuntimeException('No compatible GitHub API class is registered.');
        }
        return new $api_class('https://github.com/oszuidwest/teksttv-wp-plugin/');
    }
}
