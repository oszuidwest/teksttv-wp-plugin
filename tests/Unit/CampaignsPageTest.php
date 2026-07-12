<?php

namespace TekstTV\Tests\Unit;

use TekstTV\CampaignsPage;
use TekstTV\Helpers;

class CampaignsPageTest extends TestCase
{
    // =========================================================================
    // sanitize_groups() — stable ids survive renames
    // =========================================================================

    public function test_sanitize_groups_preserves_submitted_id_on_rename(): void
    {
        // A rename keeps the hidden id, so references to the group stay intact.
        $result = CampaignsPage::sanitize_groups([
            ['id' => 'grp_existing', 'label' => 'Nieuwe naam'],
        ]);

        $this->assertSame([['id' => 'grp_existing', 'label' => 'Nieuwe naam']], $result);
    }

    public function test_sanitize_groups_derives_id_for_new_row(): void
    {
        // A newly added row submits an empty id; the server derives a stable one.
        $result = CampaignsPage::sanitize_groups([
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertSame([
            ['id' => Helpers::campaign_group_id('Sponsors'), 'label' => 'Sponsors'],
        ], $result);
    }

    public function test_sanitize_groups_drops_empty_labels(): void
    {
        $result = CampaignsPage::sanitize_groups([
            ['id' => 'grp_a', 'label' => 'Sponsors'],
            ['id' => 'grp_b', 'label' => ''],
        ]);

        $this->assertSame([['id' => 'grp_a', 'label' => 'Sponsors']], $result);
    }

    public function test_sanitize_groups_reassigns_colliding_id(): void
    {
        // Two rows claiming the same id: keep the first, give the second a fresh
        // derived id rather than silently dropping a group the user defined.
        $result = CampaignsPage::sanitize_groups([
            ['id' => 'grp_a', 'label' => 'Sponsors'],
            ['id' => 'grp_a', 'label' => 'Duplicaat'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('grp_a', $result[0]['id']);
        $this->assertSame(Helpers::campaign_group_id('Duplicaat'), $result[1]['id']);
        $this->assertNotSame($result[0]['id'], $result[1]['id']);
    }

    public function test_sanitize_groups_drops_duplicate_new_rows_with_same_label(): void
    {
        // Two new rows (empty id) with the same label derive the same id, so the
        // second collapses into the first.
        $result = CampaignsPage::sanitize_groups([
            ['id' => '', 'label' => 'Sponsors'],
            ['id' => '', 'label' => 'Sponsors'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(Helpers::campaign_group_id('Sponsors'), $result[0]['id']);
    }

    public function test_sanitize_groups_non_array_returns_empty(): void
    {
        $this->assertSame([], CampaignsPage::sanitize_groups('not an array'));
    }
}
