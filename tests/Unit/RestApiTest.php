<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\RestApi;

class RestApiTest extends TestCase
{
    /**
     * Request mock serving fixed params.
     *
     * @param array<string, mixed> $params
     */
    private static function requestMock(array $params): \Mockery\MockInterface
    {
        $request = \Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_param')->andReturnUsing(fn ($key) => $params[$key] ?? null);
        return $request;
    }

    /**
     * Serve get_option for the keys generate_content touches.
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
                'teksttv_features' => ['ai_generate'],
                'teksttv_ai_prompts' => ['min_input_words' => 0, 'max_retries' => 1],
                default => $default,
            };
        });
    }

    /** Rate limiter passes via the transient path. */
    private static function stubRateLimitOk(): void
    {
        Functions\when('TekstTV\\time')->justReturn(120);
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_transient')->justReturn(0);
        Functions\when('set_transient')->justReturn(true);
    }

    /**
     * Stub every pre-generation check to pass (feature enabled, AI available,
     * capability, existing post, rate limit ok). Tests override individual
     * stubs after calling this.
     *
     * @param array<string, mixed> $option_overrides Passed to stubOptions().
     */
    private static function stubHappyPath(array $option_overrides = []): void
    {
        self::stubOptions($option_overrides);
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);
    }

    /**
     * Assert a WP_Error carrying the given HTTP status in its error data,
     * the shape WP_REST_Server maps onto the response status.
     */
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
        // ai_generate is absent from the enabled features list.
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

    public function test_generate_content_returns_403_without_edit_post_and_skips_rate_limit(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('current_user_can')->justReturn(false);
        // Forbidden requests must not consume rate-limit quota.
        Functions\expect('get_transient')->never();
        Functions\expect('wp_cache_incr')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(403, $response);
    }

    public function test_generate_content_returns_404_for_missing_post(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('get_post')->justReturn(null);
        Functions\expect('current_user_can')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(404, $response);
    }

    public function test_generate_content_returns_429_when_rate_limited(): void
    {
        self::stubHappyPath();
        Functions\when('get_transient')->justReturn(10); // default rate_limit is 10.

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertErrorStatus(429, $response);
    }

    public function test_generate_content_passes_ai_generator_error_through(): void
    {
        self::stubHappyPath();

        // Empty post -> AiGenerator returns teksttv_no_content with status 422.
        Functions\when('get_post')->justReturn(self::makePost(['post_title' => '', 'post_content' => '']));

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

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
        $this->assertSame('<p>' . $body_text . '</p>', $data['body']);
        $this->assertArrayNotHasKey('warning', $data);
    }

    /**
     * REST owns one thing here: reading has_photo off the request and handing
     * it to the generator. Both directions are asserted - one call would leave
     * a hardcoded `true` at the call site undetected.
     */
    public function test_generate_content_forwards_has_photo_to_the_generator(): void
    {
        self::stubHappyPath(['teksttv_ai_prompts' => [
            'min_input_words' => 0,
            'max_retries' => 1,
            'word_limit' => 100,
            'word_limit_photo' => 25,
        ]]);
        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\when('update_post_meta')->justReturn(true);

        // 50 words: inside the plain limit, over the photo limit.
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
