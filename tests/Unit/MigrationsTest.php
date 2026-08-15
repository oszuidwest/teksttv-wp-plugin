<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use TekstTV\Helpers;
use TekstTV\Migrations;

class MigrationsTest extends TestCase
{
    private mixed $previous_wpdb = null;
    private bool $wpdb_replaced = false;
    private string|false $previous_error_log = false;

    protected function setUp(): void
    {
        parent::setUp();
        // The abort path logs via error_log(); keep it out of the test output.
        $this->previous_error_log = ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        if ($this->previous_error_log !== false) {
            ini_set('error_log', $this->previous_error_log);
        }
        if ($this->wpdb_replaced) {
            $GLOBALS['wpdb'] = $this->previous_wpdb;
            $this->wpdb_replaced = false;
        }
        parent::tearDown();
    }

    /**
     * Route get_option() reads to the given store; pass by reference so a test
     * can keep mutating the store through its own update_option stub.
     *
     * @param array<string, mixed> $stored
     */
    private static function stubOptionReads(array &$stored): void
    {
        Functions\when('get_option')->alias(
            static function (string $name, mixed $default = false) use (&$stored): mixed {
                return array_key_exists($name, $stored) ? $stored[$name] : $default;
            }
        );
    }

    /**
     * Replace $GLOBALS['wpdb'] with a mock whose loop-option scan returns the
     * given option names; tearDown() restores the original.
     *
     * @param list<string> $names
     */
    private function stubLoopOptionScan(array $names): void
    {
        $wpdb = Mockery::mock();
        $wpdb->options = 'wp_options';
        $wpdb->shouldReceive('esc_like')->once()->with('teksttv_loop_')->andReturn('teksttv\\_loop\\_');
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT option_name FROM wp_options');
        $wpdb->shouldReceive('get_col')->once()->andReturn($names);

        $this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb_replaced = true;
        $GLOBALS['wpdb'] = $wpdb;
    }

    public function test_run_migrates_persisted_options_and_role_capability(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
            'teksttv_campaigns' => [['id' => 'camp_1', 'group' => 'grp_sponsors']],
            'teksttv_loop_tv1' => [['type' => 'campaign', 'groups' => ['grp_sponsors']]],
        ];
        self::stubOptionReads($stored);

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

        $this->stubLoopOptionScan(['teksttv_loop_tv1']);

        Migrations::run();

        // Ids survive the migration verbatim, so existing references keep resolving.
        $this->assertSame([['id' => 'grp_sponsors', 'label' => 'Sponsors']], $updates['teksttv_commercial_blocks']);
        $this->assertSame('grp_sponsors', $updates['teksttv_campaigns'][0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $updates['teksttv_campaigns'][0]);
        $this->assertSame('commercial', $updates['teksttv_loop_tv1'][0]['type']);
        $this->assertSame(['grp_sponsors'], $updates['teksttv_loop_tv1'][0]['commercial_block_ids']);
        $this->assertSame(1, $updates['teksttv_data_version']);
    }

    public function test_run_maps_colliding_label_references_for_pre_id_data(): void
    {
        // Installs that skipped the removed one-time label-to-id migration
        // still store blocks as plain labels and reference them by label.
        $first_label = 'TekstTV collision 12618785700807';
        $second_label = 'TekstTV collision 281271414805175';
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [$first_label, $second_label],
            'teksttv_commercial_blocks' => null,
            'teksttv_campaigns' => [
                ['id' => 'camp_1', 'group' => $first_label],
                ['id' => 'camp_2', 'group' => $second_label],
            ],
            'teksttv_loop_tv1' => [['type' => 'campaign', 'groups' => [$first_label, $second_label]]],
        ];
        self::stubOptionReads($stored);

