<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\IframeLoopBlock;
use TekstTV\Tests\Unit\TestCase;

class IframeLoopBlockTest extends TestCase
{
    private static function passThroughUrlSanitization(): void
    {
        Functions\when('esc_url_raw')->alias(static fn(string $url): string => $url);
    }

    public function test_save_sanitizes_url(): void
    {
        Functions\expect('esc_url_raw')
            ->with('https://example.com/embed', ['http', 'https'])
            ->andReturn('https://example.com/embed');

        $result = IframeLoopBlock::save(['url' => '  https://example.com/embed  ']);
        $this->assertSame('https://example.com/embed', $result['url']);
    }

    public function test_save_stores_name(): void
    {
        Functions\expect('esc_url_raw')->andReturnFirstArg();

        $result = IframeLoopBlock::save(['name' => 'Weerdashboard', 'url' => 'https://example.com']);
        $this->assertSame('Weerdashboard', $result['name']);
    }

    public function test_save_defaults_to_empty_name(): void
    {
        Functions\expect('esc_url_raw')->andReturnFirstArg();

        $result = IframeLoopBlock::save(['url' => 'https://example.com']);
        $this->assertSame('', $result['name']);
    }

    public function test_save_with_duration(): void
    {
        Functions\expect('esc_url_raw')->andReturnFirstArg();

        $result = IframeLoopBlock::save(['url' => 'https://example.com', 'duration' => '45']);
        $this->assertSame(45, $result['duration']);
    }

    public function test_save_omits_empty_duration(): void
    {
        Functions\expect('esc_url_raw')->andReturnFirstArg();

        $result = IframeLoopBlock::save(['url' => 'https://example.com', 'duration' => '']);
        $this->assertArrayNotHasKey('duration', $result);
    }

    public function test_save_defaults_to_empty_url(): void
    {
        Functions\expect('esc_url_raw')->with('', ['http', 'https'])->andReturn('');

        $result = IframeLoopBlock::save([]);
        $this->assertSame('', $result['url']);
    }

    public function test_save_accepts_http_and_https_urls(): void
    {
        self::passThroughUrlSanitization();

        $this->assertSame('http://example.com/embed', IframeLoopBlock::save([
            'url' => 'http://example.com/embed',
        ])['url']);
        $this->assertSame('https://example.com/embed', IframeLoopBlock::save([
            'url' => 'https://example.com/embed',
        ])['url']);
    }

    public function test_save_rejects_unsupported_or_invalid_urls(): void
    {
        self::passThroughUrlSanitization();

        foreach (['ftp://example.com/file', 'javascript:alert(1)', '/relative', 'https://', 'not a url'] as $url) {
            $this->assertSame('', IframeLoopBlock::save(['url' => $url])['url'], $url);
        }
    }

    public function test_build_returns_empty_when_no_url(): void
    {
        self::passThroughUrlSanitization();
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertSame([], IframeLoopBlock::build(['url' => '   ']));
    }

    public function test_build_returns_slide_with_custom_duration(): void
    {
        self::passThroughUrlSanitization();
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['name' => 'Weerdashboard', 'url' => 'https://example.com/embed', 'duration' => 45];
        $result = IframeLoopBlock::build($block);

        $this->assertCount(1, $result);
        $this->assertSame('iframe', $result[0]['type']);
        $this->assertSame(45000, $result[0]['duration']);
        $this->assertSame('https://example.com/embed', $result[0]['url']);
        // Keep admin-only names out of slide payloads.
        $this->assertArrayNotHasKey('name', $result[0]);
    }

    public function test_build_uses_default_duration(): void
    {
        self::passThroughUrlSanitization();
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')->with('teksttv_duration_iframe', 30)->andReturn(30);

        $result = IframeLoopBlock::build(['url' => 'https://example.com/embed']);

        $this->assertSame(30000, $result[0]['duration']);
    }

    public function test_build_rejects_previously_stored_unsupported_urls(): void
    {
        self::passThroughUrlSanitization();

        foreach (['ftp://example.com/file', 'javascript:alert(1)', '/relative', 'https://', 'not a url'] as $url) {
            $this->assertSame([], IframeLoopBlock::build(['url' => $url]), $url);
        }
    }
}
