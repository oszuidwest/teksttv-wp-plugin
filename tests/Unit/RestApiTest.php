<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\RestApi;

class RestApiTest extends TestCase
{
    // =========================================================================
    // strip_region_prefix()
    // =========================================================================

    public function test_strip_region_prefix_removes_simple_prefix(): void
    {
        $this->assertSame('tekst hier', RestApi::strip_region_prefix('LEIDEN - tekst hier'));
    }

    public function test_strip_region_prefix_removes_multi_region_prefix(): void
    {
        $this->assertSame('tekst hier', RestApi::strip_region_prefix('DEN HAAG / ROOSENDAAL - tekst hier'));
    }

    public function test_strip_region_prefix_leaves_text_without_prefix(): void
    {
        $this->assertSame('gewone tekst', RestApi::strip_region_prefix('gewone tekst'));
    }

    public function test_strip_region_prefix_leaves_lowercase_text(): void
    {
        $this->assertSame('leiden - tekst', RestApi::strip_region_prefix('leiden - tekst'));
    }

    public function test_strip_region_prefix_handles_empty_string(): void
    {
        $this->assertSame('', RestApi::strip_region_prefix(''));
    }

    public function test_strip_region_prefix_trims_whitespace(): void
    {
        $this->assertSame('tekst', RestApi::strip_region_prefix('  UTRECHT - tekst'));
    }

    public function test_strip_region_prefix_handles_prefix_with_hyphens(): void
    {
        $this->assertSame('tekst', RestApi::strip_region_prefix('WEST-BRABANT - tekst'));
    }

    // =========================================================================
    // validate_ai_output()
    // =========================================================================

