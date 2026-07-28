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
        Functions\expect('current_user_can')->with('manage_teksttv')->once()->andReturn(false);

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

    public function test_sanitize_ai_prompts_rejects_privileged_fields_without_manage_capability(): void
    {
        Functions\when('sanitize_textarea_field')->alias(fn ($s) => $s);
        Functions\expect('current_user_can')->with('manage_teksttv')->once()->andReturn(false);

        $stored = [
            'region_taxonomy' => 'regio',
            'provider' => 'openai',
            'model' => 'openai/gpt-5',
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 4096,
            'system' => 'Oude system prompt',
        ];
        Functions\expect('get_option')->with('teksttv_ai_prompts', [])->andReturn($stored);

        $result = AdminPage::sanitize_ai_prompts([
            'system' => 'Nieuwe system prompt',
            'region_taxonomy' => 'verborgen-regio',
            'provider' => 'other-provider',
            'model' => 'other-provider/expensive-model',
            'temperature' => 2,
            'top_p' => 0,
            'max_tokens' => 8192,
        ]);

        $this->assertSame('Nieuwe system prompt', $result['system']);
        $this->assertSame('regio', $result['region_taxonomy']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('openai/gpt-5', $result['model']);
        $this->assertSame(0.7, $result['temperature']);
        $this->assertSame(0.9, $result['top_p']);
        $this->assertSame(4096, $result['max_tokens']);
    }

    public function test_sanitize_ai_prompts_accepts_privileged_fields_with_manage_capability(): void
    {
        Functions\when('sanitize_textarea_field')->alias(fn ($s) => $s);
        Functions\expect('current_user_can')->with('manage_teksttv')->once()->andReturn(true);
        Functions\expect('get_option')->with('teksttv_ai_prompts', [])->andReturn([]);

        $result = AdminPage::sanitize_ai_prompts([
            'region_taxonomy' => 'regio',
            'provider' => 'openai',
            'model' => 'openai/gpt-5',
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 4096,
        ]);

        $this->assertSame('regio', $result['region_taxonomy']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('openai/gpt-5', $result['model']);
        $this->assertSame(0.7, $result['temperature']);
        $this->assertSame(0.9, $result['top_p']);
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
        // sanitize_key lowercases and strips special chars.
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

    public function test_render_days_row_checks_every_day_for_absent_restriction(): void
    {
        $html = $this->renderDaysRow(null);

        $this->assertSame(7, substr_count($html, 'checked="checked"'));
        $this->assertStringContainsString('type="hidden" name="days[]" value=""', $html);
    }

    public function test_render_days_row_leaves_every_day_unchecked_for_empty_selection(): void
    {
        $this->assertStringNotContainsString('checked="checked"', $this->renderDaysRow([]));
    }

    public function test_render_scheduling_fields_keeps_explicit_empty_days_enabled(): void
    {
        $this->stubSchedulingRenderFunctions();

        ob_start();
        try {
            AdminPage::render_scheduling_fields(0, ['days' => []], 'blocks');
            $html = (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }

        $this->assertSame(1, substr_count($html, 'checked="checked"'));
        $this->assertStringNotContainsString('style="display:none;"', $html);
    }

    public function test_validate_loop_save_request_rejects_invalid_nonce(): void
    {
        $_POST = ['teksttv_loop_nonce' => 'invalid'];
        Functions\when('wp_unslash')->returnArg();
        Functions\expect('wp_verify_nonce')
            ->with('invalid', 'teksttv_save_loop')
            ->andReturn(false);
        Functions\expect('add_settings_error')
            ->with('teksttv', 'loop_nonce_failed', \Mockery::type('string'))
            ->once();

        try {
            $this->assertNull(self::callPrivate(AdminPage::class, 'validate_loop_save_request'));
        } finally {
            $_POST = [];
        }
    }

    public function test_validate_loop_save_request_rejects_missing_capability(): void
    {
        $_POST = ['teksttv_loop_nonce' => 'valid'];
        Functions\when('wp_unslash')->returnArg();
        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\expect('current_user_can')
            ->with('manage_teksttv')
            ->andReturn(false);
        Functions\expect('add_settings_error')
            ->with('teksttv', 'loop_no_permission', \Mockery::type('string'))
            ->once();

        try {
            $this->assertNull(self::callPrivate(AdminPage::class, 'validate_loop_save_request'));
        } finally {
            $_POST = [];
        }
    }

    public function test_validate_loop_save_request_rejects_unknown_channel(): void
    {
        $_POST = [
            'teksttv_loop_nonce' => 'valid',
            'teksttv_loop_channel' => 'unknown',
        ];
        Functions\when('wp_unslash')->returnArg();
        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\expect('current_user_can')->andReturn(true);
        Functions\expect('get_option')
            ->with('teksttv_channels', [])
            ->andReturn([['slug' => 'tv1', 'label' => 'TV 1']]);
        Functions\expect('add_settings_error')
            ->with('teksttv', 'loop_unknown_channel', \Mockery::type('string'))
            ->once();

        try {
            $this->assertNull(self::callPrivate(AdminPage::class, 'validate_loop_save_request'));
        } finally {
            $_POST = [];
        }
    }

    public function test_validate_loop_save_request_returns_known_channel(): void
    {
        $_POST = [
            'teksttv_loop_nonce' => 'valid',
            'teksttv_loop_channel' => 'TV1',
        ];
        Functions\when('wp_unslash')->returnArg();
        Functions\expect('wp_verify_nonce')->andReturn(true);
        Functions\expect('current_user_can')->andReturn(true);
        Functions\expect('get_option')
            ->with('teksttv_channels', [])
            ->andReturn([['slug' => 'tv1', 'label' => 'TV 1']]);

        try {
            $this->assertSame('tv1', self::callPrivate(AdminPage::class, 'validate_loop_save_request'));
        } finally {
            $_POST = [];
        }
    }

    /**
     * @param list<string>|null $days
     */
    private function renderDaysRow(?array $days): string
    {
        $this->stubSchedulingRenderFunctions();

        ob_start();
        try {
            AdminPage::render_days_row('days[]', $days);
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    private function stubSchedulingRenderFunctions(): void
    {
        Functions\when('esc_attr')->alias(fn ($value) => $value);
        Functions\when('esc_html')->alias(fn ($value) => $value);
        Functions\when('esc_html_e')->alias(function ($value): void {
            echo $value;
        });
        Functions\when('checked')->alias(function ($checked, $current = true, $echo = true) {
            $result = $checked === $current ? 'checked="checked"' : '';
            if ($echo) {
                echo $result;
            }
            return $result;
        });
    }
}
