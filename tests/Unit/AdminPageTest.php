<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AdminPage;

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
}
