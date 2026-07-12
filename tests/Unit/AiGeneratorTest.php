<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AiGenerator;

class AiGeneratorTest extends TestCase
{
    // =========================================================================
    // within_rate_limit()
    // =========================================================================

    public function test_within_rate_limit_uses_atomic_incr_with_object_cache(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
        // Counter lands on the limit exactly — still allowed.
        Functions\expect('wp_cache_incr')->with('teksttv_ai_rate_7', 1, 'teksttv_ai_rate')->andReturn(10);

        $this->assertTrue(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_blocks_when_incr_exceeds_limit(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\expect('wp_cache_incr')->andReturn(11);

        $this->assertFalse(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_fails_open_when_incr_fails(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\expect('wp_cache_incr')->andReturn(false);

        $this->assertTrue(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_falls_back_to_transient_without_object_cache(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\expect('get_transient')->with('teksttv_ai_rate_7')->andReturn(3);
        Functions\expect('set_transient')->once()->with('teksttv_ai_rate_7', 4, 60);

        $this->assertTrue(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_transient_blocks_at_limit(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\expect('get_transient')->with('teksttv_ai_rate_7')->andReturn(10);
        Functions\expect('set_transient')->never();

        $this->assertFalse(AiGenerator::within_rate_limit(7, 10));
    }

    // =========================================================================
    // validate_ai_output()
    // =========================================================================

    public function test_validate_ai_output_title_within_limit_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];

        $result = AiGenerator::validate_ai_output('title', 'Korte kop', $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_title_over_limit_not_last_attempt_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 10, 'word_limit' => 100];

        $result = AiGenerator::validate_ai_output('title', 'Dit is een veel te lange kop', $prompts, false);
        $this->assertSame('retry', $result);
    }

    public function test_validate_ai_output_title_over_limit_last_attempt_returns_warning(): void
    {
        $prompts = ['title_char_limit' => 10, 'word_limit' => 100];

        $result = AiGenerator::validate_ai_output('title', 'Dit is een veel te lange kop', $prompts, true);
        $this->assertNotEmpty($result);
        $this->assertNotSame('retry', $result);
    }

    public function test_validate_ai_output_body_within_range_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];

        $result = AiGenerator::validate_ai_output('body', str_repeat('woord ', 50), $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_body_over_limit_not_last_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 10];

        $result = AiGenerator::validate_ai_output('body', str_repeat('woord ', 50), $prompts, false);
        $this->assertSame('retry', $result);
    }

    public function test_validate_ai_output_body_under_minimum_returns_retry(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];
        // min = ceil(100 * 0.2) = 20

        $result = AiGenerator::validate_ai_output('body', 'slechts drie woorden', $prompts, false);
        $this->assertSame('retry', $result);
    }

    // =========================================================================
    // prepare_content()
    // =========================================================================

    public function test_prepare_content_strips_scripts(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_prepare_content_converts_block_elements_to_newlines(): void
    {
        $html = '<p>Alinea een</p><p>Alinea twee</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringContainsString("Alinea een\n", $result);
        $this->assertStringContainsString('Alinea twee', $result);
    }

    public function test_prepare_content_decodes_entities(): void
    {
        $html = '<p>Caf&eacute; &amp; bar</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringContainsString('Café & bar', $result);
    }

    public function test_prepare_content_normalizes_whitespace(): void
    {
        $html = '<p>Veel    spaties</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringContainsString('Veel spaties', $result);
    }

    public function test_prepare_content_strips_style_tags(): void
    {
        $html = '<style>.red { color: red; }</style><p>Visible</p>';
        $result = AiGenerator::prepare_content($html);
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

        [$user_prompt, $system] = AiGenerator::build_ai_prompt('title', 'Mijn titel', 'Artikeltekst hier', $prompts);

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

        [$user_prompt, $system] = AiGenerator::build_ai_prompt('body', 'Titel', 'Tekst', $prompts);

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
        [$user_prompt] = AiGenerator::build_ai_prompt('title', 'Titel', $long_text, $prompts);

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
        [$user_prompt] = AiGenerator::build_ai_prompt('body', 'Titel', $long_text, $prompts);

        // Body prompt truncates to 4000 chars
        $this->assertLessThanOrEqual(4100, mb_strlen($user_prompt));
    }

    // =========================================================================
    // get_region_prefix()
    // =========================================================================

    public function test_get_region_prefix_returns_empty_when_no_taxonomy_configured(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => '']);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_empty_when_taxonomy_not_exists(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => 'regio']);
        Functions\expect('taxonomy_exists')
            ->with('regio')
            ->andReturn(false);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_uppercase_term_name(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => 'regio']);
        Functions\expect('taxonomy_exists')
            ->with('regio')
            ->andReturn(true);
        Functions\expect('wp_get_post_terms')
            ->with(1, 'regio', ['fields' => 'names'])
            ->andReturn(['Leiden']);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('LEIDEN', $result);
    }