    public function test_validate_ai_output_title_within_limit_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];

        $result = RestApi::validate_ai_output('title', 'Korte kop', $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_title_over_limit_not_last_attempt_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 10, 'word_limit' => 100];

        $result = RestApi::validate_ai_output('title', 'Dit is een veel te lange kop', $prompts, false);
        $this->assertSame('retry', $result);
    }

    public function test_validate_ai_output_title_over_limit_last_attempt_returns_warning(): void
    {
        $prompts = ['title_char_limit' => 10, 'word_limit' => 100];

        $result = RestApi::validate_ai_output('title', 'Dit is een veel te lange kop', $prompts, true);
        $this->assertNotEmpty($result);
        $this->assertNotSame('retry', $result);
    }

    public function test_validate_ai_output_body_within_range_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];

        $result = RestApi::validate_ai_output('body', str_repeat('woord ', 50), $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_body_over_limit_not_last_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 10];

        $result = RestApi::validate_ai_output('body', str_repeat('woord ', 50), $prompts, false);
        $this->assertSame('retry', $result);
    }

    public function test_validate_ai_output_body_under_minimum_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];
        // min = ceil(100 * 0.2) = 20

        $result = RestApi::validate_ai_output('body', 'slechts drie woorden', $prompts, false);
        $this->assertSame('retry', $result);
    }

    // =========================================================================
    // prepare_content()
    // =========================================================================

    public function test_prepare_content_strips_scripts(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $result = RestApi::prepare_content($html);
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_prepare_content_converts_block_elements_to_newlines(): void
    {
        $html = '<p>Alinea een</p><p>Alinea twee</p>';
        $result = RestApi::prepare_content($html);
        $this->assertStringContainsString("Alinea een\n", $result);
        $this->assertStringContainsString('Alinea twee', $result);
    }

    public function test_prepare_content_decodes_entities(): void
    {
        $html = '<p>Caf&eacute; &amp; bar</p>';
        $result = RestApi::prepare_content($html);
        $this->assertStringContainsString('Café & bar', $result);
    }

    public function test_prepare_content_normalizes_whitespace(): void
    {
        $html = '<p>Veel    spaties</p>';
        $result = RestApi::prepare_content($html);
        $this->assertStringContainsString('Veel spaties', $result);
    }

    public function test_prepare_content_strips_style_tags(): void
    {
        $html = '<style>.red { color: red; }</style><p>Visible</p>';
        $result = RestApi::prepare_content($html);
        $this->assertStringNotContainsString('color', $result);
        $this->assertStringContainsString('Visible', $result);
    }

    // =========================================================================
    // build_ai_prompt()
    // =========================================================================

    public function test_build_ai_prompt_title_field(): void
    {
        $prompts = [
            'system' => 'System prompt.',
            'prompt_title' => 'Schrijf een kop',
            'prompt_body' => 'Vat samen',
            'title_char_limit' => 40,
            'word_limit' => 100,
        ];

        [$user_prompt, $system] = RestApi::build_ai_prompt('title', 'Mijn titel', 'Artikeltekst hier', $prompts);

        $this->assertStringContainsString('Schrijf een kop', $user_prompt);
        $this->assertStringContainsString('Mijn titel', $user_prompt);
        $this->assertStringContainsString('40 tekens', $system);
    }

    public function test_build_ai_prompt_body_field(): void
    {
        $prompts = [
            'system' => 'System prompt.',
            'prompt_title' => 'Schrijf een kop',
            'prompt_body' => 'Vat samen',
            'title_char_limit' => 40,
            'word_limit' => 100,
        ];

        [$user_prompt, $system] = RestApi::build_ai_prompt('body', 'Titel', 'Tekst', $prompts);

        $this->assertStringContainsString('Vat samen', $user_prompt);
        $this->assertStringContainsString('100 woorden', $system);
    }

    public function test_build_ai_prompt_truncates_text_for_title(): void
    {
        $prompts = [
            'system' => 'Sys',
            'prompt_title' => 'Kop',
            'prompt_body' => 'Body',
            'title_char_limit' => 40,
            'word_limit' => 100,
        ];

        $long_text = str_repeat('a', 5000);
        [$user_prompt] = RestApi::build_ai_prompt('title', 'Titel', $long_text, $prompts);

        // Title prompt truncates to 2000 chars
        $this->assertLessThanOrEqual(2100, mb_strlen($user_prompt));
    }

    public function test_build_ai_prompt_truncates_text_for_body(): void
    {
        $prompts = [
            'system' => 'Sys',
            'prompt_title' => 'Kop',
            'prompt_body' => 'Body',
            'title_char_limit' => 40,
            'word_limit' => 100,
        ];

        $long_text = str_repeat('a', 8000);
        [$user_prompt] = RestApi::build_ai_prompt('body', 'Titel', $long_text, $prompts);

        // Body prompt truncates to 4000 chars
        $this->assertLessThanOrEqual(4100, mb_strlen($user_prompt));
    }

    // =========================================================================
    // validate_channel()
    // =========================================================================

    public function test_validate_channel_returns_true_for_valid_channel(): void
    {
        Functions\expect('get_option')->with('teksttv_channels', [])->andReturn([
            ['slug' => 'tv1', 'label' => 'TV 1'],
            ['slug' => 'tv2', 'label' => 'TV 2'],
        ]);

        $this->assertTrue(RestApi::validate_channel('tv1'));
    }

    public function test_validate_channel_returns_false_for_invalid_channel(): void
    {
        Functions\expect('get_option')->with('teksttv_channels', [])->andReturn([
            ['slug' => 'tv1', 'label' => 'TV 1'],
        ]);

        $this->assertFalse(RestApi::validate_channel('tv99'));
    }

    public function test_validate_channel_uses_default_when_no_channels_configured(): void
    {
        Functions\expect('get_option')->with('teksttv_channels', [])->andReturn([]);

        $this->assertTrue(RestApi::validate_channel('tv1'));
    }

    // =========================================================================
    // invalidate_slides_cache()
    // =========================================================================

    public function test_invalidate_slides_cache_single_channel(): void
    {
        Functions\expect('delete_transient')
            ->once()
            ->with('teksttv_slides_tv1')
            ->andReturn(true);

        RestApi::invalidate_slides_cache('tv1');
    }

    public function test_invalidate_slides_cache_all_channels(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_channels', [])
            ->andReturn([
                ['slug' => 'tv1', 'label' => 'TV 1'],
                ['slug' => 'tv2', 'label' => 'TV 2'],
            ]);
        Functions\expect('delete_transient')
            ->with('teksttv_slides_tv1')
            ->once()
            ->andReturn(true);
        Functions\expect('delete_transient')
            ->with('teksttv_slides_tv2')
            ->once()
            ->andReturn(true);

        RestApi::invalidate_slides_cache();
    }
}