        $updates = [];
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value, mixed $autoload = null) use (&$stored, &$updates): bool {
                $stored[$name] = $value;
                $updates[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(true);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        $this->stubLoopOptionScan(['teksttv_loop_tv1']);

        Migrations::run();

        $base_id = Helpers::commercial_block_id($first_label);
        $this->assertSame($base_id, Helpers::commercial_block_id($second_label));
        $this->assertSame(
            [
                ['id' => $base_id, 'label' => $first_label],
                ['id' => $base_id . '_2', 'label' => $second_label],
            ],
            $updates['teksttv_commercial_blocks']
        );
        $this->assertSame($base_id, $updates['teksttv_campaigns'][0]['commercial_block_id']);
        $this->assertSame($base_id . '_2', $updates['teksttv_campaigns'][1]['commercial_block_id']);
        $this->assertSame([$base_id, $base_id . '_2'], $updates['teksttv_loop_tv1'][0]['commercial_block_ids']);
    }

    public function test_run_on_fresh_install_only_stamps_version(): void
    {
        $stored = ['teksttv_data_version' => 0];
        self::stubOptionReads($stored);
        Functions\expect('update_option')->once()->with('teksttv_data_version', 1, true)->andReturn(true);
        Functions\expect('delete_option')->never();
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        $this->stubLoopOptionScan([]);

        Migrations::run();
    }

    public function test_run_retries_when_legacy_option_cannot_be_deleted(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
        ];
        self::stubOptionReads($stored);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(false);
        Functions\expect('update_option')->never();
        $this->stubLoopOptionScan([]);

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_run_keeps_legacy_data_when_canonical_option_cannot_be_written(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
        ];
        self::stubOptionReads($stored);
        Functions\expect('update_option')->once()->with('teksttv_commercial_blocks', Mockery::type('array'))->andReturn(false);
        Functions\expect('delete_option')->never();
        // Capabilities migrate before the data step, so a data failure never
        // also locks users out of the renamed capability.
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_run_skips_everything_when_version_is_current(): void
    {
        // The DB hands back the stored version as a string; the gate must
        // treat it as current and touch nothing (no wpdb scan, no writes,
        // no role walk).
        $stored = ['teksttv_data_version' => '1'];
        self::stubOptionReads($stored);
        Functions\expect('update_option')->never();
        Functions\expect('delete_option')->never();
        Functions\expect('wp_roles')->never();

        Migrations::run();
    }

    public function test_run_aborts_before_deleting_legacy_data_when_campaigns_write_fails(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
            'teksttv_campaigns' => [['id' => 'camp_1', 'group' => 'grp_sponsors']],
        ];
        self::stubOptionReads($stored);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value) use (&$stored): bool {
                if ($name === 'teksttv_campaigns') {
                    return false;
                }
                $stored[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->never();

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_run_aborts_before_deleting_legacy_data_when_loop_write_fails(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => null,
            'teksttv_loop_tv1' => [['type' => 'campaign', 'groups' => ['grp_sponsors']]],
        ];
        self::stubOptionReads($stored);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value) use (&$stored): bool {
                if ($name === 'teksttv_loop_tv1') {
                    return false;
                }
                $stored[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->never();
        $this->stubLoopOptionScan(['teksttv_loop_tv1']);

        Migrations::run();

        $this->assertSame(0, $stored['teksttv_data_version']);
        $this->assertArrayHasKey('teksttv_campaign_groups', $stored);
    }

    public function test_run_retry_preserves_commercial_blocks_saved_by_the_admin(): void
    {
        // A retry after a partial failure must not re-derive blocks from the
        // retained legacy option over edits the admin has since saved.
        $admin_blocks = [['id' => 'grp_sponsors', 'label' => 'Hernoemd door beheerder']];
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => $admin_blocks,
        ];
        self::stubOptionReads($stored);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);

        $updates = [];
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value, mixed $autoload = null) use (&$stored, &$updates): bool {
                $stored[$name] = $value;
                $updates[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(true);
        $this->stubLoopOptionScan([]);

        Migrations::run();

        $this->assertArrayNotHasKey('teksttv_commercial_blocks', $updates);
        $this->assertSame($admin_blocks, $stored['teksttv_commercial_blocks']);
        $this->assertSame(1, $updates['teksttv_data_version']);
    }

    public function test_run_accepts_equal_read_back_when_update_reports_unchanged(): void
    {
        $stored = [
            'teksttv_data_version' => 0,
            'teksttv_campaign_groups' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_commercial_blocks' => [['id' => 'grp_sponsors', 'label' => 'Sponsors']],
            'teksttv_campaigns' => [['id' => 'camp_1', 'commercial_block_id' => 'grp_sponsors']],
        ];
        self::stubOptionReads($stored);
        Functions\when('wp_roles')->justReturn((object) ['role_objects' => []]);
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value) use (&$stored): bool {
                if ($name === 'teksttv_campaigns') {
                    return false;
                }
                $stored[$name] = $value;
                return true;
            }
        );
        Functions\expect('delete_option')->once()->with('teksttv_campaign_groups')->andReturn(true);
        $this->stubLoopOptionScan([]);

        Migrations::run();

        $this->assertSame(1, $stored['teksttv_data_version']);
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

    public function test_converters_prefer_canonical_fields_on_mixed_records(): void
    {
        // Records mixing legacy and canonical keys never come from the plugin
        // itself, but imports or hand-edited options can produce them; the
        // canonical field must win and legacy keys must still be stripped.
        $campaigns = Migrations::convert_campaigns([
            ['id' => 'camp_1', 'group' => 'grp_old', 'commercial_block_id' => 'grp_new'],
        ]);
        $this->assertSame('grp_new', $campaigns[0]['commercial_block_id']);
        $this->assertArrayNotHasKey('group', $campaigns[0]);

        $loop = Migrations::convert_loop([
            ['type' => 'campaign', 'commercial_block_ids' => ['grp_new'], 'groups' => ['grp_old']],
            ['type' => 'commercial', 'groups' => ['grp_old']],
        ]);
        $this->assertSame('commercial', $loop[0]['type']);
        $this->assertSame(['grp_new'], $loop[0]['commercial_block_ids']);
        $this->assertArrayNotHasKey('groups', $loop[0]);
        $this->assertSame(['grp_old'], $loop[1]['commercial_block_ids']);
        $this->assertArrayNotHasKey('groups', $loop[1]);
    }

    public function test_converters_are_idempotent_for_canonical_data(): void
    {
        $campaigns = [['id' => 'camp_1', 'commercial_block_id' => 'grp_1']];
        $loop = [['type' => 'commercial', 'commercial_block_ids' => ['grp_1']]];

        $this->assertSame($campaigns, Migrations::convert_campaigns($campaigns));
        $this->assertSame($loop, Migrations::convert_loop($loop));
    }
}
