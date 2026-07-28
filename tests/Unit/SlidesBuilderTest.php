<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\BlockRegistry;
use TekstTV\SlidesBuilder;

/**
 * SlidesBuilder delegates loop/ticker assembly to BlockRegistry; block behaviour is tested under Tests\Unit\Blocks\*.
 */
class SlidesBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionProperty(BlockRegistry::class, 'types');
        $ref->setValue(null, []);
    }

    public function test_build_returns_empty_for_empty_config(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([]);

        $result = SlidesBuilder::build('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_delegates_to_block_registry(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([
                ['type' => 'image', 'image_id' => 42],
                ['type' => 'image', 'image_id' => 43],
            ]);

        BlockRegistry::register('image', [
            'label' => 'Test Image',
            'context' => 'loop',
            'build' => function (array $block, string $channel) {
                return [['type' => 'image', 'id' => $block['image_id']]];
            },
        ]);

        $result = SlidesBuilder::build('tv1');

        $this->assertCount(2, $result);
        $this->assertSame(42, $result[0]['id']);
        $this->assertSame(43, $result[1]['id']);
    }

    public function test_build_filters_null_slides(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([
                ['type' => 'test_null'],
            ]);

        BlockRegistry::register('test_null', [
            'label' => 'Null',
            'context' => 'loop',
            'build' => function () {
                return [null, null];
            },
        ]);

        $result = SlidesBuilder::build('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_skips_unregistered_types(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([
                ['type' => 'nonexistent_type'],
            ]);

        $result = SlidesBuilder::build('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_skips_unscheduled_blocks(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_loop_tv1', [])
            ->andReturn([
                ['type' => 'image', 'image_id' => 42, 'date_start' => '2026-05-01'],
            ]);

        BlockRegistry::register('image', [
            'label' => 'Test Image',
            'context' => 'loop',
            'build' => function (array $block) {
                return [['type' => 'image', 'id' => $block['image_id']]];
            },
        ]);

        $result = SlidesBuilder::build('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_ticker_returns_empty_for_empty_config(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ticker_tv1', [])
            ->andReturn([]);

        $result = SlidesBuilder::build_ticker('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_ticker_delegates_to_block_registry(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_ticker_tv1', [])
            ->andReturn([
                ['type' => 'ticker_text', 'message' => 'Breaking news'],
            ]);

        BlockRegistry::register('ticker_text', [
            'label' => 'Tekst',
            'context' => 'ticker',
            'build' => function (array $item) {
                return [['message' => $item['message']]];
            },
        ]);

        $result = SlidesBuilder::build_ticker('tv1');

        $this->assertCount(1, $result);
        $this->assertSame('Breaking news', $result[0]['message']);
    }

    public function test_build_ticker_skips_unscheduled_items(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('get_option')
            ->with('teksttv_ticker_tv1', [])
            ->andReturn([
                ['type' => 'ticker_text', 'message' => 'Future', 'date_start' => '2026-05-01'],
            ]);

        BlockRegistry::register('ticker_text', [
            'label' => 'Tekst',
            'context' => 'ticker',
            'build' => function (array $item) {
                return [['message' => $item['message']]];
            },
        ]);

        $result = SlidesBuilder::build_ticker('tv1');
        $this->assertSame([], $result);
    }

    public function test_build_ticker_sanitizes_channel_slug(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ticker_test', [])
            ->andReturn([]);

        SlidesBuilder::build_ticker('Test');
        $this->assertTrue(true);
    }
}
