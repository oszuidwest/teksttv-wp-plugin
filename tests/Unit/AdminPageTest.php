<?php

namespace TekstTV\Tests\Unit;

use TekstTV\AdminPage;

class AdminPageTest extends TestCase
{
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
    // extract_scheduling_fields() — private, via reflection
    // =========================================================================

    public function test_extract_scheduling_fields_with_dates(): void
    {
        $raw = [
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-30',
        ];

        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [$raw]);

        $this->assertSame('2026-04-01', $result['date_start']);
        $this->assertSame('2026-04-30', $result['date_end']);
    }

    public function test_extract_scheduling_fields_omits_empty_dates(): void
    {
        $raw = [
            'date_start' => '',
            'date_end' => '',
        ];

        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [$raw]);

        $this->assertArrayNotHasKey('date_start', $result);
        $this->assertArrayNotHasKey('date_end', $result);
    }

    public function test_extract_scheduling_fields_with_days(): void
    {
        $raw = [
            'days' => ['1', '3', '5'],
        ];

        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [$raw]);

        $this->assertSame(['1', '3', '5'], $result['days']);
    }

    public function test_extract_scheduling_fields_omits_all_seven_days(): void
    {
        $raw = [
            'days' => ['1', '2', '3', '4', '5', '6', '7'],
        ];

        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [$raw]);

        // All 7 days = no restriction, should not be saved
        $this->assertArrayNotHasKey('days', $result);
    }

    public function test_extract_scheduling_fields_filters_invalid_days(): void
    {
        $raw = [
            'days' => ['1', '8', 'abc', '5'],
        ];

        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [$raw]);

        $this->assertSame(['1', '5'], $result['days']);
    }

    public function test_extract_scheduling_fields_empty_input(): void
    {
        $result = self::callPrivate(AdminPage::class, 'extract_scheduling_fields', [[]]);

        // Empty days array passes is_array but has count 0 < 7, so it's included
        $this->assertArrayNotHasKey('date_start', $result);
        $this->assertArrayNotHasKey('date_end', $result);
    }
}
