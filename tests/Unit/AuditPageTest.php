<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
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

    public function test_compute_stats_accepts_streamed_statuses(): void
    {
        $posts = static function (): \Generator {
            yield ['title_status' => 'no_ai', 'body_status' => 'modified'];
            for ($i = 0; $i < 50; $i++) {
                yield ['title_status' => 'no_ai', 'body_status' => 'unmodified'];
            }
        };

        $result = AuditPage::compute_stats($posts());

        $this->assertSame(0.0, $result['title_modified_pct']);
        $this->assertSame(2.0, $result['body_modified_pct']);
        $this->assertSame(2.0, $result['any_modified_pct']);
    }

    // =========================================================================
    // statuses_from_meta() / first_meta_values()
    // =========================================================================

    /** Mirror WordPress maybe_unserialize() for the raw postmeta path. */
    private static function stubMaybeUnserialize(): void
    {
        Functions\when('maybe_unserialize')->alias(static function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            $data = @unserialize($value);

            return $data === false && $value !== 'b:0;' ? $value : $data;
        });
    }

    public function test_statuses_from_meta_returns_no_ai_for_missing_meta(): void
    {
        $result = self::callPrivate(AuditPage::class, 'statuses_from_meta', [[]]);

        $this->assertSame('no_ai', $result['title_status']);
        $this->assertSame('no_ai', $result['body_status']);
    }

    public function test_statuses_from_meta_compares_values(): void
    {
        $result = self::callPrivate(AuditPage::class, 'statuses_from_meta', [[
            '_teksttv_ai_title' => 'AI kop',
            '_teksttv_title' => 'Bewerkte kop',
            '_teksttv_ai_body' => '<p>tekst</p>',
            '_teksttv_content' => ' <p>tekst</p> ',
        ]]);

        $this->assertSame('modified', $result['title_status']);
        $this->assertSame('unmodified', $result['body_status']);
    }

    public function test_statuses_from_meta_treats_non_scalar_as_absent(): void
    {
        $result = self::callPrivate(AuditPage::class, 'statuses_from_meta', [[
            '_teksttv_ai_title' => ['onverwacht'],
            '_teksttv_title' => 'Kop',
        ]]);

        $this->assertSame('no_ai', $result['title_status']);
    }

    public function test_first_meta_values_keeps_first_duplicate_and_deserializes(): void
    {
        self::stubMaybeUnserialize();
        $rows = [
            ['post_id' => '7', 'meta_key' => '_teksttv_ai_title', 'meta_value' => 'eerste'],
            ['post_id' => '7', 'meta_key' => '_teksttv_ai_title', 'meta_value' => 'tweede'],
            ['post_id' => 8, 'meta_key' => '_teksttv_content', 'meta_value' => serialize(['x'])],
        ];

        $result = self::callPrivate(AuditPage::class, 'first_meta_values', [$rows]);

        $this->assertSame('eerste', $result[7]['_teksttv_ai_title']);
        $this->assertSame(['x'], $result[8]['_teksttv_content']);
    }
}
