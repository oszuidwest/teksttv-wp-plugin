<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\Updater;

class UpdaterTest extends TestCase
{
    private const FILE = 'teksttv/teksttv.php';
    private const RELEASE = ['slug' => 'teksttv', 'version' => '9.0.0', 'requires' => '7.1', 'requires_php' => '8.4'];

    public function test_offers_the_latest_release_to_this_plugin_only(): void
    {
        [$update, $details] = $this->filters(self::RELEASE);

        $this->assertSame(self::RELEASE, $update(false, [], self::FILE));
        $this->assertSame('theirs', $update('theirs', [], 'other/other.php'));
        $this->assertEquals((object) self::RELEASE, $details(false, 'plugin_information', (object) self::RELEASE));
        $this->assertFalse($details(false, 'plugin_information', (object) ['slug' => 'other']));
    }

    #[DataProvider('withheld_releases')]
    public function test_withholds_a_release_this_site_cannot_run(mixed $release): void
    {
        [$update, $details] = $this->filters($release);

        $this->assertFalse($update(false, [], self::FILE));
        $this->assertFalse($details(false, 'plugin_information', (object) self::RELEASE));
    }

    /** @return array<string, array{mixed}> */
    public static function withheld_releases(): array
    {
        return [
            'newer WordPress' => [['requires' => '7.2'] + self::RELEASE],
            'newer PHP' => [['requires_php' => '8.5'] + self::RELEASE],
            'no version' => [array_diff_key(self::RELEASE, ['version' => 0])],
            'no WordPress minimum' => [array_diff_key(self::RELEASE, ['requires' => 0])],
            'no PHP minimum' => [array_diff_key(self::RELEASE, ['requires_php' => 0])],
        ];
    }

    /** @return list<callable> The update and the details callback. */
    private function filters(mixed $release): array
    {
        Functions\when('plugin_basename')->justReturn(self::FILE);
        Functions\when('wp_remote_get')->justReturn([]);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($release));
        Functions\when('is_wp_version_compatible')->alias(static fn(string $v): bool => version_compare('7.1', $v, '>='));
        Functions\when('is_php_version_compatible')->alias(static fn(string $v): bool => version_compare('8.4', $v, '>='));

        $filters = [];
        foreach (['update_plugins_github.com', 'plugins_api'] as $hook) {
            Filters\expectAdded($hook)->once()->with(\Mockery::any(), 10, 3)->whenHappen(static function (callable $callback) use (&$filters): void {
                $filters[] = $callback;
            });
        }
        Updater::init('/plugins/' . self::FILE);

        return $filters;
    }
}
