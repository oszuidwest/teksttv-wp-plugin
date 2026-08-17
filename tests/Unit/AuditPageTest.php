<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\AuditPage;

class AuditPageTest extends TestCase
{
    public function test_register_menu_skips_audit_without_supported_text_generator(): void
    {
        $this->stubAiSupport(false);
        Functions\expect('add_submenu_page')->never();

        AuditPage::register_menu();
    }

    public function test_register_menu_adds_audit_with_supported_text_generator(): void
    {
        $this->stubAiSupport(true);
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                'teksttv',
                'AI-audit',
                'AI-audit',
                'manage_teksttv',
                'teksttv-audit',
                [AuditPage::class, 'render_page']
            );

        AuditPage::register_menu();
    }

    public function test_register_menu_skips_audit_and_ai_discovery_without_capability(): void
    {
        $this->stubAiSupport(true, false);
        Functions\expect('add_submenu_page')->never();

        AuditPage::register_menu();
    }

    public function test_selected_month_accepts_valid_query_value(): void
    {
        $_GET['month'] = '2026-08';

        $this->assertSame(
            ['month' => '2026-08', 'invalid' => false],
            self::callPrivate(AuditPage::class, 'selected_month')
        );
    }

    public function test_selected_month_defaults_without_flagging_when_absent(): void
    {
        Functions\expect('current_datetime')->once()->andReturn(new \DateTimeImmutable('2026-08-16'));
        unset($_GET['month']);

        $this->assertSame(
            ['month' => '2026-08', 'invalid' => false],
            self::callPrivate(AuditPage::class, 'selected_month')
        );
    }

    #[DataProvider('invalidMonths')]
    public function test_selected_month_falls_back_and_flags_invalid_query_value(mixed $month): void
    {
        Functions\expect('current_datetime')->once()->andReturn(new \DateTimeImmutable('2026-08-16'));
        $_GET['month'] = $month;

        $this->assertSame(
            ['month' => '2026-08', 'invalid' => true],
            self::callPrivate(AuditPage::class, 'selected_month')
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidMonths(): array
    {
        return [
            'out-of-range month' => ['2026-13'],
            'month zero' => ['2026-00'],
            'year zero' => ['0000-01'],
            'non-padded month' => ['2026-8'],
            'wrong separator' => ['2026/08'],
            'trailing content' => ['2026-08-extra'],
            'array' => [['2026-08']],
            'NUL byte' => ["2026-08\0"],
        ];
    }

    public function test_month_query_selects_posts_modified_in_the_month(): void
    {
        $args = self::callPrivate(AuditPage::class, 'ai_post_query_args', ['2026-08']);

        $this->assertSame(-1, $args['posts_per_page']);
        $this->assertSame('modified', $args['orderby']);
        $this->assertSame('DESC', $args['order']);
        $this->assertSame([
            'year' => 2026,
            'month' => 8,
            'column' => 'post_modified',
        ], $args['date_query'][0]);
    }

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

    private function stubAiSupport(bool $supported, bool $can_manage_teksttv = true): void
    {
        if ($can_manage_teksttv) {
            Functions\when('current_user_can')->justReturn(true);
        } else {
            Functions\expect('current_user_can')->with('manage_teksttv')->once()->andReturn(false);
        }
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false): mixed {
            return $key === 'teksttv_features' ? ['ai_generate'] : $default;
        });
        if ($can_manage_teksttv) {
            Functions\when('wp_supports_ai')->justReturn(true);
            Functions\when('wp_ai_client_prompt')->justReturn(
                $supported ? self::mockAiBuilder() : self::mockUnsupportedAiBuilder()
            );
        } else {
            Functions\expect('wp_supports_ai')->never();
            Functions\expect('wp_ai_client_prompt')->never();
        }
    }
}
