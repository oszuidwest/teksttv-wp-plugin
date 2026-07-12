<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\RestApi;

class RestApiTest extends TestCase
{
    // =========================================================================
    // generate_content() — feature toggle enforcement
    // =========================================================================

    public function test_generate_content_returns_403_when_ai_generate_disabled(): void
    {
        // ai_generate is absent from the enabled features list.
        Functions\when('get_option')->justReturn(['custom_title', 'scheduling']);

        $request = \Mockery::mock('WP_REST_Request');

        $response = RestApi::generate_content($request);

        $this->assertSame(403, $response->get_status());
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
