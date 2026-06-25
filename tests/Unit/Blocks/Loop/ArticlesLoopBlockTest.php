<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\ArticlesLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class ArticlesLoopBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \WP_Query::reset();
    }

    public function test_save_defaults(): void
    {
        $result = ArticlesLoopBlock::save([]);
        $this->assertSame(3, $result['count']);
        $this->assertSame([], $result['taxonomy_filters']);
    }

    public function test_save_with_count(): void
    {
        $result = ArticlesLoopBlock::save(['count' => '10']);
        $this->assertSame(10, $result['count']);
    }

    public function test_save_with_durations(): void
    {
        $result = ArticlesLoopBlock::save([
            'duration_text' => '15',
            'duration_image' => '5',
        ]);
        $this->assertSame(15, $result['duration_text']);
        $this->assertSame(5, $result['duration_image']);
    }

    public function test_save_omits_empty_durations(): void
    {
        $result = ArticlesLoopBlock::save([
            'duration_text' => '',
            'duration_image' => '',
        ]);
        $this->assertArrayNotHasKey('duration_text', $result);
        $this->assertArrayNotHasKey('duration_image', $result);
    }

    public function test_save_with_taxonomy_filters(): void
    {
        $result = ArticlesLoopBlock::save([
            'count' => '5',
            'taxonomy_filters' => ['category' => ['1', '3']],
        ]);

        $this->assertSame(5, $result['count']);
        $this->assertArrayHasKey('category', $result['taxonomy_filters']);
        $this->assertSame([1, 3], $result['taxonomy_filters']['category']);
    }

    public function test_split_pages_single_page(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = ArticlesLoopBlock::split_pages('<p>Hello world</p>');
        $this->assertSame(['<p>Hello world</p>'], $result);
    }

    public function test_split_pages_with_html_separator(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = ArticlesLoopBlock::split_pages('<p>Page one</p><p>---</p><p>Page two</p>');
        $this->assertCount(2, $result);
        $this->assertSame('<p>Page one</p>', $result[0]);
        $this->assertSame('<p>Page two</p>', $result[1]);
    }

    public function test_split_pages_with_multiple_dashes(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = ArticlesLoopBlock::split_pages('<p>One</p><p>-----</p><p>Two</p>');
        $this->assertCount(2, $result);
    }

    public function test_split_pages_filters_empty_parts(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = ArticlesLoopBlock::split_pages('<p>---</p><p>Only page</p><p>---</p>');
        $this->assertCount(1, $result);
        $this->assertSame('<p>Only page</p>', $result[0]);
    }

    public function test_split_pages_without_feature_returns_single_page(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn([]);

        $content = '<p>Page one</p><p>---</p><p>Page two</p>';
        $result = ArticlesLoopBlock::split_pages($content);
        $this->assertCount(1, $result);
        $this->assertSame($content, $result[0]);
    }

    public function test_split_pages_empty_content(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $this->assertSame([], ArticlesLoopBlock::split_pages(''));
    }

    public function test_split_pages_whitespace_only(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $this->assertSame([], ArticlesLoopBlock::split_pages('   '));
    }

    public function test_split_pages_with_plain_text_separator(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = ArticlesLoopBlock::split_pages("Page one\n---\nPage two");
        $this->assertCount(2, $result);
        $this->assertSame('Page one', $result[0]);
        $this->assertSame('Page two', $result[1]);
    }

    public function test_sidebar_image_override_with_explicit_none(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('0');

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertNull($result);
    }

    public function test_sidebar_image_override_with_integer_zero(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn(0);

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertNull($result);
    }

    public function test_sidebar_image_custom_override(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('42');
        Functions\expect('wp_get_attachment_image_url')->with(42, 'large')->andReturn('https://example.com/custom.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/custom.jpg', $result['url']);
    }

    public function test_sidebar_image_falls_back_to_category_image(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('wp_get_post_categories')->with(1)->andReturn([10, 20]);
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('55');
        Functions\expect('wp_get_attachment_image_url')->with(55, 'large')->andReturn('https://example.com/cat.jpg');
        Functions\expect('wp_get_attachment_caption')->with(55)->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/cat.jpg', $result['url']);
    }

    public function test_sidebar_image_falls_back_to_post_thumbnail(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('wp_get_post_categories')->with(1)->andReturn([10]);
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('');

        Functions\expect('get_post_thumbnail_id')->with(1)->andReturn(77);
        Functions\expect('wp_get_attachment_image_url')->with(77, 'large')->andReturn('https://example.com/thumb.jpg');
        Functions\expect('wp_get_attachment_caption')->with(77)->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/thumb.jpg', $result['url']);
    }

    public function test_sidebar_image_returns_null_when_nothing_found(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 1)
            ->andReturn('');

        Functions\expect('wp_get_post_categories')->with(1)->andReturn([]);
        Functions\expect('get_post_thumbnail_id')->with(1)->andReturn(0);

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertNull($result);
    }

    public function test_sidebar_image_primary_category_takes_precedence(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('88');
        Functions\expect('wp_get_attachment_image_url')->with(88, 'large')->andReturn('https://example.com/primary.jpg');
        Functions\expect('wp_get_attachment_caption')->with(88)->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return match ($tag) {
                'teksttv_primary_category' => 10,
                'teksttv_image_url' => $value,
                default => '',
            };
        });

        $result = ArticlesLoopBlock::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/primary.jpg', $result['url']);
    }

    /**
     * @param list<object> $posts
     * @param array<string, mixed> $metaMap
     */
    private function setupArticleSlides(array $posts, array $metaMap = []): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        \WP_Query::$stubPosts = $posts;

        $postIndex = -1;
        Functions\when('get_the_ID')->alias(function () use ($posts, &$postIndex) {
            $postIndex++;
            return $posts[$postIndex]->ID ?? 0;
        });

        Functions\when('get_post_meta')->alias(function (int $post_id, string $key, bool $single) use ($metaMap) {
            return $metaMap[$post_id . ':' . $key] ?? '';
        });

        Functions\when('get_option')->alias(function (string $name, $default = false) {
            return match ($name) {
                'teksttv_max_post_age' => 30,
                'teksttv_duration_text' => 20,
                'teksttv_duration_image' => 7,
                'teksttv_features' => ['page_separator'],
                default => $default,
            };
        });

        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\when('wp_reset_postdata')->justReturn(true);
    }

    public function test_build_returns_empty_when_not_scheduled(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['date_start' => '2026-05-01'];
        $this->assertSame([], ArticlesLoopBlock::build($block));
    }

    public function test_build_returns_empty_when_no_posts(): void
    {
        $this->setupArticleSlides([]);

        $block = ['count' => 3];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_creates_text_slides_from_content(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '',
            '10:_teksttv_date_end' => '',
            '10:_teksttv_title' => '',
            '10:_teksttv_content' => 'Eerste alinea',
            '10:_teksttv_sidebar_image' => '',
            '10:_teksttv_images' => '',
        ]);

        Functions\expect('get_the_title')->andReturn('Post Titel');

        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 10)
            ->andReturn('');
        Functions\expect('wp_get_post_categories')->with(10)->andReturn([]);
        Functions\expect('get_post_thumbnail_id')->with(10)->andReturn(0);

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertCount(1, $result);
        $this->assertSame('text', $result[0]['type']);
        $this->assertSame('Post Titel', $result[0]['title']);
        $this->assertSame('<p>Eerste alinea</p>', $result[0]['body']);
        $this->assertSame(20000, $result[0]['duration']);
        $this->assertArrayNotHasKey('image', $result[0]);
    }

    public function test_build_uses_custom_title(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_title' => 'Aangepaste kop',
            '10:_teksttv_content' => 'Tekst',
            '10:_teksttv_sidebar_image' => '0',
        ]);

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame('Aangepaste kop', $result[0]['title']);
    }

    public function test_build_includes_sidebar_image(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_title' => '',
            '10:_teksttv_content' => 'Tekst',
            '10:_teksttv_sidebar_image' => '42',
            '10:_teksttv_images' => '',
        ]);

        Functions\expect('get_the_title')->andReturn('Titel');
        Functions\expect('wp_get_attachment_image_url')->with(42, 'large')->andReturn('https://example.com/sidebar.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertArrayHasKey('image', $result[0]);
        $this->assertSame('https://example.com/sidebar.jpg', $result[0]['image']['url']);
    }

    public function test_build_splits_content_into_multiple_pages(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_title' => '',
            '10:_teksttv_content' => '<p>Pagina een</p><p>---</p><p>Pagina twee</p>',
            '10:_teksttv_sidebar_image' => '0',
            '10:_teksttv_images' => '',
        ]);

        Functions\expect('get_the_title')->andReturn('Titel');

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame('<p><p>Pagina een</p></p>', $result[0]['body']);
        $this->assertSame('<p><p>Pagina twee</p></p>', $result[1]['body']);
    }

    public function test_build_adds_extra_images(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_title' => '',
            '10:_teksttv_content' => 'Tekst',
            '10:_teksttv_sidebar_image' => '0',
            '10:_teksttv_images' => [50, 51],
        ]);

        Functions\expect('get_the_title')->andReturn('Titel');
        Functions\expect('wp_get_attachment_image_url')
            ->andReturnUsing(fn ($id) => 'https://example.com/img-' . $id . '.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $tag === 'teksttv_image_url' ? $value : '';
        });

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertCount(3, $result);
        $this->assertSame('text', $result[0]['type']);
        $this->assertSame('image', $result[1]['type']);
        $this->assertSame('https://example.com/img-50.jpg', $result[1]['url']);
        $this->assertSame('image', $result[2]['type']);
        $this->assertSame(7000, $result[1]['duration']);
    }

    public function test_build_skips_post_restricted_by_day(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_days' => ['1', '3', '5'],
            '10:_teksttv_content' => 'Tekst',
        ]);

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_skips_post_outside_date_range(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_days' => '',
            '10:_teksttv_date_start' => '2026-05-01',
            '10:_teksttv_date_end' => '2026-05-31',
        ]);

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_uses_custom_text_duration(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_content' => 'Tekst',
            '10:_teksttv_sidebar_image' => '0',
        ]);

        Functions\expect('get_the_title')->andReturn('Titel');

        $block = ['count' => 1, 'duration_text' => 30];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame(30000, $result[0]['duration']);
    }

    public function test_build_skips_empty_content(): void
    {
        $post = (object) ['ID' => 10];
        $this->setupArticleSlides([$post], [
            '10:_teksttv_content' => '',
            '10:_teksttv_sidebar_image' => '0',
            '10:_teksttv_images' => '',
        ]);

        Functions\expect('get_the_title')->andReturn('Titel');

        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 10)
            ->andReturn('');
        Functions\expect('wp_get_post_categories')->with(10)->andReturn([]);
        Functions\expect('get_post_thumbnail_id')->with(10)->andReturn(0);

        $block = ['count' => 1];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_applies_taxonomy_filters_without_crash(): void
    {
        $this->setupArticleSlides([]);

        $block = [
            'count' => 3,
            'taxonomy_filters' => ['category' => [1, 5]],
        ];
        ArticlesLoopBlock::build($block, 'tv1');

        $this->assertTrue(true);
    }

    public function test_build_multiple_posts(): void
    {
        $post1 = (object) ['ID' => 10];
        $post2 = (object) ['ID' => 20];
        $this->setupArticleSlides([$post1, $post2], [
            '10:_teksttv_content' => 'Eerste',
            '10:_teksttv_sidebar_image' => '0',
            '10:_teksttv_images' => '',
            '20:_teksttv_content' => 'Tweede',
            '20:_teksttv_sidebar_image' => '0',
            '20:_teksttv_images' => '',
        ]);

        Functions\when('get_the_title')->alias(fn ($id = null) => 'Titel ' . $id);

        $block = ['count' => 2];
        $result = ArticlesLoopBlock::build($block, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame('<p>Eerste</p>', $result[0]['body']);
        $this->assertSame('<p>Tweede</p>', $result[1]['body']);
    }
}
