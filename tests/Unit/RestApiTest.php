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

    private static function makePost(): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = 42;
        $post->post_title = 'Titel';
        $post->post_content = '<p>' . implode(' ', array_fill(0, 60, 'woord')) . '</p>';
        return $post;
    }

    /** Rate limiter passes via the transient path. */
    private static function stubRateLimitOk(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_transient')->justReturn(0);
        Functions\when('set_transient')->justReturn(true);
    }

    public function test_generate_content_returns_403_when_ai_generate_disabled(): void
    {
        // ai_generate is absent from the enabled features list.
        Functions\when('get_option')->justReturn(['custom_title', 'scheduling']);

        $request = \Mockery::mock('WP_REST_Request');

        $response = RestApi::generate_content($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_generate_content_returns_503_when_ai_unsupported(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(false);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(503, $response->get_status());
        $this->assertArrayHasKey('error', $response->get_data());
    }

    public function test_generate_content_returns_403_without_edit_post_and_skips_rate_limit(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        // Forbidden requests must not consume rate-limit quota.
        Functions\expect('get_transient')->never();
        Functions\expect('wp_cache_incr')->never();

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(403, $response->get_status());
    }

    public function test_generate_content_returns_404_for_missing_post(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(null);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(404, $response->get_status());
    }

    public function test_generate_content_returns_429_when_rate_limited(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_transient')->justReturn(10); // default rate_limit is 10.
        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(429, $response->get_status());
    }

    public function test_generate_content_maps_wp_error_status_to_http_status(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);

        // Empty post -> AiGenerator returns teksttv_no_content with status 422.
        $post = new \WP_Post();
        $post->ID = 42;
        Functions\when('get_post')->justReturn($post);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(422, $response->get_status());
        $this->assertArrayHasKey('error', $response->get_data());
    }

    public function test_generate_content_returns_400_for_invalid_field(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'bogus']));

        $this->assertSame(400, $response->get_status());
    }

    public function test_generate_content_single_field_returns_content_shape(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);
        Functions\expect('update_post_meta')->once()->with(42, '_teksttv_ai_title', 'Korte kop');

        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('generate_text')->andReturn('Korte kop');
        Functions\when('wp_ai_client_prompt')->justReturn($builder);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'title']));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['content' => 'Korte kop'], $response->get_data());
    }

    public function test_generate_content_both_returns_title_and_body_shape(): void
    {
        self::stubOptions();
        Functions\when('wp_supports_ai')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(self::makePost());
        Functions\when('get_current_user_id')->justReturn(7);
        self::stubRateLimitOk();
        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);
        Functions\when('wpautop')->alias(fn ($text) => '<p>' . $text . '</p>');
        Functions\expect('update_post_meta')->twice();

        $body_text = implode(' ', array_fill(0, 50, 'woord'));
        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('generate_text')->andReturn('Korte kop', $body_text);
        Functions\when('wp_ai_client_prompt')->justReturn($builder);

        $response = RestApi::generate_content(self::requestMock(['post_id' => 42, 'field' => 'both']));

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('Korte kop', $data['title']);
        $this->assertSame('<p>' . $body_text . '</p>', $data['body']);
        $this->assertArrayNotHasKey('warning', $data);
    }

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
