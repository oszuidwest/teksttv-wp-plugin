<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use TekstTV\Migrations;

class MigrationsTest extends TestCase
{
    public function test_run_migrates_persisted_options_and_role_capability(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
            'teksttv_campaigns' => [['id' => 'camp_1', 'group' => 'grp_sponsors']],
            'teksttv_loop_tv1' => [['type' => 'campaign', 'groups' => ['grp_sponsors']]],
        ];
        Functions\when('get_option')->alias(
            static function (string $name, mixed $default = false) use (&$stored): mixed {
                return array_key_exists($name, $stored) ? $stored[$name] : $default;
            }
        );

        $updates = [];
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value, mixed $autoload = null) use (&$stored, &$updates): bool {
                $stored[$name] = $value;
                $updates[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(true);

        $role = Mockery::mock();
        $role->shouldReceive('has_cap')->once()->with('manage_teksttv_campaigns')->andReturn(true);
        $role->shouldReceive('add_cap')->once()->with('manage_teksttv_commercials');
        $role->shouldReceive('remove_cap')->once()->with('manage_teksttv_campaigns');
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => [$role]]);

        $wpdb = Mockery::mock();
        $wpdb->options = 'wp_options';
        $wpdb->shouldReceive('esc_like')->once()->with('teksttv_loop_')->andReturn('teksttv\\_loop\\_');
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT option_name FROM wp_options');
        $wpdb->shouldReceive('get_col')->once()->andReturn(['teksttv_loop_tv1']);
        $previous_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = $wpdb;

        try {
            Migrations::run();
        } finally {
            $GLOBALS['wpdb'] = $previous_wpdb;
        }

        // Ids survive the migration verbatim, so existing references keep resolving.
        $this->assertSame([['id' => 'grp_sponsors', 'label' => 'Sponsors']], $updates['teksttv_commercial_blocks']);
        $this->assertSame('grp_sponsors', $updates['teksttv_campaigns'][0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $updates['teksttv_campaigns'][0]);
        $this->assertSame('commercial', $updates['teksttv_loop_tv1'][0]['type']);
        $this->assertSame(['grp_sponsors'], $updates['teksttv_loop_tv1'][0]['commercial_block_ids']);
        $this->assertSame(1, $updates['teksttv_data_version']);
    }

    public function test_run_on_fresh_install_only_stamps_version(): void
    {
        $stored = ['teksttv_data_version' => 0];
        Functions\when('get_option')->alias(
            static function (string $name, mixed $default = false) use (&$stored): mixed {
                return array_key_exists($name, $stored) ? $stored[$name] : $default;
            }
        );
        Functions\expect('update_option')->once()->with('teksttv_data_version', 1, true)->andReturn(true);
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(true);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);

        $wpdb = Mockery::mock();
        $wpdb->options = 'wp_options';
        $wpdb->shouldReceive('esc_like')->once()->andReturn('teksttv\\_loop\\_');
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT option_name FROM wp_options');
        $wpdb->shouldReceive('get_col')->once()->andReturn([]);
        $previous_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = $wpdb;

        try {
            Migrations::run();
        } finally {
            $GLOBALS['wpdb'] = $previous_wpdb;
        }
    }

    public function test_run_keeps_legacy_data_when_canonical_option_cannot_be_written(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
        ];
        Functions\when('get_option')->alias(
            static function (string $name, mixed $default = false) use (&$stored): mixed {
                return array_key_exists($name, $stored) ? $stored[$name] : $default;
            }
        );
        Functions\expect('update_option')->once()->with('teksttv_commercial_blocks', Mockery::type('array'))->andReturn(false);
        Functions\expect('delete_option')->never();
        Functions\expect('wp_roles')->never();

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_convert_campaigns_renames_relation_and_preserves_campaign_data(): void
    {
        $campaigns = Migrations::convert_campaigns([
            [
                'id' => 'camp_sponsor',
                'name' => 'Sponsor',
                'group' => 'grp_sponsors',
                'channels' => ['tv1'],
                'slides' => [10],
            ],
        ]);

        $this->assertSame('grp_sponsors', $campaigns[0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $campaigns[0]);
        $this->assertSame(['tv1'], $campaigns[0]['channels']);
        $this->assertSame([10], $campaigns[0]['slides']);
    }

    public function test_convert_loop_renames_commercial_type_and_block_references(): void
    {
        $loop = Migrations::convert_loop([
            ['type' => 'articles', 'count' => 3],
            [
                'type' => 'campaign',
                'groups' => ['grp_sponsors', 'grp_partners'],
                'limit' => 2,
            ],
        ]);

        $this->assertSame(['type' => 'articles', 'count' => 3], $loop[0]);
        $this->assertSame('commercial', $loop[1]['type']);
        $this->assertSame(['grp_sponsors', 'grp_partners'], $loop[1]['commercial_block_ids']);
        $this->assertSame(2, $loop[1]['limit']);
        $this->assertArrayNotHasKey('groups', $loop[1]);
    }

    public function test_converters_are_idempotent_for_canonical_data(): void
    {
        $campaigns = [['id' => 'camp_1', 'commercial_block_id' => 'cblock_1']];
        $loop = [['type' => 'commercial', 'commercial_block_ids' => ['cblock_1']]];

        $this->assertSame($campaigns, Migrations::convert_campaigns($campaigns));
        $this->assertSame($loop, Migrations::convert_loop($loop));
    }
}
