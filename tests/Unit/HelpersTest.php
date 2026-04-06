<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Helpers;

class HelpersTest extends TestCase
{
    // =========================================================================
    // is_allowed_on_day()
    // =========================================================================

    public function test_is_allowed_on_day_returns_true_for_empty_array(): void
    {
        $this->assertTrue(Helpers::is_allowed_on_day([]));
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
}
