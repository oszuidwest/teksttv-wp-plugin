<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\CommercialsPage;
use TekstTV\Helpers;

class CommercialsPageTest extends TestCase
{
    public function test_register_menu_uses_commercials_page_label(): void
    {
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                'teksttv',
                'Reclame',
                'Reclame',
                'manage_teksttv_commercials',
                'teksttv-commercials',
                [CommercialsPage::class, 'render_page']
            );
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                '',
                'Reclame',
                'Reclame',
                'manage_teksttv_commercials',
                'teksttv-campaigns',
                '__return_null'
            )
            ->andReturn('admin_page_teksttv-campaigns');
        Functions\expect('add_action')
            ->once()
            ->with('load-admin_page_teksttv-campaigns', [CommercialsPage::class, 'redirect_legacy_page']);

        CommercialsPage::register_menu();
    }

    // =========================================================================
    // sanitize_commercial_blocks() — stable ids survive renames
    // =========================================================================

    public function test_sanitize_commercial_blocks_preserves_submitted_id_on_rename(): void
    {
        // A rename keeps the hidden id, so references to the commercial block stay intact.
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => 'cblock_existing', 'label' => 'Nieuwe naam'],
        ]);

        $this->assertSame([['id' => 'cblock_existing', 'label' => 'Nieuwe naam']], $result);
    }

    public function test_sanitize_commercial_blocks_derives_id_for_new_row(): void
    {
        // A newly added row submits an empty id; the server derives a stable one.
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertSame([
            ['id' => Helpers::commercial_block_id('Sponsors'), 'label' => 'Sponsors'],
        ], $result);
    }

    public function test_sanitize_commercial_blocks_drops_empty_labels(): void
    {
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => 'cblock_a', 'label' => 'Sponsors'],
            ['id' => 'cblock_b', 'label' => ''],
        ]);

        $this->assertSame([['id' => 'cblock_a', 'label' => 'Sponsors']], $result);
    }

    public function test_sanitize_commercial_blocks_reassigns_colliding_id(): void
    {
        // Two rows claiming the same id: keep the first, give the second a fresh
        // derived id rather than silently dropping a commercial block the user defined.
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => 'cblock_a', 'label' => 'Sponsors'],
            ['id' => 'cblock_a', 'label' => 'Duplicaat'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('cblock_a', $result[0]['id']);
        $this->assertSame(Helpers::commercial_block_id('Duplicaat'), $result[1]['id']);
        $this->assertNotSame($result[0]['id'], $result[1]['id']);
    }

    public function test_sanitize_commercial_blocks_drops_duplicate_new_rows_with_same_label(): void
    {
        // Two new rows (empty id) with the same label derive the same id, so the
        // second collapses into the first.
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => '', 'label' => 'Sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(Helpers::commercial_block_id('Sponsors'), $result[0]['id']);
    }

    public function test_sanitize_commercial_blocks_drops_new_row_with_existing_label(): void
    {
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => 'cblock_existing', 'label' => 'Sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertSame([['id' => 'cblock_existing', 'label' => 'Sponsors']], $result);
    }

    public function test_sanitize_commercial_blocks_keeps_new_block_when_derived_id_hits_renamed_block(): void
    {
        // A block renamed away from 'Sponsors' keeps its label-derived id. A
        // new block reusing that old label derives the same id; it must get a
        // unique id instead of being silently dropped.
        $renamed_id = Helpers::commercial_block_id('Sponsors');
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => $renamed_id, 'label' => 'Oude sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame($renamed_id, $result[0]['id']);
        $this->assertSame('Sponsors', $result[1]['label']);
        $this->assertNotSame($result[0]['id'], $result[1]['id']);
    }

    public function test_sanitize_commercial_blocks_collapses_repeated_label_behind_suffixed_id(): void
    {
        // The renamed block holds the derived id, so new 'Sponsors' rows land
        // on suffixed ids; a repeated label must still collapse into one block
        // instead of claiming the next suffix.
        $renamed_id = Helpers::commercial_block_id('Sponsors');
        $result = CommercialsPage::sanitize_commercial_blocks([
            ['id' => $renamed_id, 'label' => 'Oude sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('Sponsors', $result[1]['label']);
    }

    public function test_sanitize_commercial_blocks_non_array_returns_empty(): void
    {
        $this->assertSame([], CommercialsPage::sanitize_commercial_blocks('not an array'));
    }

    public function test_campaign_save_reassigns_duplicate_submitted_ids(): void
    {
        Functions\expect('wp_generate_uuid4')->once()->andReturn('generated-uuid');

        $campaigns = self::callPrivate(
            CommercialsPage::class,
            'sanitize_campaigns',
            [
                [
                    ['id' => 'camp_existing', 'name' => 'Eerste', 'channels' => ['tv1']],
                    ['id' => 'camp_existing', 'name' => 'Tweede', 'channels' => ['tv1']],
                ],
                ['tv1'],
                []
            ]
        );

        $this->assertSame('camp_existing', $campaigns[0]['id']);
        $this->assertSame('camp_generated-uuid', $campaigns[1]['id']);
        $this->assertCount(2, array_unique(array_column($campaigns, 'id')));
    }

    public function test_save_clears_campaign_reference_to_deleted_commercial_block(): void
    {
        $previous_post = $_POST;
        $_POST = [
            'teksttv_commercials_nonce' => 'valid',
            'teksttv_commercial_blocks' => [
                ['id' => 'cblock_kept', 'label' => 'Behouden'],
            ],
            'teksttv_campaigns' => [
                [
                    'id' => 'camp_existing',
                    'name' => 'Campagne',
                    'commercial_block_id' => 'cblock_deleted',
                    'channels' => ['tv1'],
                ],
            ],
        ];

        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(
            static fn (string $name, mixed $default = false): mixed => $name === 'teksttv_channels'
                ? [['slug' => 'tv1', 'label' => 'TV 1']]
                : $default
        );
        $updates = [];
        Functions\when('update_option')->alias(
            static function (string $name, mixed $value) use (&$updates): bool {
                $updates[$name] = $value;
                return true;
            }
        );
        Functions\when('add_settings_error')->justReturn(null);

        try {
            self::callPrivate(CommercialsPage::class, 'handle_save');
        } finally {
            $_POST = $previous_post;
        }

        $this->assertSame('', $updates['teksttv_campaigns'][0]['commercial_block_id']);
    }

    public function test_save_rejects_invalid_nonce(): void
    {
        $previous_post = $_POST;
        $_POST = ['teksttv_commercials_nonce' => 'invalid'];
        Functions\expect('wp_verify_nonce')->once()->with('invalid', 'teksttv_save_commercials')->andReturn(false);
        Functions\expect('current_user_can')->never();
        Functions\expect('update_option')->never();
        Functions\when('add_settings_error')->justReturn(null);

        try {
            self::callPrivate(CommercialsPage::class, 'handle_save');
        } finally {
            $_POST = $previous_post;
        }
    }

    public function test_save_rejects_missing_commercials_capability(): void
    {
        $previous_post = $_POST;
        $_POST = ['teksttv_commercials_nonce' => 'valid'];
        Functions\expect('wp_verify_nonce')->once()->with('valid', 'teksttv_save_commercials')->andReturn(true);
        Functions\expect('current_user_can')->once()->with('manage_teksttv_commercials')->andReturn(false);
        Functions\expect('update_option')->never();
        Functions\when('add_settings_error')->justReturn(null);

        try {
            self::callPrivate(CommercialsPage::class, 'handle_save');
        } finally {
            $_POST = $previous_post;
        }
    }
}
