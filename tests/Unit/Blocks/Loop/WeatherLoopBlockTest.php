<?php

namespace TekstTV\Tests\Unit\Blocks\Loop;

use Brain\Monkey\Functions;
use TekstTV\Blocks\Loop\WeatherLoopBlock;
use TekstTV\OpenWeatherProvider;
use TekstTV\Tests\Unit\TestCase;
use TekstTV\WeatherProvider;

class WeatherLoopBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WeatherLoopBlock::resetWeatherProvider();
    }

    public function test_save(): void
    {
        $result = WeatherLoopBlock::save([
            'location' => 'Breda,NL',
            'title' => 'Het weer',
            'duration' => '15',
        ]);

        $this->assertSame('Breda,NL', $result['location']);
        $this->assertSame('Het weer', $result['title']);
        $this->assertSame(15, $result['duration']);
    }

    public function test_save_sanitizes_location(): void
    {
        $result = WeatherLoopBlock::save([
            'location' => '<script>alert("xss")</script>Breda',
        ]);

        $this->assertStringNotContainsString('<script>', $result['location']);
    }

    public function test_save_clamps_duration_to_ui_max(): void
    {
        $result = WeatherLoopBlock::save([
            'location' => 'Breda,NL',
            'duration' => '9999',
        ]);

        $this->assertSame(120, $result['duration']);
    }

    public function test_save_omits_empty_duration(): void
    {
        $result = WeatherLoopBlock::save([
            'location' => 'Breda,NL',
            'title' => 'Weer',
            'duration' => '',
        ]);

        $this->assertArrayNotHasKey('duration', $result);
    }

    public function test_save_empty_fields(): void
    {
        $result = WeatherLoopBlock::save([]);

        $this->assertSame('', $result['location']);
        $this->assertSame('', $result['title']);
    }

    public function test_wind_deg_north(): void
    {
        $this->assertSame('N', WeatherLoopBlock::wind_deg_to_direction(0.0));
    }

    public function test_wind_deg_east(): void
    {
        $this->assertSame('O', WeatherLoopBlock::wind_deg_to_direction(90.0));
    }

    public function test_wind_deg_south(): void
    {
        $this->assertSame('Z', WeatherLoopBlock::wind_deg_to_direction(180.0));
    }

    public function test_wind_deg_west(): void
    {
        $this->assertSame('W', WeatherLoopBlock::wind_deg_to_direction(270.0));
    }

    public function test_wind_deg_360_wraps_to_north(): void
    {
        $this->assertSame('N', WeatherLoopBlock::wind_deg_to_direction(360.0));
    }

    public function test_wind_deg_northeast(): void
    {
        $this->assertSame('NO', WeatherLoopBlock::wind_deg_to_direction(45.0));
    }

    public function test_wind_deg_southeast(): void
    {
        $this->assertSame('ZO', WeatherLoopBlock::wind_deg_to_direction(135.0));
    }

    public function test_wind_deg_southwest(): void
    {
        $this->assertSame('ZW', WeatherLoopBlock::wind_deg_to_direction(225.0));
    }

    public function test_wind_deg_northwest(): void
    {
        $this->assertSame('NW', WeatherLoopBlock::wind_deg_to_direction(315.0));
    }

    public function test_beaufort_calm(): void
    {
        $this->assertSame(0, WeatherLoopBlock::wind_speed_to_beaufort(0.0));
    }

    public function test_beaufort_light_breeze(): void
    {
        $this->assertSame(2, WeatherLoopBlock::wind_speed_to_beaufort(2.0));
    }

    public function test_beaufort_moderate_wind(): void
    {
        $this->assertSame(4, WeatherLoopBlock::wind_speed_to_beaufort(6.5));
    }

    public function test_beaufort_strong_gale(): void
    {
        $this->assertSame(9, WeatherLoopBlock::wind_speed_to_beaufort(22.0));
    }

    public function test_beaufort_hurricane(): void
    {
        $this->assertSame(12, WeatherLoopBlock::wind_speed_to_beaufort(40.0));
    }

    public function test_beaufort_boundary_value(): void
    {
        $this->assertSame(1, WeatherLoopBlock::wind_speed_to_beaufort(0.3));
    }

    public function test_build_returns_empty_when_no_location(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['location' => '', 'title' => 'Weer'];
        $this->assertSame([], WeatherLoopBlock::build($block));
    }

    public function test_build_returns_empty_when_no_provider(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('');
        Functions\expect('apply_filters')->andReturn(null);
        Functions\expect('error_log')->andReturn(true);

        $block = ['location' => 'Breda,NL', 'title' => 'Weer'];
        $this->assertSame([], WeatherLoopBlock::build($block));
    }

    public function test_build_formats_output_correctly(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $mock_provider = \Mockery::mock(WeatherProvider::class);
        $mock_provider->shouldReceive('fetch')
            ->with('Breda,NL')
            ->andReturn([
                'city' => 'Breda',
                'days' => [
                    [
                        'date' => new \DateTime('2026-04-07'),
                        'temp_min' => 8.3,
                        'temp_max' => 15.7,
                        'weather_id' => 800,
                        'description' => 'Helder',
                        'icon' => '01d',
                        'wind_deg' => 180.0,
                        'wind_speed' => 5.5,
                    ],
                    [
                        'date' => new \DateTime('2026-04-08'),
                        'temp_min' => 10.1,
                        'temp_max' => 17.2,
                        'weather_id' => 802,
                        'description' => 'Bewolkt',
                        'icon' => '03d',
                        'wind_deg' => 270.0,
                        'wind_speed' => 3.0,
                    ],
                ],
            ]);

        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('test-key');
        Functions\expect('apply_filters')
            ->with('teksttv_weather_provider', \Mockery::type(OpenWeatherProvider::class))
            ->andReturn($mock_provider);

        Functions\expect('date_i18n')->andReturnUsing(function ($format, $timestamp) {
            return date($format, $timestamp);
        });

        $block = ['location' => 'Breda,NL', 'title' => 'Het weer', 'duration' => 20];
        $result = WeatherLoopBlock::build($block);

        $this->assertCount(1, $result);
        $slide = $result[0];
        $this->assertSame('weather', $slide['type']);
        $this->assertSame(20000, $slide['duration']);
        $this->assertSame('Het weer', $slide['title']);
        $this->assertSame('Breda', $slide['location']);
        $this->assertCount(2, $slide['days']);

        $day1 = $slide['days'][0];
        $this->assertSame('vandaag', $day1['day_short']);
        $this->assertSame(8, $day1['temp_min']);
        $this->assertSame(16, $day1['temp_max']);
        $this->assertSame(800, $day1['weather_id']);
        $this->assertSame('Z', $day1['wind_direction']);
        $this->assertSame(4, $day1['wind_beaufort']);

        $day2 = $slide['days'][1];
        $this->assertNotSame('vandaag', $day2['day_short']);
        $this->assertSame('W', $day2['wind_direction']);
    }

    public function test_build_returns_empty_when_fetch_fails(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $mock_provider = \Mockery::mock(WeatherProvider::class);
        $mock_provider->shouldReceive('fetch')->andReturn(null);

        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('key');
        Functions\expect('apply_filters')->andReturn($mock_provider);
        Functions\expect('error_log')->andReturn(true);

        $block = ['location' => 'Onbekend', 'title' => 'Weer'];
        $this->assertSame([], WeatherLoopBlock::build($block));
    }

    public function test_get_weather_provider_returns_null_without_api_key(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_openweather_api_key', '')
            ->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_weather_provider', null)
            ->andReturn(null);

        $result = WeatherLoopBlock::getWeatherProvider();
        $this->assertNull($result);
    }

    public function test_get_weather_provider_returns_provider_with_api_key(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_openweather_api_key', '')
            ->andReturn('test-key');
        Functions\expect('apply_filters')
            ->andReturnUsing(fn ($filter, $provider) => $provider);

        $result = WeatherLoopBlock::getWeatherProvider();
        $this->assertInstanceOf(WeatherProvider::class, $result);
    }

    public function test_get_weather_provider_caches_result(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_openweather_api_key', '')
            ->once()
            ->andReturn('test-key');
        Functions\expect('apply_filters')
            ->once()
            ->andReturnUsing(fn ($filter, $provider) => $provider);

        $result1 = WeatherLoopBlock::getWeatherProvider();
        $result2 = WeatherLoopBlock::getWeatherProvider();

        $this->assertSame($result1, $result2);
    }

    public function test_get_weather_provider_allows_filter_override(): void
    {
        $custom_provider = \Mockery::mock(WeatherProvider::class);

        Functions\expect('get_option')
            ->with('teksttv_openweather_api_key', '')
            ->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_weather_provider', null)
            ->andReturn($custom_provider);

        $result = WeatherLoopBlock::getWeatherProvider();
        $this->assertSame($custom_provider, $result);
    }

    public function test_build_uses_default_duration(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $mock_provider = \Mockery::mock(WeatherProvider::class);
        $mock_provider->shouldReceive('fetch')->andReturn([
            'city' => 'Breda',
            'days' => [
                [
                    'date' => new \DateTime('2026-04-07'),
                    'temp_min' => 8.0, 'temp_max' => 16.0,
                    'weather_id' => 800, 'description' => 'Helder',
                    'icon' => '01d', 'wind_deg' => 0, 'wind_speed' => 0,
                ],
            ],
        ]);

        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('key');
        Functions\expect('apply_filters')->andReturn($mock_provider);
        Functions\expect('date_i18n')->andReturn('dinsdag 7 apr');

        $block = ['location' => 'Breda,NL', 'title' => 'Weer'];
        $result = WeatherLoopBlock::build($block);

        $this->assertSame(15000, $result[0]['duration']);
    }
}
