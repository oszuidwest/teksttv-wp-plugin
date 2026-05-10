<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\ImageLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class ImageLoopBlockTest extends TestCase
{
    public function test_save_with_id(): void
    {
        $result = ImageLoopBlock::save(['image_id' => '42']);
        $this->assertSame(42, $result['image_id']);
    }

    public function test_save_with_duration(): void
    {
        $result = ImageLoopBlock::save(['image_id' => '42', 'duration' => '10']);
        $this->assertSame(10, $result['duration']);
    }

    public function test_save_omits_empty_duration(): void
    {
        $result = ImageLoopBlock::save(['image_id' => '42', 'duration' => '']);
        $this->assertArrayNotHasKey('duration', $result);
    }

    public function test_save_defaults_to_zero(): void
    {
        $result = ImageLoopBlock::save([]);
        $this->assertSame(0, $result['image_id']);
    }

    public function test_build_returns_empty_when_not_scheduled(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'image_id' => 123,
            'date_start' => '2026-05-01',
            'date_end' => '2026-05-31',
        ];

        $this->assertSame([], ImageLoopBlock::build($block));
    }

    public function test_build_returns_empty_when_no_image_id(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertSame([], ImageLoopBlock::build(['image_id' => 0]));
    }

    public function test_build_returns_slide_with_custom_duration(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->with(42, 'large')->andReturn('https://example.com/image.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('');
        Functions\expect('apply_filters')->with('teksttv_image_attribution', '', 42)->andReturn('');

        $block = ['image_id' => 42, 'duration' => 10];
        $result = ImageLoopBlock::build($block);

        $this->assertCount(1, $result);
        $this->assertSame('image', $result[0]['type']);
        $this->assertSame(10000, $result[0]['duration']);
        $this->assertSame('https://example.com/image.jpg', $result[0]['url']);
    }

    public function test_build_uses_default_duration(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/image.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')->andReturn('');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $block = ['image_id' => 42];
        $result = ImageLoopBlock::build($block);

        $this->assertSame(7000, $result[0]['duration']);
    }

    public function test_build_returns_empty_when_attachment_not_found(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn(false);

        $block = ['image_id' => 999];
        $this->assertSame([], ImageLoopBlock::build($block));
    }

    public function test_build_includes_caption(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('Een mooie foto');
        Functions\expect('apply_filters')->andReturn('');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = ImageLoopBlock::build(['image_id' => 42]);

        $this->assertSame('Een mooie foto', $result[0]['caption']);
    }

    public function test_build_includes_attribution(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 42)
            ->andReturn('Foto: Jan Jansen');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = ImageLoopBlock::build(['image_id' => 42]);

        $this->assertSame('Foto: Jan Jansen', $result[0]['attribution']);
        $this->assertArrayNotHasKey('caption', $result[0]);
    }

    public function test_build_includes_both_caption_and_attribution(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('Zonsondergang');
        Functions\expect('apply_filters')->andReturn('Foto: ANP');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = ImageLoopBlock::build(['image_id' => 42]);

        $this->assertSame('Zonsondergang', $result[0]['caption']);
        $this->assertSame('Foto: ANP', $result[0]['attribution']);
    }
}
