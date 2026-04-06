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
}
