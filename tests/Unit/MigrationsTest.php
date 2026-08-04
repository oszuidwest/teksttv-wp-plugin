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
        $role->shouldReceive('has_cap')->once()->with('manage_teksttv_commercials')->andReturn(true);
        $role->shouldReceive('add_cap')->once()->with('manage_teksttv_commercials');
        $role->shouldReceive('remove_cap')->once()->with('manage_teksttv_campaigns');
        Functions\when('wp_roles')->justReturn((object) ['roles' => ['commercial_manager' => []]]);
        Functions\when('get_role')->justReturn($role);

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

        $commercial_block_id = $updates['teksttv_commercial_blocks'][0]['id'];
        $this->assertSame($commercial_block_id, $updates['teksttv_campaigns'][0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $updates['teksttv_campaigns'][0]);
        $this->assertSame('commercial', $updates['teksttv_loop_tv1'][0]['type']);
        $this->assertSame([$commercial_block_id], $updates['teksttv_loop_tv1'][0]['commercial_block_ids']);
        $this->assertSame(1, $updates['teksttv_data_version']);
    }

    public function test_convert_commercial_blocks_creates_canonical_ids_and_reference_map(): void
    {
        [$blocks, $id_map] = Migrations::convert_commercial_blocks([
            ['id' => 'grp_sponsors', 'label' => 'Sponsors'],
            ['id' => 'grp_partners', 'label' => 'Partners'],
            ['id' => '', 'label' => ''],
            'invalid',
        ]);

        $this->assertCount(2, $blocks);
        $this->assertMatchesRegularExpression('/^cblock_[a-f0-9]{12}$/', $blocks[0]['id']);
        $this->assertSame('Sponsors', $blocks[0]['label']);
        $this->assertSame($blocks[0]['id'], $id_map['grp_sponsors']);
        $this->assertSame($blocks[1]['id'], $id_map['grp_partners']);
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
        Functions\expect('update_option')->once()->with('teksttv_commercial_blocks', Mockery::type('array'), false)->andReturn(false);
        Functions\expect('delete_option')->never();
        Functions\expect('wp_roles')->never();

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_convert_campaigns_rewrites_relation_and_preserves_campaign_data(): void
    {
        $campaigns = Migrations::convert_campaigns([
            [
                'id' => 'camp_sponsor',
                'name' => 'Sponsor',
                'group' => 'grp_sponsors',
                'channels' => ['tv1'],
                'slides' => [10],
            ],
        ], ['grp_sponsors' => 'cblock_sponsors']);

        $this->assertSame('cblock_sponsors', $campaigns[0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $campaigns[0]);
        $this->assertSame(['tv1'], $campaigns[0]['channels']);
        $this->assertSame([10], $campaigns[0]['slides']);
    }

    public function test_convert_campaigns_maps_missing_block_reference_deterministically(): void
    {
        $first = Migrations::convert_campaigns([['group' => 'orphaned']], []);
        $second = Migrations::convert_campaigns([['group' => 'orphaned']], []);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^cblock_[a-f0-9]{12}$/', $first[0]['commercial_block_id']);
    }

    public function test_convert_loop_rewrites_commercial_type_and_block_references(): void
    {
        $loop = Migrations::convert_loop([
            ['type' => 'articles', 'count' => 3],
            [
                'type' => 'campaign',
                'groups' => ['grp_sponsors', 'grp_partners'],
                'limit' => 2,
            ],
        ], [
            'grp_sponsors' => 'cblock_sponsors',
            'grp_partners' => 'cblock_partners',
        ]);

        $this->assertSame(['type' => 'articles', 'count' => 3], $loop[0]);
        $this->assertSame('commercial', $loop[1]['type']);
        $this->assertSame(['cblock_sponsors', 'cblock_partners'], $loop[1]['commercial_block_ids']);
        $this->assertSame(2, $loop[1]['limit']);
        $this->assertArrayNotHasKey('groups', $loop[1]);
    }

    public function test_converters_are_idempotent_for_canonical_data(): void
    {
        $campaigns = [['id' => 'camp_1', 'commercial_block_id' => 'cblock_1']];
        $loop = [['type' => 'commercial', 'commercial_block_ids' => ['cblock_1']]];

        $this->assertSame($campaigns, Migrations::convert_campaigns($campaigns, []));
        $this->assertSame($loop, Migrations::convert_loop($loop, []));
    }
}
