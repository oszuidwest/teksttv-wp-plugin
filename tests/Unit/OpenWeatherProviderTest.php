<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\OpenWeatherProvider;

class OpenWeatherProviderTest extends TestCase
{
    // =========================================================================
    // fetch() — cached result
    // =========================================================================

    public function test_fetch_returns_cached_result(): void
    {
        $cached_data = ['city' => 'Breda', 'days' => [['temp_min' => 8]]];

        Functions\expect('sanitize_title')->andReturn('bredanl');
        Functions\expect('get_transient')
            ->with('teksttv_weather_bredanl')
            ->andReturn($cached_data);

        $provider = new OpenWeatherProvider('test-api-key');
        $result = $provider->fetch('Breda,NL');

        $this->assertSame($cached_data, $result);
    }

    // =========================================================================
    // fetch() — geocode failure
    // =========================================================================

    public function test_fetch_returns_null_when_geocode_fails(): void
    {
        Functions\when('sanitize_title')->justReturn('onbekend');
        Functions\when('add_query_arg')->justReturn('https://api.openweathermap.org/mock');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn('[]');
        Functions\when('wp_remote_get')->justReturn(['body' => '[]']);
        Functions\when('error_log')->justReturn(true);

        Functions\when('get_transient')->alias(function (string $key) {
            return false; // Both weather and geo cache miss
        });

        $provider = new OpenWeatherProvider('test-api-key');
        $result = $provider->fetch('Onbekend');

        $this->assertNull($result);
    }

    // =========================================================================
    // fetch() — geocode succeeds but API fails
    // =========================================================================

    public function test_fetch_returns_null_when_api_returns_error(): void
    {
        Functions\when('sanitize_title')->justReturn('bredanl');
        Functions\when('add_query_arg')->justReturn('https://api.openweathermap.org/mock');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 401]]);
        Functions\when('error_log')->justReturn(true);

        Functions\when('get_transient')->alias(function (string $key) {
            if ($key === 'teksttv_geo_bredanl') {
                return ['lat' => 51.59, 'lon' => 4.78, 'name' => 'Breda'];
            }
            return false;
        });

        $provider = new OpenWeatherProvider('invalid-key');
        $result = $provider->fetch('Breda,NL');

        $this->assertNull($result);
    }

    // =========================================================================
    // fetch() — WP_Error from HTTP
    // =========================================================================

    public function test_fetch_returns_null_on_http_error(): void
    {
        Functions\expect('sanitize_title')->andReturn('bredanl');
        Functions\expect('get_transient')
            ->with('teksttv_weather_bredanl')
            ->andReturn(false);

        Functions\expect('get_transient')
            ->with('teksttv_geo_bredanl')
            ->andReturn(false);

        Functions\expect('add_query_arg')->andReturn('https://api.openweathermap.org/geo/1.0/direct');

        $wp_error = \Mockery::mock('WP_Error');
        Functions\expect('wp_remote_get')->andReturn($wp_error);
        Functions\expect('is_wp_error')->andReturn(true);

        Functions\expect('error_log')->andReturn(true);

        $provider = new OpenWeatherProvider('test-key');
        $result = $provider->fetch('Breda,NL');

        $this->assertNull($result);
    }

    // =========================================================================
    // fetch() — successful end-to-end with cached geocode
    // =========================================================================

    public function test_fetch_returns_weather_data_on_success(): void
    {
        $api_response = [
            'daily' => [
                [
                    'dt' => 1744070400,
                    'temp' => ['min' => 8.0, 'max' => 16.0],
                    'weather' => [['id' => 800, 'icon' => '01d', 'description' => 'helder']],
                    'wind_speed' => 5.0,
                    'wind_deg' => 180,
                ],
            ],
        ];
        $response_body = json_encode($api_response);

        Functions\when('sanitize_title')->justReturn('bredanl');
        Functions\when('add_query_arg')->justReturn('https://api.openweathermap.org/mock');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn($response_body);
        Functions\when('wp_remote_get')->justReturn(['body' => $response_body]);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Amsterdam'));
        Functions\when('set_transient')->justReturn(true);
        Functions\when('error_log')->justReturn(true);

        // Weather cache miss, geocode from cache
        Functions\when('get_transient')->alias(function (string $key) {
            if ($key === 'teksttv_geo_bredanl') {
                return ['lat' => 51.59, 'lon' => 4.78, 'name' => 'Breda'];
            }
            return false;
        });

        $provider = new OpenWeatherProvider('test-key');
        $result = $provider->fetch('Breda,NL');

        $this->assertNotNull($result);
        $this->assertSame('Breda', $result['city']);
        $this->assertCount(1, $result['days']);
        $this->assertEquals(8.0, $result['days'][0]['temp_min']);
        $this->assertEquals(16.0, $result['days'][0]['temp_max']);
        $this->assertSame(800, $result['days'][0]['weather_id']);
        $this->assertSame('Helder', $result['days'][0]['description']);
    }
}
