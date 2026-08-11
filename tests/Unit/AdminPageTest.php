<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AdminPage;
use TekstTV\Helpers;

class AdminPageTest extends TestCase
{
    public function test_register_menu_hides_content_page_without_supported_text_generator(): void
    {
        $submenu_slugs = $this->stubMenuRegistration(false);

        AdminPage::register_menu();

        $this->assertNotContains('teksttv-content', $submenu_slugs());
    }

    public function test_register_menu_shows_content_page_with_supported_text_generator(): void
    {
        $submenu_slugs = $this->stubMenuRegistration(true);

        AdminPage::register_menu();

        $this->assertContains('teksttv-content', $submenu_slugs());
    }

    public function test_register_menu_skips_content_page_and_ai_discovery_without_capability(): void
    {
        $submenu_slugs = $this->stubMenuRegistration(true, false);

        AdminPage::register_menu();

        $this->assertNotContains('teksttv-content', $submenu_slugs());
    }

    public function test_preview_url_shares_site_origin_true_for_exact_origin(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_for_different_scheme(): void
    {
        $this->stubWpParseUrl();

        $this->assertFalse(AdminPage::preview_url_shares_site_origin(
            'http://bredanu.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_for_different_port(): void
    {
        $this->stubWpParseUrl();

        $this->assertFalse(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.nl:8443/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_normalizes_effective_port(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.nl:443/preview',
            'https://bredanu.nl'
        ));
        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'http://bredanu.nl:80/preview',
            'http://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_for_separate_host(): void
    {
        $this->stubWpParseUrl();

        $this->assertFalse(AdminPage::preview_url_shares_site_origin(
            'https://bredanu.teksttv.pages.dev/bredanu/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_false_when_empty(): void
    {
        $this->stubWpParseUrl();

        $this->assertFalse(AdminPage::preview_url_shares_site_origin('', 'https://bredanu.nl'));
    }

    public function test_preview_url_shares_site_origin_false_for_invalid_url(): void
    {
        $this->stubWpParseUrl();

        $this->assertFalse(AdminPage::preview_url_shares_site_origin(
            'not a valid URL',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_ignores_host_case(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://BredaNU.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_decodes_percent_encoded_host(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://%62redanu.nl/preview',
            'https://bredanu.nl'
        ));
    }

    public function test_preview_url_shares_site_origin_normalizes_unicode_host(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://bücher.example/preview',
            'https://xn--bcher-kva.example'
        ));
    }

    public function test_preview_url_shares_site_origin_uses_nontransitional_idn_processing(): void
    {
        $this->stubWpParseUrl();

        $this->assertTrue(AdminPage::preview_url_shares_site_origin(
            'https://faß.de/preview',
            'https://xn--fa-hia.de'
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
        $this->assertArrayNotHasKey('temperature', $result);
        $this->assertArrayNotHasKey('top_p', $result);
        $this->assertArrayNotHasKey('max_tokens', $result);
    }

    public function test_sanitize_ai_prompts_rejects_privileged_fields_without_manage_capability(): void
    {
        Functions\when('sanitize_textarea_field')->alias(fn ($s) => $s);
        Functions\expect('current_user_can')->with('manage_teksttv')->once()->andReturn(false);

        $stored = [
            'region_taxonomy' => 'regio',
            'provider' => 'openai',
            'model' => 'openai/gpt-5',
            'system' => 'Oude system prompt',
        ];
        Functions\expect('get_option')->with('teksttv_ai_prompts', [])->andReturn($stored);

        $result = AdminPage::sanitize_ai_prompts([
            'system' => 'Nieuwe system prompt',
            'region_taxonomy' => 'verborgen-regio',
            'provider' => 'other-provider',
            'model' => 'other-provider/expensive-model',
        ]);

        $this->assertSame('Nieuwe system prompt', $result['system']);
        $this->assertSame('regio', $result['region_taxonomy']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('openai/gpt-5', $result['model']);
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
        ]);

        $this->assertSame('regio', $result['region_taxonomy']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('openai/gpt-5', $result['model']);
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

    public function test_sanitize_channels_non_array_returns_empty(): void
    {
        $this->assertSame([], AdminPage::sanitize_channels('not an array'));
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

    public function test_extract_scheduling_fields_with_days(): void
    {
        $raw = [
            'days' => ['1', '3', '5'],
        ];

        $result = Helpers::extract_scheduling_fields($raw);

        $this->assertSame(['1', '3', '5'], $result['days']);
    }

    public function test_extract_scheduling_fields_preserves_explicit_null_days(): void
    {
        $result = Helpers::extract_scheduling_fields(['days' => null]);

        $this->assertArrayNotHasKey('days', $result);
    }

    public function test_render_days_row_checks_all_days_for_absent_restriction(): void
    {
        $this->assertSame(7, substr_count($this->renderDaysRow(null), 'checked="checked"'));
    }

    public function test_render_days_row_leaves_all_days_unchecked_for_empty_selection(): void
    {
        $this->assertStringNotContainsString('checked="checked"', $this->renderDaysRow([]));
    }

    public function test_sanitize_registry_items_preserves_unregistered_stored_rows(): void
    {
        $stored = [
            ['type' => 'addon_weather', 'location' => 'Breda'],
        ];
        Functions\expect('get_option')->with('teksttv_loop_tv1', [])->andReturn($stored);

        [$items, $preserved] = self::callPrivate(
            AdminPage::class,
            'sanitize_registry_items',
            [[], 'teksttv_loop_tv1', 'loop']
        );

        $this->assertTrue($preserved);
        $this->assertSame($stored, $items);
    }

    public function test_sanitize_registry_items_does_not_restore_registered_removed_rows(): void
    {
        \TekstTV\BlockRegistry::register('test_registered_row', [
            'save' => static fn (array $raw): array => $raw,
        ]);
        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([['type' => 'test_registered_row', 'value' => 'removed']]);

        [$items, $preserved] = self::callPrivate(
            AdminPage::class,
            'sanitize_registry_items',
            [[], 'teksttv_loop_tv1', 'loop']
        );

        $this->assertFalse($preserved);
        $this->assertSame([], $items);
    }

    public function test_sanitize_registry_items_restores_unregistered_rows_at_stored_positions(): void
    {
        \TekstTV\BlockRegistry::register('test_order_a', [
            'context' => 'loop',
            'save' => static fn (array $raw): array => ['value' => $raw['value']],
        ]);
        \TekstTV\BlockRegistry::register('test_order_b', [
            'context' => 'loop',
            'save' => static fn (array $raw): array => ['value' => $raw['value']],
        ]);

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([
                ['type' => 'addon_first'],
                ['type' => 'test_order_a', 'value' => 'old-a'],
                ['type' => 'addon_middle'],
                ['type' => 'test_order_b', 'value' => 'old-b'],
                ['type' => 'addon_last'],
            ]);

        [$items, $preserved] = self::callPrivate(
            AdminPage::class,
            'sanitize_registry_items',
            [
                [
                    ['type' => 'test_order_a', 'value' => 'new-a'],
                    ['type' => 'test_order_b', 'value' => 'new-b'],
                ],
                'teksttv_loop_tv1',
                'loop',
            ]
        );

        $this->assertTrue($preserved);
        $this->assertSame(
            ['addon_first', 'test_order_a', 'addon_middle', 'test_order_b', 'addon_last'],
            array_column($items, 'type')
        );
        $this->assertSame('new-a', $items[1]['value']);
        $this->assertSame('new-b', $items[3]['value']);
    }

    public function test_sanitize_registry_items_rejects_registered_type_from_other_context(): void
    {
        \TekstTV\BlockRegistry::register('test_loop_context', [
            'context' => 'loop',
            'save' => static fn (array $raw): array => ['value' => $raw['value']],
        ]);
        \TekstTV\BlockRegistry::register('test_ticker_context', [
            'context' => 'ticker',
            'save' => static fn (array $raw): array => ['value' => $raw['value']],
        ]);
        Functions\expect('get_option')->with('teksttv_loop_tv1', [])->andReturn([]);

        [$items, $preserved] = self::callPrivate(
            AdminPage::class,
            'sanitize_registry_items',
            [
                [
                    ['type' => 'test_loop_context', 'value' => 'allowed'],
                    ['type' => 'test_ticker_context', 'value' => 'rejected'],
                ],
                'teksttv_loop_tv1',
                'loop',
            ]
        );

        $this->assertFalse($preserved);
        $this->assertSame(['test_loop_context'], array_column($items, 'type'));
    }

    private function stubWpParseUrl(): void
    {
        Functions\when('wp_parse_url')->alias(fn ($url, $comp = -1) => parse_url($url, $comp));
    }

    /**
     * @return callable(): list<string>
     */
    private function stubMenuRegistration(bool $ai_supported, bool $can_manage_content = true): callable
    {
        $submenu_slugs = [];
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false): mixed {
            return match ($key) {
                'teksttv_channels' => [['slug' => 'tv1', 'label' => 'TV 1']],
                'teksttv_features' => ['ai_generate'],
                default => $default,
            };
        });
        if ($can_manage_content) {
            Functions\when('current_user_can')->justReturn(true);
        } else {
            Functions\expect('current_user_can')->with('manage_teksttv_content')->once()->andReturn(false);
        }
        Functions\when('add_menu_page')->justReturn(null);
        Functions\when('add_submenu_page')->alias(
            static function (
                mixed $parent_slug,
                mixed $page_title,
                mixed $menu_title,
                mixed $capability,
                mixed $menu_slug
            ) use (&$submenu_slugs): void {
                $submenu_slugs[] = (string) $menu_slug;
            }
        );
        Functions\when('remove_submenu_page')->justReturn(null);
        if ($can_manage_content) {
            Functions\when('wp_supports_ai')->justReturn(true);
            Functions\when('wp_ai_client_prompt')->justReturn(
                $ai_supported ? self::mockAiBuilder() : self::mockUnsupportedAiBuilder()
            );
        } else {
            Functions\expect('wp_supports_ai')->never();
            Functions\expect('wp_ai_client_prompt')->never();
        }

        return static function () use (&$submenu_slugs): array {
            return $submenu_slugs;
        };
    }

    /** @param list<string>|null $days */
    private function renderDaysRow(?array $days): string
    {
        return $this->captureRender(function () use ($days): void {
            AdminPage::render_days_row('days[]', $days);
        });
    }

    /** Stub the WP escaping/checked helpers and capture the render output. */
    private function captureRender(callable $render): string
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
            $render();
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
