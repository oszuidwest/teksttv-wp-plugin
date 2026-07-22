<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Helpers;

class HelpersTest extends TestCase
{
    // =========================================================================
    // clamp_int()
    // =========================================================================

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
        // absint() takes the absolute value first, then the range is applied.
        $this->assertSame(120, Helpers::clamp_int('-9999', 1, 120));
    }

    // =========================================================================
    // duration_ms()
    // =========================================================================

    public function test_duration_ms_clamps_legacy_override(): void
    {
        $this->assertSame(120000, Helpers::duration_ms('9999', 'unused_option', 20));
    }

    public function test_duration_ms_clamps_legacy_option(): void
    {
        Functions\expect('get_option')->with('duration_option', 20)->andReturn(0);

        $this->assertSame(1000, Helpers::duration_ms(null, 'duration_option', 20));
    }

    public function test_duration_ms_clamps_direct_default(): void
    {
        $this->assertSame(120000, Helpers::duration_ms(null, '', 9999));
    }

    // =========================================================================
    // is_allowed_on_day()
    // =========================================================================

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
        // Wednesday = N=3
        $wednesday = new \DateTimeImmutable('2026-04-08'); // a Wednesday
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
        // Mock WP's current_datetime to return a known Monday
        $monday = new \DateTimeImmutable('2026-04-06');
        Functions\expect('current_datetime')->once()->andReturn($monday);

        $this->assertTrue(Helpers::is_allowed_on_day(['1']));
    }

    public function test_is_allowed_on_day_handles_integer_day_values(): void
    {
        $monday = new \DateTimeImmutable('2026-04-06');
        // Even if array contains ints, they should be cast to string
        $this->assertTrue(Helpers::is_allowed_on_day([1, 2], $monday));
    }

    // =========================================================================
    // sanitize_date_input()
    // =========================================================================

    public function test_sanitize_date_input_accepts_strict_calendar_date(): void
    {
        $this->assertSame('2026-07-23', Helpers::sanitize_date_input('2026-07-23'));
    }

    public function test_sanitize_date_input_rejects_invalid_calendar_date(): void
    {
        $this->assertSame('', Helpers::sanitize_date_input('2026-02-31'));
        $this->assertSame('', Helpers::sanitize_date_input('not-a-date'));
    }

    // =========================================================================
    // is_within_date_range()
    // =========================================================================

    public function test_is_within_date_range_returns_true_for_empty_strings(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_within_date_range('', ''));
    }

    public function test_is_within_date_range_returns_true_for_nulls(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_within_date_range(null, null));
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

    public function test_is_within_date_range_open_start(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tz = new \DateTimeZone('Europe/Amsterdam');
        Functions\expect('current_datetime')->once()->andReturn($now);
        Functions\expect('wp_timezone')->once()->andReturn($tz);

        $this->assertTrue(Helpers::is_within_date_range('', '2026-12-31'));
    }

    // =========================================================================
    // is_block_scheduled()
    // =========================================================================

    public function test_is_block_scheduled_returns_true_for_block_without_scheduling(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_block_scheduled(['type' => 'image']));
    }

    public function test_is_block_scheduled_returns_false_when_outside_date_range(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'date_start' => '2026-05-01',
            'date_end' => '2026-05-31',
        ];

        $this->assertFalse(Helpers::is_block_scheduled($block));
    }

    public function test_is_block_scheduled_returns_false_when_wrong_day(): void
    {
        // 2026-04-07 is a Tuesday (N=2)
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');
        Functions\expect('current_datetime')->andReturn($now);
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'days' => ['1', '3', '5'], // Mon, Wed, Fri
        ];

        $this->assertFalse(Helpers::is_block_scheduled($block));
    }

    public function test_is_block_scheduled_returns_false_for_explicit_empty_days(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertFalse(Helpers::is_block_scheduled(['days' => []]));
    }

    public function test_is_block_scheduled_returns_true_for_null_days(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertTrue(Helpers::is_block_scheduled(['days' => null]));
    }

    public function test_is_block_scheduled_returns_true_when_in_range_and_correct_day(): void
    {
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');
        Functions\expect('current_datetime')->andReturn($now);
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-30',
            'days' => ['2'], // Tuesday
        ];

        $this->assertTrue(Helpers::is_block_scheduled($block));
    }

    // =========================================================================
    // build_tax_query()
    // =========================================================================

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

    // =========================================================================
    // count_words()
    // =========================================================================

    public function test_count_words_regular_text(): void
    {
        $this->assertSame(5, Helpers::count_words('Dit is een test zin'));
    }

    public function test_count_words_empty_string(): void
    {
        $this->assertSame(0, Helpers::count_words(''));
    }

    public function test_count_words_only_whitespace(): void
    {
        $this->assertSame(0, Helpers::count_words("   \n\t  "));
    }

    public function test_count_words_dutch_text_with_special_chars(): void
    {
        $this->assertSame(4, Helpers::count_words('café résumé über straße'));
    }

    public function test_count_words_text_with_multiple_spaces(): void
    {
        $this->assertSame(3, Helpers::count_words("een   twee\n\nDrie"));
    }

    public function test_count_words_single_word(): void
    {
        $this->assertSame(1, Helpers::count_words('woord'));
    }

    // =========================================================================
    // get_date_end_meta_query()
    // =========================================================================

    public function test_get_date_end_meta_query_structure(): void
    {
        $now = new \DateTimeImmutable('2026-04-07');
        Functions\expect('current_datetime')->once()->andReturn($now);

        $result = Helpers::get_date_end_meta_query();

        $this->assertSame('OR', $result['relation']);
        $this->assertCount(3, array_filter($result, 'is_array'));

        // Check the date comparison uses today's date
        $date_clause = $result[2];
        $this->assertSame('_teksttv_date_end', $date_clause['key']);
        $this->assertSame('2026-04-07', $date_clause['value']);
        $this->assertSame('>=', $date_clause['compare']);
        $this->assertSame('DATE', $date_clause['type']);
    }

    // =========================================================================
    // get_active_campaigns()
    // =========================================================================

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
        // 2026-04-07 is a Tuesday (ISO day 2).
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

    public function test_get_active_campaigns_includes_campaigns_without_dates(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                ['channels' => ['tv1'], 'name' => 'No dates'],
            ]);

        $result = Helpers::get_active_campaigns('tv1');
        $this->assertCount(1, $result);
    }

    public function test_get_active_campaigns_returns_empty_for_unknown_channel(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                ['channels' => ['tv1'], 'name' => 'A'],
            ]);

        $result = Helpers::get_active_campaigns('tv99');
        $this->assertEmpty($result);
    }

    // =========================================================================
    // get_image_data()
    // =========================================================================

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

    public function test_get_image_data_includes_caption(): void
    {
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/img.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('Een foto');
        Functions\expect('apply_filters')->andReturn('');

        $result = Helpers::get_image_data(42);

        $this->assertSame('Een foto', $result['caption']);
    }

    public function test_get_image_data_includes_attribution(): void
    {
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/img.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 42)
            ->andReturn('Foto: ANP');

        $result = Helpers::get_image_data(42);

        $this->assertSame('Foto: ANP', $result['attribution']);
        $this->assertArrayNotHasKey('caption', $result);
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

    // =========================================================================
    // get_channels()
    // =========================================================================

    public function test_get_channels_returns_configured_channels(): void
    {
        $channels = [
            ['slug' => 'tv1', 'label' => 'TV 1'],
            ['slug' => 'tv2', 'label' => 'TV 2'],
        ];
        Functions\expect('get_option')
            ->with('teksttv_channels', [])
            ->andReturn($channels);

        $this->assertSame($channels, Helpers::get_channels());
    }

    public function test_get_channels_returns_default_when_empty(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_channels', [])
            ->andReturn([]);

        $result = Helpers::get_channels();

        $this->assertCount(1, $result);
        $this->assertSame('tv1', $result[0]['slug']);
        $this->assertSame('TV 1', $result[0]['label']);
    }

    // =========================================================================
    // has_feature()
    // =========================================================================

    public function test_has_feature_returns_true_when_enabled(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['bold', 'italic', 'ai_generate']);

        $this->assertTrue(Helpers::has_feature('ai_generate'));
    }

    public function test_has_feature_returns_false_when_disabled(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['bold', 'italic']);

        $this->assertFalse(Helpers::has_feature('ai_generate'));
    }

    // =========================================================================
    // get_ai_prompts()
    // =========================================================================

    public function test_get_ai_prompts_returns_defaults_when_empty(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame(100, $result['word_limit']);
        $this->assertSame(40, $result['title_char_limit']);
        $this->assertSame(50, $result['min_input_words']);
        $this->assertSame(3, $result['max_retries']);
        $this->assertSame(10, $result['rate_limit']);
        $this->assertSame(2048, $result['max_tokens']);
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
                'max_retries' => 5,
                'temperature' => 0.7,
                'model' => 'anthropic/claude-3',
            ]);

        $result = Helpers::get_ai_prompts();

        $this->assertSame('Custom system prompt', $result['system']);
        $this->assertSame(200, $result['word_limit']);
        $this->assertSame(50, $result['title_char_limit']);
        $this->assertSame(5, $result['max_retries']);
        $this->assertSame(0.7, $result['temperature']);
        $this->assertSame('anthropic/claude-3', $result['model']);
    }

    public function test_get_ai_prompts_clamps_max_retries(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['max_retries' => 99]);

        $result = Helpers::get_ai_prompts();
        $this->assertSame(5, $result['max_retries']);
    }

    public function test_get_ai_prompts_clamps_rate_limit(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['rate_limit' => 999]);

        $result = Helpers::get_ai_prompts();
        $this->assertSame(60, $result['rate_limit']);
    }

    // =========================================================================
    // get_campaign_groups()
    // =========================================================================

    public function test_get_campaign_groups_returns_id_label_pairs(): void
    {
        $stored = [
            ['id' => 'grp_aaa', 'label' => 'Sponsors'],
            ['id' => 'grp_bbb', 'label' => 'Partners'],
        ];
        Functions\expect('get_option')
            ->with('teksttv_campaign_groups', [])
            ->andReturn($stored);

        $this->assertSame($stored, Helpers::get_campaign_groups());
    }

    public function test_get_campaign_groups_skips_malformed_entries(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaign_groups', [])
            ->andReturn([
                ['id' => 'grp_aaa', 'label' => 'Sponsors'],
                ['id' => '', 'label' => 'Geen id'],
                ['id' => 'grp_ccc', 'label' => ''],
                'legacy-string',
            ]);

        $this->assertSame(
            [['id' => 'grp_aaa', 'label' => 'Sponsors']],
            Helpers::get_campaign_groups()
        );
    }

    public function test_campaign_group_id_is_stable_for_label(): void
    {
        $this->assertSame(
            Helpers::campaign_group_id('Sponsors'),
            Helpers::campaign_group_id('Sponsors')
        );
        $this->assertNotSame(
            Helpers::campaign_group_id('Sponsors'),
            Helpers::campaign_group_id('Partners')
        );
    }

    public function test_get_campaign_groups_returns_empty_when_not_set(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaign_groups', [])
            ->andReturn([]);

        $this->assertSame([], Helpers::get_campaign_groups());
    }

    public function test_get_campaign_groups_returns_empty_for_non_array(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_campaign_groups', [])
            ->andReturn(false);

        $this->assertSame([], Helpers::get_campaign_groups());
    }

    // =========================================================================
    // get_loop_config()
    // =========================================================================

    public function test_get_loop_config_returns_option_value(): void
    {
        $config = [['type' => 'articles', 'count' => 5]];

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn($config);

        $this->assertSame($config, Helpers::get_loop_config('tv1'));
    }

    public function test_get_loop_config_sanitizes_slug(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_loop_test-channel', [])
            ->andReturn([]);

        $result = Helpers::get_loop_config('Test-Channel');
        $this->assertSame([], $result);
    }

    // =========================================================================
    // get_preview_url()
    // =========================================================================

    public function test_get_preview_url_returns_option(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_preview_url', '')
            ->andReturn('https://preview.example.com');

        $this->assertSame('https://preview.example.com', Helpers::get_preview_url());
    }

    public function test_get_preview_url_returns_empty_when_not_set(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_preview_url', '')
            ->andReturn('');

        $this->assertSame('', Helpers::get_preview_url());
    }

    // =========================================================================
    // is_within_date_range() — edge case: invalid date format
    // =========================================================================

    public function test_is_within_date_range_ignores_invalid_start_format(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        // Invalid format should not block
        $this->assertTrue(Helpers::is_within_date_range('not-a-date', ''));
    }
}