    public function test_get_region_prefix_joins_multiple_terms(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => 'regio']);
        Functions\expect('taxonomy_exists')->andReturn(true);
        Functions\expect('wp_get_post_terms')
            ->andReturn(['Den Haag', 'Leiden']);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('DEN HAAG / LEIDEN', $result);
    }

    public function test_get_region_prefix_returns_empty_when_no_terms(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => 'regio']);
        Functions\expect('taxonomy_exists')->andReturn(true);
        Functions\expect('wp_get_post_terms')->andReturn([]);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_empty_on_wp_error(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn(['region_taxonomy' => 'regio']);
        Functions\expect('taxonomy_exists')->andReturn(true);

        $error = \Mockery::mock('WP_Error');
        Functions\expect('wp_get_post_terms')->andReturn($error);
        Functions\expect('is_wp_error')->with($error)->andReturn(true);

        $result = AiGenerator::get_region_prefix(1);
        $this->assertSame('', $result);
    }

    // =========================================================================
    // validate_ai_output() — body at exact boundaries
    // =========================================================================

    public function test_validate_ai_output_body_at_exact_minimum_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];
        // min = ceil(100 * 0.2) = 20 words
        $content = implode(' ', array_fill(0, 20, 'woord'));

        $result = AiGenerator::validate_ai_output('body', $content, $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_body_at_exact_maximum_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 100];
        $content = implode(' ', array_fill(0, 100, 'woord'));

        $result = AiGenerator::validate_ai_output('body', $content, $prompts, false);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_body_over_limit_last_attempt_returns_warning(): void
    {
        $prompts = ['title_char_limit' => 40, 'word_limit' => 10];
        $content = implode(' ', array_fill(0, 50, 'woord'));

        $result = AiGenerator::validate_ai_output('body', $content, $prompts, true);
        $this->assertNotEmpty($result);
        $this->assertNotSame('retry', $result);
    }

    public function test_validate_ai_output_title_at_exact_limit_returns_empty(): void
    {
        $prompts = ['title_char_limit' => 10, 'word_limit' => 100];

        $result = AiGenerator::validate_ai_output('title', '1234567890', $prompts, false);
        $this->assertSame('', $result);
    }

    // =========================================================================
    // generate_single_field() — with mocked AI
    // =========================================================================

    public function test_generate_single_field_returns_body_with_wpautop(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'system' => 'Test',
                'prompt_body' => 'Vat samen',
                'word_limit' => 100,
                'title_char_limit' => 40,
                'max_retries' => 1,
                'max_tokens' => 2048,
                'temperature' => '',
                'top_p' => '',
                'model' => '',
                'provider' => '',
            ]);

        // Mock wp_ai_client_prompt chain
        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('generate_text')
            ->andReturn(implode(' ', array_fill(0, 50, 'woord')));

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $result = AiGenerator::generate_single_field('body', 'Titel', 'Tekst hier');

        $this->assertArrayHasKey('content', $result);
        $this->assertStringStartsWith('<p>', $result['content']);
        $this->assertArrayNotHasKey('warning', $result);
    }

    public function test_generate_single_field_returns_title_without_wpautop(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'system' => 'Test',
                'prompt_title' => 'Schrijf kop',
                'word_limit' => 100,
                'title_char_limit' => 40,
                'max_retries' => 1,
                'max_tokens' => 2048,
                'temperature' => '',
                'top_p' => '',
                'model' => '',
                'provider' => '',
            ]);

        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('generate_text')->andReturn('Korte kop');

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);

        $result = AiGenerator::generate_single_field('title', 'Titel', 'Tekst');

        $this->assertSame('Korte kop', $result['content']);
    }

    public function test_generate_single_field_returns_wp_error_on_failure(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'system' => 'Test',
                'prompt_body' => 'Vat samen',
                'word_limit' => 100,
                'title_char_limit' => 40,
                'max_retries' => 1,
                'max_tokens' => 2048,
                'temperature' => '',
                'top_p' => '',
                'model' => '',
                'provider' => '',
            ]);

        $wp_error = \Mockery::mock('WP_Error');
        $wp_error->shouldReceive('get_error_message')->andReturn('API timeout');

        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('generate_text')->andReturn($wp_error);

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->with($wp_error)->andReturn(true);
        Functions\expect('error_log')->andReturn(true);

        $result = AiGenerator::generate_single_field('body', 'Titel', 'Tekst');

        $this->assertSame($wp_error, $result);
    }

    public function test_generate_single_field_retries_on_length_violation(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_ai_prompts', [])
            ->andReturn([
                'system' => 'Test',
                'prompt_body' => 'Vat samen',
                'word_limit' => 10,
                'title_char_limit' => 40,
                'max_retries' => 2,
                'max_tokens' => 2048,
                'temperature' => '',
                'top_p' => '',
                'model' => '',
                'provider' => '',
            ]);

        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        // First attempt: too many words, second attempt: still too many
        $builder->shouldReceive('generate_text')
            ->twice()
            ->andReturn(implode(' ', array_fill(0, 50, 'woord')));

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $result = AiGenerator::generate_single_field('body', 'Titel', 'Tekst');

        // Should have a warning because both attempts exceeded limit
        $this->assertArrayHasKey('warning', $result);
        $this->assertNotSame('retry', $result['warning']);
    }

    // =========================================================================
    // prepare_content() — additional edge cases
    // =========================================================================

    public function test_prepare_content_handles_br_tags(): void
    {
        $html = '<p>Line one<br/>Line two</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringContainsString("Line one\n", $result);
        $this->assertStringContainsString('Line two', $result);
    }

    public function test_prepare_content_handles_empty_string(): void
    {
        $this->assertSame('', AiGenerator::prepare_content(''));
    }

    public function test_prepare_content_strips_noscript_tags(): void
    {
        $html = '<noscript><img src="tracker.gif"></noscript><p>Content</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertStringNotContainsString('noscript', $result);
        $this->assertStringNotContainsString('tracker', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function test_prepare_content_limits_consecutive_newlines(): void
    {
        $html = '<p>One</p><p></p><p></p><p></p><p>Two</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $result);
    }
}
