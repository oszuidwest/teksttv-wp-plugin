<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\BuiltinBlocks;

class BuiltinBlocksTest extends TestCase
{
    // =========================================================================
    // save_articles()
    // =========================================================================

    public function test_save_articles_defaults(): void
    {
        $result = BuiltinBlocks::save_articles([]);
        $this->assertSame(3, $result['count']);
        $this->assertSame([], $result['taxonomy_filters']);
    }

    public function test_save_articles_with_count(): void
    {
        $result = BuiltinBlocks::save_articles(['count' => '10']);
        $this->assertSame(10, $result['count']);
    }

    public function test_save_articles_with_durations(): void
    {
        $result = BuiltinBlocks::save_articles([
            'duration_text' => '15',
            'duration_image' => '5',
        ]);
        $this->assertSame(15, $result['duration_text']);
        $this->assertSame(5, $result['duration_image']);
    }

    public function test_save_articles_omits_empty_durations(): void
    {
        $result = BuiltinBlocks::save_articles([
            'duration_text' => '',
            'duration_image' => '',
        ]);
        $this->assertArrayNotHasKey('duration_text', $result);
        $this->assertArrayNotHasKey('duration_image', $result);
    }

    // =========================================================================
    // save_image()
    // =========================================================================

    public function test_save_image_with_id(): void
    {
        $result = BuiltinBlocks::save_image(['image_id' => '42']);
        $this->assertSame(42, $result['image_id']);
    }

    public function test_save_image_with_duration(): void
    {
        $result = BuiltinBlocks::save_image(['image_id' => '42', 'duration' => '10']);
        $this->assertSame(10, $result['duration']);
    }

    public function test_save_image_omits_empty_duration(): void
    {
        $result = BuiltinBlocks::save_image(['image_id' => '42', 'duration' => '']);
        $this->assertArrayNotHasKey('duration', $result);
    }

    // =========================================================================
    // save_weather()
    // =========================================================================

    public function test_save_weather(): void
    {
        $result = BuiltinBlocks::save_weather([
            'location' => 'Breda,NL',
            'title' => 'Het weer',
            'duration' => '15',
        ]);

        $this->assertSame('Breda,NL', $result['location']);
        $this->assertSame('Het weer', $result['title']);
        $this->assertSame(15, $result['duration']);
    }

    public function test_save_weather_sanitizes_location(): void
    {
        $result = BuiltinBlocks::save_weather([
            'location' => '<script>alert("xss")</script>Breda',
        ]);

        $this->assertStringNotContainsString('<script>', $result['location']);
    }

    // =========================================================================
    // save_commercial()
    // =========================================================================

    public function test_save_commercial_with_groups(): void
    {
        $result = BuiltinBlocks::save_commercial([
            'groups' => ['Sponsors', 'Partners'],
            'intro_image_id' => '10',
            'outro_image_id' => '20',
        ]);

        $this->assertSame(['Sponsors', 'Partners'], $result['groups']);
        $this->assertSame(10, $result['intro_image_id']);
        $this->assertSame(20, $result['outro_image_id']);
    }

    public function test_save_commercial_filters_empty_groups(): void
    {
        $result = BuiltinBlocks::save_commercial([
            'groups' => ['Sponsors', '', 'Partners'],
        ]);

        $this->assertSame(['Sponsors', 'Partners'], $result['groups']);
    }

    public function test_save_commercial_with_limit(): void
    {
        $result = BuiltinBlocks::save_commercial([
            'groups' => ['A'],
            'limit' => '5',
        ]);

        $this->assertSame(5, $result['limit']);
    }

    public function test_save_commercial_omits_empty_limit(): void
    {
        $result = BuiltinBlocks::save_commercial([
            'groups' => ['A'],
            'limit' => '',
        ]);

        $this->assertArrayNotHasKey('limit', $result);
    }

    // =========================================================================
    // save_ticker_text()
    // =========================================================================

    public function test_save_ticker_text_with_message(): void
    {
        $result = BuiltinBlocks::save_ticker_text(['message' => 'Breaking news']);
        $this->assertSame(['message' => 'Breaking news'], $result);
    }

    public function test_save_ticker_text_returns_null_for_empty(): void
    {
        $this->assertNull(BuiltinBlocks::save_ticker_text(['message' => '']));
    }

    public function test_save_ticker_text_returns_null_for_missing(): void
    {
        $this->assertNull(BuiltinBlocks::save_ticker_text([]));
    }

    // =========================================================================
    // save_ticker_headlines()
    // =========================================================================

