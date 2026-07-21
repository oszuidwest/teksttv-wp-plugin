<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AdminPage;
use TekstTV\Helpers;

class AdminPageTest extends TestCase
{
    public function test_preview_url_shares_site_origin_true_for_same_host(): void
    {
        Functions\when('wp_parse_url')->alias(fn ($url, $comp) => parse_url($url, $comp));

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_for_separate_host(): void
    {
        Functions\when('wp_parse_url')->alias(fn ($url, $comp) => parse_url($url, $comp));

        $this->assertFalse(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.teksttv.pages.dev/bredanu/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_when_empty(): void
    {
        $this->assertFalse(AdminPage::preview_url_shares_site_origin('', 'https://bredanu.nl'));
    }

    public function test_preview_url_shares_site_origin_ignores_host_case(): void
    {
        Functions\when('wp_parse_url')->alias(fn ($url, $comp) => parse_url($url, $comp));

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://BredaNU.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_sanitize_ai_prompts_preserves_omitted_technical_fields(): void
    {
        Functions\when('sanitize_textarea_field')->alias(fn ($s) => $s);

        $stored = [
            'provider' => 'openai',
            'model' => 'openai/gpt-5',
            'region_taxonomy' => 'regio',
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 4096,
            'system' => 'Oude system prompt',
        ];
        Functions\expect('get_option')->with('teksttv_ai_prompts', [])->andReturn($stored);

        // Partial submission: only the editorial prompt fields are present,
        // as rendered for a role without the region/technical sections.
        $result = AdminPage::sanitize_ai_prompts([
            'system' => 'Nieuwe system prompt',
            'prompt_title' => 'Titel prompt',
            'prompt_body' => 'Body prompt',
        ]);

        // Submitted field updates...
        $this->assertSame('Nieuwe system prompt', $result['system']);
        // ...omitted technical/region fields keep their stored values.
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('openai/gpt-5', $result['model']);
        $this->assertSame('regio', $result['region_taxonomy']);
        $this->assertSame(0.7, $result['temperature']);
        $this->assertSame(4096, $result['max_tokens']);
    }

    public function test_sanitize_ai_prompts_non_array_input_keeps_current(): void
    {
        $stored = ['provider' => 'openai', 'model' => 'openai/gpt-5'];
        Functions\expect('get_option')->with('teksttv_ai_prompts', [])->andReturn($stored);

        $this->assertSame($stored, AdminPage::sanitize_ai_prompts('not an array'));
    }

    public function test_sanitize_channels_deduplicates_slug_keeping_first(): void
    {
        Functions\when('add_settings_error')->justReturn(null);

        $input = [
            ['slug' => 'tv1', 'label' => 'TV 1'],
            ['slug' => 'tv1', 'label' => 'Duplicaat'],
            ['slug' => 'tv2', 'label' => 'TV 2'],
        ];

        $result = AdminPage::sanitize_channels($input);

        $this->assertCount(2, $result);
        $this->assertSame('tv1', $result[0]['slug']);
        $this->assertSame('TV 1', $result[0]['label']);
        $this->assertSame('tv2', $result[1]['slug']);
    }

    // =========================================================================
    // sanitize_channels()
    // =========================================================================

    public function test_sanitize_channels_valid_input(): void
    {
        $input = [
            ['slug' => 'tv1', 'label' => 'TV 1'],
            ['slug' => 'tv2', 'label' => 'TV 2'],
        ];

        $result = AdminPage::sanitize_channels($input);

        $this->assertCount(2, $result);
        $this->assertSame('tv1', $result[0]['slug']);
        $this->assertSame('TV 1', $result[0]['label']);
        $this->assertSame('tv2', $result[1]['slug']);
    }

    public function test_sanitize_channels_filters_empty_slug(): void
    {
        $input = [
            ['slug' => '', 'label' => 'No Slug'],
            ['slug' => 'tv1', 'label' => 'Valid'],
        ];

        $result = AdminPage::sanitize_channels($input);

        $this->assertCount(1, $result);
        $this->assertSame('tv1', $result[0]['slug']);
    }

    public function test_sanitize_channels_filters_empty_label(): void
    {
        $input = [
            ['slug' => 'tv1', 'label' => ''],
        ];

        $result = AdminPage::sanitize_channels($input);
        $this->assertSame([], $result);
    }

    public function test_sanitize_channels_non_array_returns_empty(): void
    {
        $this->assertSame([], AdminPage::sanitize_channels('not an array'));
    }

    public function test_sanitize_channels_null_returns_empty(): void
    {
        $this->assertSame([], AdminPage::sanitize_channels(null));
    }

    public function test_sanitize_channels_empty_array(): void
    {
        $this->assertSame([], AdminPage::sanitize_channels([]));
    }

    public function test_sanitize_channels_sanitizes_slug(): void
    {
        $input = [
            ['slug' => 'TV-1 Test!', 'label' => 'Test'],
        ];

        $result = AdminPage::sanitize_channels($input);

        $this->assertCount(1, $result);
        // sanitize_key lowercases and strips special chars
        $this->assertSame('tv-1test', $result[0]['slug']);
    }

    public function test_sanitize_channels_strips_html_from_label(): void
    {
        $input = [
            ['slug' => 'tv1', 'label' => '<b>TV 1</b>'],
        ];

        $result = AdminPage::sanitize_channels($input);

        $this->assertSame('TV 1', $result[0]['label']);
    }

    public function test_sanitize_channels_handles_missing_keys(): void
    {
        $input = [
            ['slug' => 'tv1'],
            ['label' => 'No slug'],
            [],
        ];

        $result = AdminPage::sanitize_channels($input);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Helpers::extract_scheduling_fields() — shared by loop and campaigns saves
    // =========================================================================

    public function test_extract_scheduling_fields_with_dates(): void
    {
        $raw = [
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-30',
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        $this->assertSame('2026-04-01', $result['date_start']);
        $this->assertSame('2026-04-30', $result['date_end']);
    }

    public function test_extract_scheduling_fields_omits_empty_dates(): void
    {
        $raw = [
            'date_start' => '',
            'date_end' => '',
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        $this->assertArrayNotHasKey('date_start', $result);
        $this->assertArrayNotHasKey('date_end', $result);
    }

    public function test_extract_scheduling_fields_omits_invalid_dates(): void
    {
        $result = Helpers::extract_scheduling_fields([
            'date_start' => '2026-02-31',
            'date_end' => 'not-a-date',
            'days' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        $this->assertArrayNotHasKey('date_start', $result);
        $this->assertArrayNotHasKey('date_end', $result);
    }

    public function test_extract_scheduling_fields_with_days(): void
    {
        $raw = [
            'days' => ['1', '3', '5'],
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        $this->assertSame(['1', '3', '5'], $result['days']);
    }

    public function test_extract_scheduling_fields_omits_all_seven_days(): void
    {
        $raw = [
            'days' => ['1', '2', '3', '4', '5', '6', '7'],
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        // All 7 days = no restriction, should not be saved
        $this->assertArrayNotHasKey('days', $result);
    }

    public function test_extract_scheduling_fields_filters_invalid_days(): void
    {
        $raw = [
            'days' => ['1', '8', 'abc', '5'],
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        $this->assertSame(['1', '5'], $result['days']);
    }

    public function test_extract_scheduling_fields_deduplicates_days(): void
    {
        $result = Helpers::extract_scheduling_fields(['days' => ['1', '1', '2']]);

        $this->assertSame(['1', '2'], $result['days']);
    }

    public function test_extract_scheduling_fields_empty_input(): void
    {
        $result = Helpers::extract_scheduling_fields([]);

        $this->assertArrayNotHasKey('date_start', $result);
        $this->assertArrayNotHasKey('date_end', $result);
        $this->assertSame([], $result['days']);
    }

    public function test_render_days_row_checks_all_days_for_absent_restriction(): void
    {
        $this->assertSame(7, substr_count($this->renderDaysRow(null), 'checked="checked"'));
    }

    public function test_render_days_row_leaves_all_days_unchecked_for_empty_selection(): void
    {
        $this->assertStringNotContainsString('checked="checked"', $this->renderDaysRow([]));
    }

    /** @param list<string>|null $days */
    private function renderDaysRow(?array $days): string
    {
        Functions\when('esc_attr')->alias(fn ($value) => $value);
        Functions\when('esc_html')->alias(fn ($value) => $value);
        Functions\when('checked')->alias(function ($checked, $current = true, $echo = true) {
            $result = $checked === $current ? 'checked="checked"' : '';
            if ($echo) {
                echo $result;
            }
            return $result;
        });

        ob_start();
        try {
            AdminPage::render_days_row('days[]', $days);
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
