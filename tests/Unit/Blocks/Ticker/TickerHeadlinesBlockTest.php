<?php

namespace TekstTV\Tests\Unit\Blocks\Ticker;

use Brain\Monkey\Functions;
use TekstTV\Blocks\BuildContext;
use TekstTV\Blocks\Ticker\TickerHeadlinesBlock;
use TekstTV\Tests\Unit\TestCase;

class TickerHeadlinesBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \WP_Query::reset();
        BuildContext::reset();
    }

    public function test_save_defaults(): void
    {
        $result = TickerHeadlinesBlock::save([]);
        $this->assertSame(5, $result['count']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    public function test_save_clamps_count(): void
    {
        $result = TickerHeadlinesBlock::save(['count' => '99']);
        $this->assertSame(20, $result['count']);

        $result = TickerHeadlinesBlock::save(['count' => '0']);
        $this->assertSame(1, $result['count']);
    }

    public function test_save_with_prefix(): void
    {
        $result = TickerHeadlinesBlock::save(['prefix' => 'Nieuws:']);
        $this->assertSame('Nieuws:', $result['prefix']);
    }

    public function test_save_omits_empty_prefix(): void
    {
        $result = TickerHeadlinesBlock::save(['prefix' => '']);
        $this->assertArrayNotHasKey('prefix', $result);
    }

    public function test_save_with_taxonomy_filters(): void
    {
        $result = TickerHeadlinesBlock::save([
            'count' => '3',
            'taxonomy_filters' => ['category' => ['1']],
        ]);

        $this->assertArrayHasKey('taxonomy_filters', $result);
        $this->assertSame([1], $result['taxonomy_filters']['category']);
    }

    public function test_save_omits_empty_taxonomy_filters(): void
    {
        $result = TickerHeadlinesBlock::save([
            'taxonomy_filters' => ['category' => ['0']],
        ]);

        $this->assertArrayNotHasKey('taxonomy_filters', $result);
    }

    /**
     * @param list<int> $postIds
     * @param array<string, mixed> $metaMap
     */
    private function setupTickerHeadlines(array $postIds, array $metaMap = []): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        \WP_Query::$stubPosts = array_map(fn ($id) => (object) ['ID' => $id], $postIds);

        Functions\when('get_option')->alias(fn (string $name, $default = false) => match ($name) {
            'teksttv_max_post_age' => 30,
            default => $default,
        });

        Functions\when('get_post_meta')->alias(function (int $post_id, string $key, bool $single) use ($metaMap) {
            return $metaMap[$post_id . ':' . $key] ?? '';
        });
    }

    public function test_build_returns_messages(): void
    {
        $this->setupTickerHeadlines([10, 20], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
            '20:_teksttv_days' => '',
            '20:_teksttv_date_start' => '',
            '20:_teksttv_date_end' => '',
        ]);

        Functions\when('get_the_title')->alias(fn ($post) => 'Titel ' . $post->ID);

        $item = ['count' => 5];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame(['message' => 'Titel 10'], $result[0]);
        $this->assertSame(['message' => 'Titel 20'], $result[1]);
    }

    public function test_build_with_prefix(): void
    {
        $this->setupTickerHeadlines([10], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
        ]);

        Functions\when('get_the_title')->justReturn('Nieuws artikel');

        $item = ['count' => 5, 'prefix' => 'Nieuws:'];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertCount(1, $result);
        $this->assertSame('Nieuws: Nieuws artikel', $result[0]['message']);
    }

    public function test_build_returns_empty_when_no_posts(): void
    {
        $this->setupTickerHeadlines([]);

        $item = ['count' => 5];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_skips_empty_titles(): void
    {
        $this->setupTickerHeadlines([10], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
        ]);

        Functions\when('get_the_title')->justReturn('');

        $item = ['count' => 5];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_includes_all_posts_regardless_of_teksttv_scheduling(): void
    {
        $this->setupTickerHeadlines([10, 20, 30], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
            '20:_teksttv_days' => ['1'],
            '30:_teksttv_days' => '',
            '30:_teksttv_date_start' => '2026-05-01',
            '30:_teksttv_date_end' => '2026-05-31',
        ]);

        Functions\when('get_the_title')->alias(fn ($post) => 'Post ' . $post->ID);

        $item = ['count' => 10];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertCount(3, $result);
        $this->assertSame('Post 10', $result[0]['message']);
        $this->assertSame('Post 20', $result[1]['message']);
        $this->assertSame('Post 30', $result[2]['message']);
    }

    public function test_build_passes_posts_with_no_day_restrictions(): void
    {
        $this->setupTickerHeadlines([10], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
        ]);

        Functions\when('get_the_title')->justReturn('Titel');

        $item = ['count' => 5];
        $result = TickerHeadlinesBlock::build($item, 'tv1');

        $this->assertCount(1, $result);
    }

    public function test_build_marks_returned_post_ids_as_seen(): void
    {
        $this->setupTickerHeadlines([10, 20]);
        Functions\when('get_the_title')->alias(fn ($post) => 'Titel ' . $post->ID);

        TickerHeadlinesBlock::build(['count' => 5], 'tv1');

        $this->assertSame([10, 20], BuildContext::get_seen_post_ids());
    }

    public function test_build_excludes_already_seen_post_ids(): void
    {
        BuildContext::mark_post_seen(10);
        BuildContext::mark_post_seen(20);

        $this->setupTickerHeadlines([10, 20, 30, 40]);
        Functions\when('get_the_title')->alias(fn ($post) => 'Titel ' . $post->ID);

        $result = TickerHeadlinesBlock::build(['count' => 5], 'tv1');

        $this->assertSame([10, 20], \WP_Query::$lastInstance->query_vars['post__not_in']);
        $this->assertCount(2, $result);
        $this->assertSame('Titel 30', $result[0]['message']);
        $this->assertSame('Titel 40', $result[1]['message']);
    }

    public function test_build_omits_post_not_in_when_nothing_seen(): void
    {
        $this->setupTickerHeadlines([10]);
        Functions\when('get_the_title')->justReturn('Titel');

        TickerHeadlinesBlock::build(['count' => 5], 'tv1');

        $this->assertArrayNotHasKey('post__not_in', \WP_Query::$lastInstance->query_vars);
    }
}