    public function test_save_ticker_headlines_defaults(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines([]);
        $this->assertSame(5, $result['count']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    public function test_save_ticker_headlines_clamps_count(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines(['count' => '99']);
        $this->assertSame(20, $result['count']);

        $result = BuiltinBlocks::save_ticker_headlines(['count' => '0']);
        $this->assertSame(1, $result['count']);
    }

    public function test_save_ticker_headlines_with_prefix(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines(['prefix' => 'Nieuws:']);
        $this->assertSame('Nieuws:', $result['prefix']);
    }

    public function test_save_ticker_headlines_omits_empty_prefix(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines(['prefix' => '']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    // =========================================================================
    // build_ticker_text()
    // =========================================================================

    public function test_build_ticker_text_returns_message(): void
    {
        $result = BuiltinBlocks::build_ticker_text(['message' => 'Hello world'], 'tv1');
        $this->assertSame([['message' => 'Hello world']], $result);
    }

    public function test_build_ticker_text_returns_empty_for_no_message(): void
    {
        $this->assertSame([], BuiltinBlocks::build_ticker_text(['message' => ''], 'tv1'));
        $this->assertSame([], BuiltinBlocks::build_ticker_text([], 'tv1'));
    }

    // =========================================================================
    // sanitize_taxonomy_filters()
    // =========================================================================

    public function test_sanitize_taxonomy_filters_empty(): void
    {
        $result = BuiltinBlocks::sanitize_taxonomy_filters([]);
        $this->assertSame([], $result);
    }

    public function test_sanitize_taxonomy_filters_valid_input(): void
    {
        $raw = ['taxonomy_filters' => ['category' => ['1', '5']]];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        $this->assertArrayHasKey('category', $result);
        $this->assertSame([1, 5], $result['category']);
    }

    public function test_sanitize_taxonomy_filters_removes_zero_ids(): void
    {
        $raw = ['taxonomy_filters' => ['category' => ['0', '1']]];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        $this->assertSame([1], array_values($result['category']));
    }

    public function test_sanitize_taxonomy_filters_skips_empty_term_arrays(): void
    {
        $raw = ['taxonomy_filters' => ['category' => ['0', '0']]];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        $this->assertArrayNotHasKey('category', $result);
    }

    public function test_sanitize_taxonomy_filters_handles_scalar_value(): void
    {
        $raw = ['taxonomy_filters' => ['category' => '5']];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        $this->assertSame([5], $result['category']);
    }

    // =========================================================================
    // save_articles() — with taxonomy filters
    // =========================================================================

    public function test_save_articles_with_taxonomy_filters(): void
    {
        $result = BuiltinBlocks::save_articles([
            'count' => '5',
            'taxonomy_filters' => ['category' => ['1', '3']],
        ]);

        $this->assertSame(5, $result['count']);
        $this->assertArrayHasKey('category', $result['taxonomy_filters']);
        $this->assertSame([1, 3], $result['taxonomy_filters']['category']);
    }

    // =========================================================================
    // save_commercial() — empty groups array
    // =========================================================================

    public function test_save_commercial_empty_groups_defaults(): void
    {
        $result = BuiltinBlocks::save_commercial([]);

        $this->assertSame([], $result['groups']);
        $this->assertSame(0, $result['intro_image_id']);
        $this->assertSame(0, $result['outro_image_id']);
    }

    public function test_save_commercial_non_array_groups(): void
    {
        $result = BuiltinBlocks::save_commercial(['groups' => 'single']);

        $this->assertSame([], $result['groups']);
    }

    // =========================================================================
    // save_weather() — empty fields
    // =========================================================================

    public function test_save_weather_omits_empty_duration(): void
    {
        $result = BuiltinBlocks::save_weather([
            'location' => 'Breda,NL',
            'title' => 'Weer',
            'duration' => '',
        ]);

        $this->assertArrayNotHasKey('duration', $result);
    }

    public function test_save_weather_empty_fields(): void
    {
        $result = BuiltinBlocks::save_weather([]);

        $this->assertSame('', $result['location']);
        $this->assertSame('', $result['title']);
    }

    // =========================================================================
    // save_image() — defaults
    // =========================================================================

    public function test_save_image_defaults_to_zero(): void
    {
        $result = BuiltinBlocks::save_image([]);

        $this->assertSame(0, $result['image_id']);
    }

    // =========================================================================
    // save_ticker_headlines() — with taxonomy filters
    // =========================================================================

    public function test_save_ticker_headlines_with_taxonomy_filters(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines([
            'count' => '3',
            'taxonomy_filters' => ['category' => ['1']],
        ]);

        $this->assertArrayHasKey('taxonomy_filters', $result);
        $this->assertSame([1], $result['taxonomy_filters']['category']);
    }

    public function test_save_ticker_headlines_omits_empty_taxonomy_filters(): void
    {
        $result = BuiltinBlocks::save_ticker_headlines([
            'taxonomy_filters' => ['category' => ['0']],
        ]);

        $this->assertArrayNotHasKey('taxonomy_filters', $result);
    }

    // =========================================================================
    // build_ticker_text() — scheduling passthrough
    // =========================================================================

    public function test_build_ticker_text_returns_trimmed_message(): void
    {
        $result = BuiltinBlocks::build_ticker_text(['message' => '  spaced  '], 'tv1');
        $this->assertSame([['message' => '  spaced  ']], $result);
    }

    // =========================================================================
    // sanitize_taxonomy_filters() — multiple taxonomies
    // =========================================================================

    public function test_sanitize_taxonomy_filters_handles_multiple_taxonomies(): void
    {
        $raw = ['taxonomy_filters' => [
            'category' => ['1', '2'],
            'post_tag' => ['5'],
        ]];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        $this->assertCount(2, $result);
        $this->assertSame([1, 2], $result['category']);
        $this->assertSame([5], $result['post_tag']);
    }

    public function test_sanitize_taxonomy_filters_sanitizes_taxonomy_name(): void
    {
        $raw = ['taxonomy_filters' => ['Category!' => ['1']]];
        $result = BuiltinBlocks::sanitize_taxonomy_filters($raw);

        // sanitize_key strips the '!'
        $this->assertArrayHasKey('category', $result);
    }
}
