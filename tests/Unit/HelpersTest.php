<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\Helpers;

class HelpersTest extends TestCase
{
    public function test_field_id_normalizes_repeated_form_prefix_and_field(): void
    {
        $this->assertSame('teksttv-blocks-3-duration-text', Helpers::field_id('teksttv_blocks', 3, 'duration_text'));
    }


    public function test_clamp_int_returns_value_within_range(): void
    {
        $this->assertSame(50, Helpers::clamp_int('50', 1, 120));
    }

    public function test_clamp_int_caps_at_max(): void
    {
        $this->assertSame(120, Helpers::clamp_int('9999', 1, 120));
    }

    public function test_clamp_int_raises_to_min(): void
    {
        $this->assertSame(10, Helpers::clamp_int('0', 10, 500));
    }

    public function test_clamp_int_handles_negative_via_absint(): void
    {
        // absint() runs before clamping.
        $this->assertSame(120, Helpers::clamp_int('-9999', 1, 120));
    }


    public function test_duration_ms_clamps_legacy_override(): void
    {
        $this->assertSame(120000, Helpers::duration_ms('9999', 'teksttv_duration_text'));
    }

    public function test_duration_ms_clamps_legacy_option(): void
    {
        Functions\expect('get_option')->with('teksttv_duration_text', 20)->andReturn(0);

        $this->assertSame(1000, Helpers::duration_ms(null, 'teksttv_duration_text'));
    }

    public function test_duration_ms_reads_default_from_duration_defaults(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_duration_image', Helpers::DURATION_DEFAULTS['teksttv_duration_image'])
            ->andReturnArg(1);

        $this->assertSame(7000, Helpers::duration_ms(null, 'teksttv_duration_image'));
    }

    public function test_fixed_duration_ms_clamps_direct_default(): void
    {
        $this->assertSame(120000, Helpers::fixed_duration_ms(null, 9999));
    }


    public function test_is_allowed_on_day_returns_false_for_empty_array(): void
    {
        $this->assertFalse(Helpers::is_allowed_on_day([]));
    }

    public function test_is_allowed_on_day_returns_true_for_null(): void
    {
        $this->assertTrue(Helpers::is_allowed_on_day(null));
    }

    public function test_is_allowed_on_day_returns_true_when_day_matches(): void
    {
        $wednesday = new \DateTimeImmutable('2026-04-08');
        $this->assertTrue(Helpers::is_allowed_on_day(['3'], $wednesday));
    }

    public function test_is_allowed_on_day_returns_false_when_day_does_not_match(): void
    {
        $wednesday = new \DateTimeImmutable('2026-04-08');
        $this->assertFalse(Helpers::is_allowed_on_day(['1', '5'], $wednesday));
    }

    public function test_is_allowed_on_day_handles_all_days(): void
    {
        $monday = new \DateTimeImmutable('2026-04-06');
        $this->assertTrue(Helpers::is_allowed_on_day(['1', '2', '3', '4', '5', '6', '7'], $monday));
    }

    public function test_is_allowed_on_day_uses_current_datetime_when_no_date_given(): void
    {
        $monday = new \DateTimeImmutable('2026-04-06');
        Functions\expect('current_datetime')->once()->andReturn($monday);

        $this->assertTrue(Helpers::is_allowed_on_day(['1']));
    }

    public function test_is_allowed_on_day_handles_integer_day_values(): void
    {
        $monday = new \DateTimeImmutable('2026-04-06');
        $this->assertTrue(Helpers::is_allowed_on_day([1, 2], $monday));
    }


    public function test_sanitize_date_input_accepts_strict_calendar_date(): void
    {
        $this->assertSame('2026-07-23', Helpers::sanitize_date_input('2026-07-23'));
    }

    public function test_sanitize_date_input_rejects_invalid_calendar_date(): void
    {
        $this->assertSame('', Helpers::sanitize_date_input('2026-02-31'));
        $this->assertSame('', Helpers::sanitize_date_input('not-a-date'));
    }


