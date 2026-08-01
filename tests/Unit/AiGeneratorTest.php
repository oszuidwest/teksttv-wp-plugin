<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AiGenerator;

class AiGeneratorTest extends TestCase
{
    /**
     * Complete AI config as produced by Helpers::get_ai_prompts().
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function aiConfig(array $overrides = []): array
    {
        return $overrides + [
            'system' => 'Test',
            'prompt_title' => 'Schrijf kop',
            'prompt_body' => 'Vat samen',
            'word_limit' => 100,
            'word_limit_photo' => 100,
            'title_char_limit' => 40,
            'min_input_words' => 0,
            'max_retries' => 1,
            'rate_limit' => 10,
            'region_taxonomy' => '',
            'provider' => '',
            'model' => '',
            'temperature' => '',
            'top_p' => '',
            'max_tokens' => 2048,
        ];
    }

    public function test_within_rate_limit_uses_atomic_incr_with_object_cache(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
        // Counter lands on the limit exactly - still allowed.
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

    public function test_within_rate_limit_fails_closed_when_incr_fails(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\expect('wp_cache_incr')->andReturn(false);
        // Failing closed prevents an uncounted paid request.
        Functions\expect('error_log')->once()->andReturn(true);

        $this->assertFalse(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_falls_back_to_transient_without_object_cache(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\expect('get_transient')->with('teksttv_ai_rate_7')->andReturn(3);
        Functions\expect('set_transient')->once()->with('teksttv_ai_rate_7', 4, 60)->andReturn(true);

        $this->assertTrue(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_fails_closed_when_transient_write_fails(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\expect('get_transient')->with('teksttv_ai_rate_7')->andReturn(3);
        Functions\expect('set_transient')->once()->andReturn(false);
        Functions\expect('error_log')->once()->andReturn(true);

        $this->assertFalse(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_within_rate_limit_transient_blocks_at_limit(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\expect('get_transient')->with('teksttv_ai_rate_7')->andReturn(10);
        Functions\expect('set_transient')->never();

        $this->assertFalse(AiGenerator::within_rate_limit(7, 10));
    }

    public function test_validate_ai_output_title_over_limit_returns_warning(): void
    {
        $config = self::aiConfig(['title_char_limit' => 10]);

        $result = AiGenerator::validate_ai_output('title', 'Dit is een veel te lange kop', $config);
        $this->assertStringContainsString('10', $result);
    }

    public function test_validate_ai_output_body_over_limit_returns_warning(): void
    {
        $config = self::aiConfig(['word_limit' => 10]);

        $result = AiGenerator::validate_ai_output('body', str_repeat('woord ', 50), $config);
        $this->assertStringContainsString('50 woorden', $result);
    }

    public function test_validate_ai_output_body_under_minimum_returns_warning(): void
    {
        // min = ceil(100 * 0.2) = 20.
        $result = AiGenerator::validate_ai_output('body', 'slechts drie woorden', self::aiConfig());
        $this->assertStringContainsString('3 woorden', $result);
    }

    public function test_prepare_content_strips_non_content_tags(): void
    {
        // One alternation covers all three. noscript is in it because
        // wp_strip_all_tags() keeps its fallback text, which would otherwise
        // reach the model as article prose.
        $this->assertSame('Hello', AiGenerator::prepare_content('<p>Hello</p><script>alert("xss")</script>'));
        $this->assertSame('Visible', AiGenerator::prepare_content('<style>.red { color: red; }</style><p>Visible</p>'));
        $this->assertSame('Content', AiGenerator::prepare_content('<noscript>Zet JavaScript aan</noscript><p>Content</p>'));
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

    public function test_build_ai_prompt_title_field(): void
    {
        $config = self::aiConfig(['prompt_title' => 'Schrijf een kop']);

        [$user_prompt, $system] = AiGenerator::build_ai_prompt('title', 'Mijn titel', 'Artikeltekst hier', $config);

        $this->assertStringContainsString('Schrijf een kop', $user_prompt);
        $this->assertStringContainsString('Mijn titel', $user_prompt);
        $this->assertStringContainsString('40 tekens', $system);
    }

    public function test_build_ai_prompt_body_field(): void
    {
        $config = self::aiConfig(['prompt_body' => 'Vat samen']);

        [$user_prompt, $system] = AiGenerator::build_ai_prompt('body', 'Titel', 'Tekst', $config);

        $this->assertStringContainsString('Vat samen', $user_prompt);
        $this->assertStringContainsString('100 woorden', $system);
    }

    public function test_build_ai_prompt_truncates_text_for_title(): void
    {
        $long_text = str_repeat('a', 5000);
        [$user_prompt] = AiGenerator::build_ai_prompt('title', 'Titel', $long_text, self::aiConfig());

        // Title prompt truncates to 2000 chars.
        $this->assertLessThanOrEqual(2100, mb_strlen($user_prompt));
    }

    public function test_build_ai_prompt_truncates_text_for_body(): void
    {
        $long_text = str_repeat('a', 8000);
        [$user_prompt] = AiGenerator::build_ai_prompt('body', 'Titel', $long_text, self::aiConfig());

        // Body prompt truncates to 4000 chars.
        $this->assertLessThanOrEqual(4100, mb_strlen($user_prompt));
    }

    public function test_get_region_prefix_returns_empty_when_no_taxonomy_configured(): void
    {
        $result = AiGenerator::get_region_prefix(1, '');
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_empty_when_taxonomy_not_exists(): void
    {
        Functions\expect('taxonomy_exists')
            ->with('regio')
            ->andReturn(false);
        // A configured but missing taxonomy is a config error and is logged.
        Functions\expect('error_log')->once()->andReturn(true);

        $result = AiGenerator::get_region_prefix(1, 'regio');
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_uppercase_term_name(): void
    {
        Functions\expect('taxonomy_exists')
            ->with('regio')
            ->andReturn(true);
        Functions\expect('wp_get_post_terms')
            ->with(1, 'regio', ['fields' => 'names'])
            ->andReturn(['Leiden']);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1, 'regio');
        $this->assertSame('LEIDEN', $result);
    }

    public function test_get_region_prefix_joins_multiple_terms(): void
    {
        Functions\expect('taxonomy_exists')->andReturn(true);
        Functions\expect('wp_get_post_terms')
            ->andReturn(['Den Haag', 'Leiden']);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1, 'regio');
        $this->assertSame('DEN HAAG / LEIDEN', $result);
    }

    public function test_get_region_prefix_returns_empty_when_no_terms(): void
    {
        Functions\expect('taxonomy_exists')->andReturn(true);
        Functions\expect('wp_get_post_terms')->andReturn([]);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::get_region_prefix(1, 'regio');
        $this->assertSame('', $result);
    }

    public function test_get_region_prefix_returns_empty_on_wp_error(): void
    {
        Functions\expect('taxonomy_exists')->andReturn(true);

        $error = \Mockery::mock('WP_Error');
        $error->shouldReceive('get_error_message')->andReturn('lookup failed');
        Functions\expect('wp_get_post_terms')->andReturn($error);
        Functions\expect('is_wp_error')->with($error)->andReturn(true);
        // The failure is logged so a broken taxonomy config is discoverable.
        Functions\expect('error_log')->once()->andReturn(true);

        $result = AiGenerator::get_region_prefix(1, 'regio');
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_body_accepts_both_range_boundaries(): void
    {
        // The range is inclusive: min = ceil(100 * 0.2) = 20, max = word_limit.
        $config = self::aiConfig();

        $this->assertSame('', AiGenerator::validate_ai_output('body', implode(' ', array_fill(0, 20, 'woord')), $config));
        $this->assertSame('', AiGenerator::validate_ai_output('body', implode(' ', array_fill(0, 100, 'woord')), $config));
    }

    public function test_validate_ai_output_title_at_exact_limit_returns_empty(): void
    {
        $config = self::aiConfig(['title_char_limit' => 10]);

        $result = AiGenerator::validate_ai_output('title', '1234567890', $config);
        $this->assertSame('', $result);
    }

    public function test_validate_ai_output_uses_photo_word_limit_when_has_photo(): void
    {
        $config = self::aiConfig(['word_limit' => 100, 'word_limit_photo' => 25]);
        $content = str_repeat('woord ', 30); // 30 words: valid without photo, over the photo limit

        $this->assertSame('', AiGenerator::validate_ai_output('body', $content, $config, false));

        $warning = AiGenerator::validate_ai_output('body', $content, $config, true);

        $this->assertStringContainsString('25', $warning);
    }

    public function test_build_ai_prompt_uses_photo_word_limit_when_has_photo(): void
    {
        $config = self::aiConfig(['word_limit' => 100, 'word_limit_photo' => 25]);

        [, $system] = AiGenerator::build_ai_prompt('body', 'Titel', 'Tekst', $config, true);
        $this->assertStringContainsString('tussen de 5 en 25 woorden', $system);

        [, $system_no_photo] = AiGenerator::build_ai_prompt('body', 'Titel', 'Tekst', $config, false);
        $this->assertStringContainsString('tussen de 20 en 100 woorden', $system_no_photo);
    }

    public function test_generate_single_field_rejects_empty_output_on_last_attempt(): void
    {
        $builder = self::mockAiBuilder('');

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::generate_single_field('title', 'Titel', 'Tekst', self::aiConfig());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('teksttv_empty_output', $result->get_error_code());
    }

    public function test_generate_single_field_retries_after_empty_output(): void
    {
        $builder = self::mockAiBuilder('', 'Korte kop');

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);

        $result = AiGenerator::generate_single_field('title', 'Titel', 'Tekst', self::aiConfig(['max_retries' => 2]));

        $this->assertSame('Korte kop', $result['content']);
        $this->assertArrayNotHasKey('warning', $result);
    }

    public function test_generate_single_field_returns_body_with_wpautop(): void
    {
        $builder = self::mockAiBuilder(implode(' ', array_fill(0, 50, 'woord')));

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $result = AiGenerator::generate_single_field('body', 'Titel', 'Tekst hier', self::aiConfig());

        $this->assertArrayHasKey('content', $result);
        $this->assertStringStartsWith('<p>', $result['content']);
        $this->assertArrayNotHasKey('warning', $result);
    }

    public function test_generate_single_field_returns_title_without_wpautop(): void
    {
        $builder = self::mockAiBuilder('Korte kop');

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);

        $result = AiGenerator::generate_single_field('title', 'Titel', 'Tekst', self::aiConfig());

        $this->assertSame('Korte kop', $result['content']);
    }

    public function test_generate_single_field_returns_wp_error_on_failure(): void
    {
        $wp_error = \Mockery::mock('WP_Error');
        $wp_error->shouldReceive('get_error_message')->andReturn('API timeout');

        $builder = self::mockAiBuilder($wp_error);

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->with($wp_error)->andReturn(true);
        Functions\expect('error_log')->andReturn(true);

        $result = AiGenerator::generate_single_field('body', 'Titel', 'Tekst', self::aiConfig());

        $this->assertSame($wp_error, $result);
    }

    public function test_generate_single_field_retries_on_length_violation(): void
    {
        // First attempt: too many words, second attempt: still too many.
        $response = implode(' ', array_fill(0, 50, 'woord'));
        $builder = self::mockAiBuilder($response, $response);

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $result = AiGenerator::generate_single_field(
            'body',
            'Titel',
            'Tekst',
            self::aiConfig(['word_limit' => 10, 'word_limit_photo' => 10, 'max_retries' => 2])
        );

        $this->assertArrayHasKey('warning', $result);
    }

    public function test_generate_for_post_rejects_invalid_field(): void
    {
        $result = AiGenerator::generate_for_post(self::makePost(), 'bogus', self::aiConfig());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('teksttv_invalid_field', $result->get_error_code());
        $this->assertSame(['status' => 400], $result->get_error_data());
    }

    public function test_generate_for_post_rejects_empty_post(): void
    {
        $result = AiGenerator::generate_for_post(
            self::makePost(['post_title' => '', 'post_content' => '']),
            'body',
            self::aiConfig()
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('teksttv_no_content', $result->get_error_code());
        $this->assertSame(422, $result->get_error_data()['status']);
    }

    public function test_generate_for_post_rejects_too_short_input(): void
    {
        $result = AiGenerator::generate_for_post(
            self::makePost(['post_content' => '<p>veel te kort</p>']),
            'body',
            self::aiConfig(['min_input_words' => 50])
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('teksttv_input_too_short', $result->get_error_code());
        $this->assertSame(422, $result->get_error_data()['status']);
    }

    public function test_generate_for_post_saves_prefixed_body_as_audit_baseline(): void
    {
        $builder = self::mockAiBuilder(implode(' ', array_fill(0, 50, 'woord')));

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $saved_body = null;
        Functions\expect('update_post_meta')
            ->once()
            ->with(42, '_teksttv_ai_body', \Mockery::capture($saved_body));

        Functions\expect('taxonomy_exists')->with('regio')->andReturn(true);
        Functions\expect('wp_get_post_terms')->andReturn(['Leiden']);
        Functions\when('esc_html')->returnArg();

        $result = AiGenerator::generate_for_post(self::makePost(), 'body', self::aiConfig(['region_taxonomy' => 'regio']));

        $this->assertIsArray($result);
        $this->assertStringStartsWith('<p>LEIDEN - ', $result['fields']['body']);
        $this->assertSame($result['fields']['body'], $saved_body);
        $this->assertSame('', $result['warning']);
    }

    public function test_generate_for_post_generates_both_fields(): void
    {
        $builder = self::mockAiBuilder('Korte kop', implode(' ', array_fill(0, 50, 'woord')));

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wpautop')->andReturnUsing(fn($t) => '<p>' . $t . '</p>');

        $saved_body = null;
        Functions\expect('update_post_meta')->once()->with(42, '_teksttv_ai_title', 'Korte kop');
        Functions\expect('update_post_meta')->once()->with(42, '_teksttv_ai_body', \Mockery::capture($saved_body));

        $result = AiGenerator::generate_for_post(self::makePost(), 'both', self::aiConfig());

        $this->assertIsArray($result);
        $this->assertSame('Korte kop', $result['fields']['title']);
        $this->assertStringStartsWith('<p>', $result['fields']['body']);
        $this->assertSame($result['fields']['body'], $saved_body);
    }

    public function test_generate_for_post_maps_provider_failure_to_500(): void
    {
        $wp_error = \Mockery::mock('WP_Error');
        $wp_error->shouldReceive('get_error_message')->andReturn('API timeout');

        $builder = self::mockAiBuilder($wp_error);

        Functions\expect('wp_ai_client_prompt')->andReturn($builder);
        Functions\expect('is_wp_error')->andReturnUsing(fn($v) => $v === $wp_error);
        Functions\expect('error_log')->andReturn(true);

        $result = AiGenerator::generate_for_post(self::makePost(), 'body', self::aiConfig());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('teksttv_generation_failed', $result->get_error_code());
        $this->assertSame(500, $result->get_error_data()['status']);
        $this->assertStringContainsString('API timeout', $result->get_error_message());
    }

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

    public function test_prepare_content_limits_consecutive_newlines(): void
    {
        $html = '<p>One</p><p></p><p></p><p></p><p>Two</p>';
        $result = AiGenerator::prepare_content($html);
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $result);
    }
}
