<?php

namespace TekstTV\Tests\Unit\Blocks\Common;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Common\PostSlides;
use TekstTV\Tests\Unit\TestCase;

class PostSlidesTest extends TestCase
{
    public function test_from_article_emits_text_pages_before_images(): void
    {
        Functions\when('wpautop')->alias(static fn(string $text): string => '<p>' . $text . '</p>');
        $sidebar = ['url' => 'https://example.com/sidebar.jpg'];
        $image = ['url' => 'https://example.com/extra.jpg'];

        $slides = PostSlides::from_article([
            'title' => 'Kop',
            'pages' => ['Een', 'Twee'],
            'sidebar_image' => $sidebar,
            'images' => [$image],
        ], 20000, 8000);

        $this->assertSame([
            ['type' => 'text', 'duration' => 20000, 'title' => 'Kop', 'body' => '<p>Een</p>', 'image' => $sidebar],
            ['type' => 'text', 'duration' => 20000, 'title' => 'Kop', 'body' => '<p>Twee</p>', 'image' => $sidebar],
            ['type' => 'image', 'duration' => 8000, 'url' => 'https://example.com/extra.jpg'],
        ], $slides);
    }

    public function test_from_article_omits_missing_sidebar_image(): void
    {
        Functions\when('wpautop')->returnArg();

        $slides = PostSlides::from_article([
            'title' => 'Kop',
            'pages' => ['Tekst'],
            'sidebar_image' => null,
            'images' => [],
        ], 20000, 8000);

        $this->assertSame([['type' => 'text', 'duration' => 20000, 'title' => 'Kop', 'body' => 'Tekst']], $slides);
    }
}
