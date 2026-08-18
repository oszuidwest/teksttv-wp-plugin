<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\AiGenerator;
use TekstTV\RestApi;

class RestApiTest extends TestCase
{
    /**
     * Request double with fixed params.
     *
     * @param array<string, mixed> $params
     */
    private static function requestMock(array $params): \Mockery\MockInterface
    {
        $params += [
            'source_title' => 'Titel',
            'source_content' => '<p>Inhoud</p>',
        ];
        $request = \Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_param')->andReturnUsing(fn ($key) => $params[$key] ?? null);
        return $request;
    }

    /**
     * Stub generation options.
     *
     * @param array<string, mixed> $overrides
     */
    private static function stubOptions(array $overrides = []): void
    {
        Functions\when('get_option')->alias(function ($key, $default = false) use ($overrides) {
            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }
            return match ($key) {
                'teksttv_features' => ['ai_generate', 'custom_title'],
                'teksttv_ai_prompts' => ['min_input_words' => 0],
                default => $default,
            };
        });
    }

    /** Pass rate limiting through transients. */
    private static function stubRateLimitOk(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_transient')->justReturn(0);
        Functions\when('set_transient')->justReturn(true);
    }

    /**
     * Pass every pre-generation check; tests override individual stubs.
     *
     * @param array<string, mixed> $option_overrides Passed to stubOptions().
     */
    private static function stubHappyPath(array $option_overrides = []): void
    {
        self::stubOptions($option_overrides);
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder());
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);
        Functions\when('wp_slash')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
    }

    private function assertErrorStatus(int $status, mixed $response): void
    {
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame($status, $response->get_error_data()['status'] ?? null);
    }

    public function test_get_image_data_returns_404_error_for_missing_attachment(): void
    {
        Functions\when('wp_get_attachment_image_url')->justReturn(false);

        $response = RestApi::get_image_data(self::requestMock(['id' => 999]));

        $this->assertErrorStatus(404, $response);
    }

    public function test_generate_content_returns_403_when_ai_generate_disabled(): void
    {
        Functions\when('get_option')->justReturn(['custom_title', 'scheduling']);

        $request = \Mockery::mock('WP_REST_Request');

        $this->assertErrorStatus(403, RestApi::generate_content($request));
    }

    public function test_generate_content_returns_503_when_ai_unsupported(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(false);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(503, $response);
        $this->assertNotSame('', $response->get_error_message());
    }

    public function test_generate_content_returns_503_when_no_provider_supports_the_prompt(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockUnsupportedAiBuilder());
        Functions\expect('get_post')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(503, $response);
        $this->assertSame('teksttv_ai_unavailable', $response->get_error_code());
    }

    public function test_generate_content_returns_403_without_edit_post_and_skips_rate_limit(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder());
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('current_user_can')->justReturn(false);
        // Reject before touching quota.
        Functions\expect('get_transient')->never();
        Functions\expect('wp_cache_incr')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(403, $response);
    }

    public function test_generate_content_returns_404_for_missing_post(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder());
        Functions\when('get_post')->justReturn(null);
        Functions\expect('current_user_can')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(404, $response);
    }

    public function test_generate_content_returns_429_when_rate_limited(): void
    {
        self::stubHappyPath();
        Functions\when('get_transient')->justReturn(AiGenerator::REQUESTS_PER_MINUTE);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(429, $response);
    }

    public function test_generate_content_both_succeeds_when_two_quota_slots_remain(): void
    {
        self::stubHappyPath();
        Functions\when('get_transient')->justReturn(AiGenerator::REQUESTS_PER_MINUTE - 2);
        $reserved_count = null;
        Functions\when('set_transient')->alias(
            static function (string $key, int $count, int $expiration) use (&$reserved_count): bool {
                $reserved_count = $count;
                return true;
            }
        );
        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\when('update_post_meta')->justReturn(true);

        $body_text = implode(' ', array_fill(0, 50, 'woord'));
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder('Korte kop', $body_text));

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'both']));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(AiGenerator::REQUESTS_PER_MINUTE, $reserved_count);
    }

    public function test_generate_content_both_returns_429_when_only_one_quota_slot_remains(): void
    {
        self::stubHappyPath();
        Functions\when('get_transient')->justReturn(AiGenerator::REQUESTS_PER_MINUTE - 1);
        Functions\expect('set_transient')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'both']));

        $this->assertErrorStatus(429, $response);
    }

    public function test_generate_content_rejects_missing_editor_state_before_counting_quota(): void
    {
        self::stubHappyPath();
        Functions\expect('get_transient')->never();

        $response = RestApi::generate_content(
            self::requestMock([
                'post_id' => 42,
                'field' => 'body',
                'source_title' => null,
                'source_content' => null,
            ])
        );

        $this->assertErrorStatus(400, $response);
        $this->assertSame('teksttv_editor_state_unavailable', $response->get_error_code());
    }

    public function test_generate_content_uses_sanitized_unsaved_editor_state(): void
    {
        self::stubHappyPath();
        Functions\when('update_post_meta')->justReturn(true);

        $builder = self::mockAiBuilder('Nieuwe kop');
        $prompts = [];
        Functions\when('wp_ai_client_prompt')->alias(function ($prompt) use ($builder, &$prompts) {
            $prompts[] = $prompt;
            return $builder;
        });

        $response = RestApi::generate_content(self::requestMock([
            'post_id' => 42,
            'field' => 'title',
            'source_title' => ' <b>Nieuwe titel</b> ',
            'source_content' => '<p>Actuele onopgeslagen tekst</p>',
        ]));

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Nieuwe kop', $response->get_data()['content']);
        $this->assertTrue((bool) array_filter(
            $prompts,
            fn ($prompt) => str_contains($prompt, 'Titel: Nieuwe titel')
                && str_contains($prompt, 'Actuele onopgeslagen tekst')
        ));
    }

    public function test_generate_content_passes_ai_generator_error_through(): void
    {
        self::stubHappyPath();

        Functions\when('get_post')->justReturn(self::makePost(['post_title' => '', 'post_content' => '']));

        $response = RestApi::generate_content(self::requestMock([
            'post_id' => 42,
            'field' => 'title',
            'source_title' => '',
            'source_content' => '',
        ]));

        $this->assertErrorStatus(422, $response);
        $this->assertSame('teksttv_no_content', $response->get_error_code());
    }

    public function test_generate_content_single_field_returns_content_shape(): void
    {
        self::stubHappyPath();
        Functions\expect('update_post_meta')->once()->with(42, '_teksttv_ai_title', 'Korte kop');

        $builder = self::mockAiBuilder('Korte kop');
        Functions\when('wp_ai_client_prompt')->justReturn($builder);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['content' => 'Korte kop'], $response->get_data());
    }

    public function test_generate_content_returns_403_for_title_fields_when_custom_title_disabled(): void
    {
        self::stubHappyPath(['teksttv_features' => ['ai_generate']]);
        Functions\expect('update_post_meta')->never();

        foreach (['title', 'both'] as $field) {
            $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => $field]));

            $this->assertErrorStatus(403, $response);
            $this->assertSame('teksttv_custom_title_disabled', $response->get_error_code());
        }
    }

    public function test_generate_content_body_only_does_not_generate_a_title(): void
    {
        self::stubHappyPath(['teksttv_features' => ['ai_generate']]);
        $body_text = implode(' ', array_fill(0, 50, 'woord'));

        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\expect('update_post_meta')->once()->with(42, '_teksttv_ai_body', $body_text);
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder($body_text));

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'body']));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['content' => $body_text], $response->get_data());
    }

    public function test_generate_content_both_returns_title_and_body_shape(): void
    {
        self::stubHappyPath();
        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\expect('update_post_meta')->twice();

        $body_text = implode(' ', array_fill(0, 50, 'woord'));
        $builder = self::mockAiBuilder('Korte kop', $body_text);
        Functions\when('wp_ai_client_prompt')->justReturn($builder);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'both']));

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('Korte kop', $data['title']);
        $this->assertSame($body_text, $data['body']);
        $this->assertArrayNotHasKey('warning', $data);
    }

    public function test_generate_content_forwards_has_photo_to_the_generator(): void
    {
        self::stubHappyPath(['teksttv_ai_prompts' => [
            'min_input_words' => 0,
            'word_limit' => 100,
            'word_limit_photo' => 25,
        ]
        ]);
        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\when('update_post_meta')->justReturn(true);

        // Valid without a photo; too long with one.
        $body_text = implode(' ', array_fill(0, 50, 'woord'));
        Functions\when('wp_ai_client_prompt')->justReturn(self::mockAiBuilder($body_text, $body_text));

        $with_photo = RestApi::generate_content(
            self::requestMock(['post_id' => 42, 'field' => 'body', 'has_photo' => true])
        );
        $without_photo = RestApi::generate_content(
            self::requestMock(['post_id' => 42, 'field' => 'body', 'has_photo' => false])
        );

        $this->assertArrayHasKey('warning', $with_photo->get_data(), 'has_photo did not reach the generator.');
        $this->assertArrayNotHasKey('warning', $without_photo->get_data());
    }
}
