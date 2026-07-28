<?php

namespace TekstTV\Tests\Unit\Blocks\Common;

use Brain\Monkey\Functions;
use TekstTV\Blocks\BuildContext;
use TekstTV\Blocks\Common\RecentPostsQuery;
use TekstTV\Tests\Unit\TestCase;

class RecentPostsQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BuildContext::reset();
    }

    protected function tearDown(): void
    {
        BuildContext::reset();
        parent::tearDown();
    }

    public function test_base_policy_args(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(30);

        $args = RecentPostsQuery::args(5, []);

        $this->assertSame('post', $args['post_type']);
        $this->assertSame(5, $args['posts_per_page']);
        $this->assertSame('publish', $args['post_status']);
        $this->assertTrue($args['no_found_rows']);
    }

    public function test_applies_date_query_when_max_post_age_positive(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(14);

        $args = RecentPostsQuery::args(5, []);

        $this->assertSame([['after' => '14 days ago']], $args['date_query']);
    }

    public function test_omits_date_query_when_max_post_age_zero(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(0);

        $args = RecentPostsQuery::args(5, []);

        $this->assertArrayNotHasKey('date_query', $args);
    }

    public function test_excludes_posts_seen_this_build_pass(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(0);
        BuildContext::mark_post_seen(11);
        BuildContext::mark_post_seen(12);

        $args = RecentPostsQuery::args(5, []);

        $this->assertSame([11, 12], $args['post__not_in']);
    }

    public function test_wires_tax_query_from_taxonomy_filters(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(0);

        $args = RecentPostsQuery::args(3, ['category' => [5, '7']]);

        $this->assertCount(1, $args['tax_query']);
        $this->assertSame('category', $args['tax_query'][0]['taxonomy']);
        $this->assertSame([5, 7], $args['tax_query'][0]['terms']);
    }

    public function test_omits_tax_query_for_empty_filters(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(0);

        $args = RecentPostsQuery::args(3, ['category' => []]);

        $this->assertArrayNotHasKey('tax_query', $args);
    }

    public function test_extra_args_survive_the_merge(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(0);
        $meta_query = [['key' => '_thumbnail_id', 'compare' => 'EXISTS']];

        $args = RecentPostsQuery::args(3, [], ['meta_query' => $meta_query]);

        $this->assertSame($meta_query, $args['meta_query']);
    }

    public function test_extra_args_cannot_override_policy_keys(): void
    {
        Functions\expect('get_option')->with('teksttv_max_post_age', 30)->andReturn(14);
        BuildContext::mark_post_seen(11);

        $args = RecentPostsQuery::args(3, [], [
            'post_status' => 'any',
            'post__not_in' => [],
            'date_query' => [],
            'posts_per_page' => 999,
        ]);

        $this->assertSame('publish', $args['post_status']);
        $this->assertSame([11], $args['post__not_in']);
        $this->assertSame([['after' => '14 days ago']], $args['date_query']);
        $this->assertSame(3, $args['posts_per_page']);
    }
}
