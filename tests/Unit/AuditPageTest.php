<?php

namespace TekstTV\Tests\Unit;

use TekstTV\AuditPage;

class AuditPageTest extends TestCase
{
    // =========================================================================
    // compare()
    // =========================================================================

    public function test_compare_returns_no_ai_when_ai_version_empty(): void
    {
        $result = AuditPage::compare('', 'current text');
        $this->assertSame('no_ai', $result);
    }

    public function test_compare_returns_unmodified_when_same(): void
    {
        $result = AuditPage::compare('ai text', 'ai text');
        $this->assertSame('unmodified', $result);
    }

    public function test_compare_returns_unmodified_ignoring_whitespace(): void
    {
        $result = AuditPage::compare('  ai text  ', 'ai text');
        $this->assertSame('unmodified', $result);
    }

    public function test_compare_returns_modified_when_different(): void
    {
        $result = AuditPage::compare('ai text', 'edited text');
        $this->assertSame('modified', $result);
    }

    public function test_compare_treats_zero_as_an_ai_baseline(): void
    {
        $this->assertSame('unmodified', AuditPage::compare('0', '0'));
        $this->assertSame('modified', AuditPage::compare('0', '1'));
    }

    public function test_compare_ignores_html_whitespace_and_body_region_prefixes(): void
    {
        $this->assertSame('unmodified', AuditPage::compare('<p>AI <strong>tekst</strong></p>', ' AI tekst '));
        $this->assertSame('unmodified', AuditPage::compare("Rock 'n roll", 'Rock &apos;n roll'));
        $this->assertSame(
            'unmodified',
            AuditPage::compare('<p>LEIDEN - Dezelfde tekst</p>', '<div>DEN HAAG - Dezelfde tekst</div>', true)
        );
    }

    public function test_change_percentage_uses_documented_word_edit_distance(): void
    {
        $this->assertSame(0.0, AuditPage::change_percentage('<p>een twee drie vier</p>', 'een  twee drie vier'));
        $this->assertSame(25.0, AuditPage::change_percentage('een twee drie vier', 'een twee ander vier'));
        $this->assertSame(20.0, AuditPage::change_percentage('een twee drie vier', 'een twee drie vier vijf'));
        $this->assertSame(0.0, AuditPage::change_percentage('LEIDEN - een twee', 'DEN HAAG - een twee', true));
    }

    public function test_generation_status_classifies_human_and_partial_ai_posts(): void
    {
        $this->assertSame('human', AuditPage::classify_generation_status('no_ai', 'no_ai'));
        $this->assertSame('ai_unmodified', AuditPage::classify_generation_status('unmodified', 'no_ai'));
        $this->assertSame('ai_edited', AuditPage::classify_generation_status('no_ai', 'modified'));
    }

    public function test_filters_accept_only_documented_values(): void
    {
        $this->assertSame([
            'month' => '2026-08',
            'generation_status' => 'ai_edited',
            'change_band' => 'substantial',
        ], AuditPage::sanitize_filters([
            'month' => '2026-08',
            'generation_status' => 'ai_edited',
            'change_band' => 'substantial',
        ]));
        $this->assertSame([
            'month' => '',
            'generation_status' => '',
            'change_band' => '',
        ], AuditPage::sanitize_filters([
            'month' => '2026-13',
            'generation_status' => 'forged',
            'change_band' => 'all-of-it',
        ]));
    }

    public function test_change_bands_cover_every_tenth_of_a_percent(): void
    {
        $audit = [
            'generation_status' => 'ai_edited',
            'max_change' => 0.0,
        ];
        $filters = [
            'month' => '',
            'generation_status' => '',
            'change_band' => 'unchanged',
        ];

        $this->assertTrue(self::callPrivate(AuditPage::class, 'matches_filters', [$audit, $filters]));

        $boundaries = [
            [0.1, 'minor'],
            [25.0, 'minor'],
            [25.1, 'substantial'],
            [50.0, 'substantial'],
            [50.1, 'extensive'],
            [100.0, 'extensive'],
        ];
        foreach ($boundaries as [$change, $band]) {
            $audit['max_change'] = $change;
            $filters['change_band'] = $band;
            $this->assertTrue(self::callPrivate(AuditPage::class, 'matches_filters', [$audit, $filters]));
        }
    }

    public function test_compare_returns_no_ai_when_both_empty(): void
    {
        $result = AuditPage::compare('', '');
        $this->assertSame('no_ai', $result);
    }

    // =========================================================================
    // compute_stats()
    // =========================================================================

    public function test_compute_stats_returns_zeros_for_empty_array(): void
    {
        $result = AuditPage::compute_stats([]);
        $this->assertSame(0, $result['title_modified_pct']);
        $this->assertSame(0, $result['body_modified_pct']);
        $this->assertSame(0, $result['any_modified_pct']);
    }

    public function test_compute_stats_all_unmodified(): void
    {
        $posts = [
            ['title_status' => 'unmodified', 'body_status' => 'unmodified'],
            ['title_status' => 'unmodified', 'body_status' => 'unmodified'],
        ];
        $result = AuditPage::compute_stats($posts);
        $this->assertSame(0.0, $result['title_modified_pct']);
        $this->assertSame(0.0, $result['body_modified_pct']);
        $this->assertSame(0.0, $result['any_modified_pct']);
    }

    public function test_compute_stats_mixed(): void
    {
        $posts = [
            ['title_status' => 'modified', 'body_status' => 'unmodified'],
            ['title_status' => 'unmodified', 'body_status' => 'modified'],
            ['title_status' => 'no_ai', 'body_status' => 'no_ai'],
            ['title_status' => 'modified', 'body_status' => 'modified'],
        ];
        $result = AuditPage::compute_stats($posts);

        // 2 out of 4 titles modified = 50%
        $this->assertSame(50.0, $result['title_modified_pct']);
        // 2 out of 4 bodies modified = 50%
        $this->assertSame(50.0, $result['body_modified_pct']);
        // 3 out of 4 have any modification = 75%
        $this->assertSame(75.0, $result['any_modified_pct']);
    }

    public function test_compute_stats_all_modified(): void
    {
        $posts = [
            ['title_status' => 'modified', 'body_status' => 'modified'],
        ];
        $result = AuditPage::compute_stats($posts);
        $this->assertSame(100.0, $result['title_modified_pct']);
        $this->assertSame(100.0, $result['body_modified_pct']);
        $this->assertSame(100.0, $result['any_modified_pct']);
    }

    public function test_compute_stats_rounds_uneven_ratio(): void
    {
        $posts = [['title_status' => 'no_ai', 'body_status' => 'modified']];
        for ($i = 0; $i < 50; $i++) {
            $posts[] = ['title_status' => 'no_ai', 'body_status' => 'unmodified'];
        }

        $result = AuditPage::compute_stats($posts);

        $this->assertSame(0.0, $result['title_modified_pct']);
        $this->assertSame(2.0, $result['body_modified_pct']);
        $this->assertSame(2.0, $result['any_modified_pct']);
    }
}