    public function test_is_within_date_range_returns_true_for_empty_strings(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_within_date_range('', ''));
    }

    public function test_is_within_date_range_returns_false_before_start_date(): void
    {
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');
        $tz = new \DateTimeZone('Europe/Amsterdam');
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertFalse(Helpers::is_within_date_range('2026-04-08', ''));
    }

    public function test_is_within_date_range_returns_false_after_end_date(): void
    {
        $now = new \DateTimeImmutable('2026-04-10 12:00:00');
        $tz = new \DateTimeZone('Europe/Amsterdam');
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertFalse(Helpers::is_within_date_range('', '2026-04-09'));
    }

    public function test_is_within_date_range_returns_true_within_range(): void
    {
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');
        $tz = new \DateTimeZone('Europe/Amsterdam');
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertTrue(Helpers::is_within_date_range('2026-04-01', '2026-04-30'));
    }

    public function test_is_within_date_range_returns_true_on_start_date(): void
    {
        $now = new \DateTimeImmutable('2026-04-07 08:00:00');
        $tz = new \DateTimeZone('Europe/Amsterdam');
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertTrue(Helpers::is_within_date_range('2026-04-07', ''));
    }

    public function test_is_within_date_range_returns_true_on_end_date(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $now = new \DateTimeImmutable('2026-04-07 23:00:00', $tz);
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertTrue(Helpers::is_within_date_range('', '2026-04-07'));
    }


    public function test_is_block_scheduled_returns_false_for_explicit_empty_days(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertFalse(Helpers::is_block_scheduled(['days' => []]));
    }

    public function test_is_block_scheduled_returns_true_when_in_range_and_correct_day(): void
    {
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');
        Functions\expect('current_datetime')->andReturn($now);
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-30',
            'days' => ['2'],
        ];

        $this->assertTrue(Helpers::is_block_scheduled($block));
    }


    public function test_build_tax_query_returns_empty_for_empty_filters(): void
    {
        $this->assertSame([], Helpers::build_tax_query([]));
    }

    public function test_build_tax_query_builds_correct_structure(): void
    {
        $result = Helpers::build_tax_query([
            'category' => [1, 5, 10],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('category', $result[0]['taxonomy']);
        $this->assertSame('term_id', $result[0]['field']);
        $this->assertSame([1, 5, 10], $result[0]['terms']);
    }

    public function test_build_tax_query_handles_multiple_taxonomies(): void
    {
        $result = Helpers::build_tax_query([
            'category' => [1],
            'post_tag' => [3, 4],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('category', $result[0]['taxonomy']);
        $this->assertSame('post_tag', $result[1]['taxonomy']);
    }

    public function test_build_tax_query_filters_zero_values(): void
    {
        $result = Helpers::build_tax_query([
            'category' => [0, 0],
        ]);

        $this->assertSame([], $result);
    }

    public function test_build_tax_query_casts_strings_to_int(): void
    {
        $result = Helpers::build_tax_query([
            'category' => ['5', '10'],
        ]);

        $this->assertSame([5, 10], $result[0]['terms']);
    }


    public function test_count_words_regular_text(): void
    {
        $this->assertSame(5, Helpers::count_words('Dit is een test zin'));
    }

    public function test_count_words_empty_string(): void
    {
        $this->assertSame(0, Helpers::count_words(''));
    }

    public function test_count_words_dutch_text_with_special_chars(): void
    {
        $this->assertSame(4, Helpers::count_words('café résumé über straße'));
    }

    #[DataProvider('terminalPeriodProvider')]
    public function test_ensure_terminal_period(string $input, string $expected): void
    {
        $this->assertSame($expected, Helpers::ensure_terminal_period($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function terminalPeriodProvider(): array
    {
        return [
            'empty string stays empty' => [" \t\n", ''],
            'missing punctuation gets period' => ['Het bericht stopt hier', 'Het bericht stopt hier.'],
            'text is trimmed before period is added' => ['  Het bericht stopt hier  ', 'Het bericht stopt hier.'],
            'existing period is preserved' => ['Het bericht is klaar.', 'Het bericht is klaar.'],
            'existing question mark is preserved' => ['Komt er extra toezicht?', 'Komt er extra toezicht?'],
            'existing exclamation mark is preserved' => ['De weg is weer open!', 'De weg is weer open!'],
            'existing ellipsis is preserved' => ['Het onderzoek loopt…', 'Het onderzoek loopt…'],
            'closing quote after punctuation is kept' => ['"Het besluit is genomen."', '"Het besluit is genomen."'],
            'period is added after closing bracket' => ['De weg gaat dicht (A58)', 'De weg gaat dicht (A58).'],
            'trailing comma becomes period' => ['De vergadering start morgen,', 'De vergadering start morgen.'],
            'comma before quote becomes period' => ['Hij zei "ja,"', 'Hij zei "ja."'],
            'comma after quote becomes period' => ['Hij zei "ja",', 'Hij zei "ja."'],
            'colon before quote becomes period' => ['Hij zei "ja:"', 'Hij zei "ja."'],
            'comma before bracket becomes period' => ['De weg gaat dicht (A58,)', 'De weg gaat dicht (A58).'],
            'comma after bracket becomes period' => ['De weg gaat dicht (A58),', 'De weg gaat dicht (A58).'],
            'comma between quote and bracket becomes period' => ['Hij zei ("ja,")', 'Hij zei ("ja").'],
        ];
    }

    public function test_get_date_range_meta_query_structure(): void
    {
        $now = new \DateTimeImmutable('2026-04-07');
        Functions\expect('current_datetime')->once()->andReturn($now);

        $this->assertSame([
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_teksttv_date_start', 'compare' => 'NOT EXISTS'],
                ['key' => '_teksttv_date_start', 'value' => '', 'compare' => '='],
                [
                    'key' => '_teksttv_date_start',
                    'value' => '2026-04-07',
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
            ],
            [
                'relation' => 'OR',
                ['key' => '_teksttv_date_end', 'compare' => 'NOT EXISTS'],
                ['key' => '_teksttv_date_end', 'value' => '', 'compare' => '='],
                [
                    'key' => '_teksttv_date_end',
                    'value' => '2026-04-07',
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
        ], Helpers::get_date_range_meta_query());
    }


    public function test_get_active_campaigns_filters_by_channel(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                ['channels' => ['tv1'], 'name' => 'A'],
                ['channels' => ['tv2'], 'name' => 'B'],
                ['channels' => ['tv1', 'tv2'], 'name' => 'C'],
            ]);

        $result = Helpers::get_active_campaigns('tv1');
        $names = array_column($result, 'name');

        $this->assertContains('A', $names);
        $this->assertContains('C', $names);
        $this->assertNotContains('B', $names);
    }

    public function test_get_active_campaigns_filters_by_date_range(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                ['channels' => ['tv1'], 'name' => 'Active', 'date_start' => '2026-04-01', 'date_end' => '2026-04-30'],
                ['channels' => ['tv1'], 'name' => 'Expired', 'date_start' => '2026-03-01', 'date_end' => '2026-03-31'],
                ['channels' => ['tv1'], 'name' => 'Future', 'date_start' => '2026-05-01', 'date_end' => '2026-05-31'],
            ]);

        $result = Helpers::get_active_campaigns('tv1');
        $names = array_column($result, 'name');

        $this->assertContains('Active', $names);
        $this->assertNotContains('Expired', $names);
        $this->assertNotContains('Future', $names);
    }

    public function test_get_active_campaigns_filters_by_days_of_week(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                ['channels' => ['tv1'], 'name' => 'TueOnly', 'days' => ['2']],
                ['channels' => ['tv1'], 'name' => 'WeekendOnly', 'days' => ['6', '7']],
                ['channels' => ['tv1'], 'name' => 'NoDays'],
            ]);

        $result = Helpers::get_active_campaigns('tv1');
        $names = array_column($result, 'name');

        $this->assertContains('TueOnly', $names);
        $this->assertContains('NoDays', $names);
        $this->assertNotContains('WeekendOnly', $names);
    }


    public function test_get_image_data_returns_null_when_no_url(): void
    {
        Functions\expect('wp_get_attachment_image_url')
            ->with(999, 'large')
            ->andReturn(false);

        $this->assertNull(Helpers::get_image_data(999));
    }

    public function test_get_image_data_returns_url_only_when_no_extras(): void
    {
        Functions\expect('wp_get_attachment_image_url')
            ->with(42, 'large')
            ->andReturn('https://example.com/img.jpg');
        Functions\expect('wp_get_attachment_caption')
            ->with(42)
            ->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $result = Helpers::get_image_data(42);

        $this->assertSame(['url' => 'https://example.com/img.jpg'], $result);
    }

    public function test_get_image_data_includes_both_caption_and_attribution(): void
    {
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/img.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('Zonsondergang');
        Functions\expect('apply_filters')->andReturn('Foto: ANP');

        $result = Helpers::get_image_data(42);

        $this->assertSame('Zonsondergang', $result['caption']);
        $this->assertSame('Foto: ANP', $result['attribution']);
    }

    public function test_get_image_data_uses_custom_size(): void
    {
        Functions\expect('wp_get_attachment_image_url')
            ->with(42, 'thumbnail')
            ->andReturn('https://example.com/thumb.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $result = Helpers::get_image_data(42, 'thumbnail');

        $this->assertSame('https://example.com/thumb.jpg', $result['url']);
    }


    public function test_ai_supported_returns_false_when_environment_allows_ai_but_no_provider_matches(): void
    {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            return match ($key) {
                'teksttv_features' => ['ai_generate'],
                'teksttv_ai_prompts' => [],
                default => $default,
            };
        });
        Functions\expect('wp_supports_ai')->once()->andReturn(true);
        Functions\expect('wp_ai_client_prompt')->once()->andReturn(self::mockUnsupportedAiBuilder());

        $this->assertFalse(Helpers::ai_supported());
    }

    public function test_ai_supported_memoizes_a_supported_probe_result(): void
    {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            return $key === 'teksttv_features' ? ['ai_generate'] : $default;
        });
        Functions\expect('wp_supports_ai')->once()->andReturn(true);
        Functions\expect('wp_ai_client_prompt')->once()->andReturn(self::mockAiBuilder());

        $this->assertTrue(Helpers::ai_supported());
        $this->assertTrue(Helpers::ai_supported());
    }

    public function test_ai_supported_memoizes_an_unsupported_probe_result(): void
    {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            return $key === 'teksttv_features' ? ['ai_generate'] : $default;
        });
        Functions\expect('wp_supports_ai')->once()->andReturn(true);
        Functions\expect('wp_ai_client_prompt')->once()->andReturn(self::mockUnsupportedAiBuilder());

        $this->assertFalse(Helpers::ai_supported());
        $this->assertFalse(Helpers::ai_supported());
    }

    public function test_get_ai_prompts_returns_defaults_when_empty(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame(100, $result['word_limit']);
        $this->assertSame(40, $result['title_char_limit']);
        $this->assertSame(50, $result['min_input_words']);
        $this->assertTrue($result['ensure_terminal_period']);
        $this->assertNotEmpty($result['system']);
        $this->assertNotEmpty($result['prompt_title']);
        $this->assertNotEmpty($result['prompt_body']);
    }

    public function test_get_ai_prompts_uses_saved_values(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'system' => 'Custom system prompt',
                'word_limit' => 200,
                'title_char_limit' => 50,
                'model' => 'anthropic/claude-3',
                'ensure_terminal_period' => false,
            ]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame('Custom system prompt', $result['system']);
        $this->assertSame(200, $result['word_limit']);
        $this->assertSame(50, $result['title_char_limit']);
        $this->assertSame('anthropic/claude-3', $result['model']);
        $this->assertFalse($result['ensure_terminal_period']);
    }

    public function test_get_ai_prompts_clamps_out_of_range_limits(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'word_limit' => 9999,
                'word_limit_photo' => 9999,
                'title_char_limit' => 9999,
                'min_input_words' => 9999,
            ]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame(500, $result['word_limit']);
        $this->assertSame(500, $result['word_limit_photo']);
        $this->assertSame(100, $result['title_char_limit']);
        $this->assertSame(500, $result['min_input_words']);
    }

    public function test_normalize_ai_prompt_settings_preserves_photo_inheritance_marker(): void
    {
        $limits = Helpers::normalize_ai_prompt_settings([
            'word_limit' => 250,
            'word_limit_photo' => 0,
        ]);

        $this->assertSame(250, $limits['word_limit']);
        $this->assertSame(0, $limits['word_limit_photo']);
    }

    public function test_normalize_ai_prompt_settings_clamps_positive_photo_word_limit(): void
    {
        foreach ([1 => 10, 9 => 10, 10 => 10, 500 => 500, 501 => 500] as $input => $expected) {
            $limits = Helpers::normalize_ai_prompt_settings(['word_limit_photo' => $input]);

            $this->assertSame($expected, $limits['word_limit_photo'], 'Input ' . $input);
        }
    }

    public function test_get_ai_prompts_resolves_photo_inheritance_marker(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'word_limit' => 250,
                'word_limit_photo' => 0,
            ]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame(250, $result['word_limit_photo']);
    }

    public function test_get_ai_prompts_clamps_photo_word_limit_below_minimum(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['word_limit_photo' => 1]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame(10, $result['word_limit_photo']);
    }


    public function test_get_commercial_blocks_skips_malformed_entries(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_commercial_blocks', [])
            ->andReturn([
                ['id' => 'cblock_aaa', 'label' => 'Sponsors'],
                ['id' => '', 'label' => 'Geen id'],
                ['id' => 'cblock_ccc', 'label' => ''],
                'legacy-string',
            ]);

        $this->assertSame(
            [['id' => 'cblock_aaa', 'label' => 'Sponsors']],
            Helpers::get_commercial_blocks()
        );
    }

    public function test_commercial_block_id_matches_stored_reference_format(): void
    {
        $this->assertSame(
            'grp_e881053494ad',
            Helpers::commercial_block_id('Sponsors')
        );
        $this->assertNotSame(
            Helpers::commercial_block_id('Sponsors'),
            Helpers::commercial_block_id('Partners')
        );
    }


    public function test_is_within_date_range_ignores_invalid_start_format(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_within_date_range('not-a-date', ''));
    }
}
