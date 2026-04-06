<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Helpers;
use TekstTV\SlidesBuilder;
use TekstTV\WeatherProvider;

class SlidesBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SlidesBuilder::reset_weather_provider();
    }

    // =========================================================================
    // wind_deg_to_direction()
    // =========================================================================

    public function test_wind_deg_north(): void
    {
        $this->assertSame('N', SlidesBuilder::wind_deg_to_direction(0.0));
    }

    public function test_wind_deg_east(): void
    {
        $this->assertSame('O', SlidesBuilder::wind_deg_to_direction(90.0));
    }

    public function test_wind_deg_south(): void
    {
        $this->assertSame('Z', SlidesBuilder::wind_deg_to_direction(180.0));
    }

    public function test_wind_deg_west(): void
    {
        $this->assertSame('W', SlidesBuilder::wind_deg_to_direction(270.0));
    }

    public function test_wind_deg_360_wraps_to_north(): void
    {
        $this->assertSame('N', SlidesBuilder::wind_deg_to_direction(360.0));
    }

    public function test_wind_deg_northeast(): void
    {
        $this->assertSame('NO', SlidesBuilder::wind_deg_to_direction(45.0));
    }

    public function test_wind_deg_southeast(): void
    {
        $this->assertSame('ZO', SlidesBuilder::wind_deg_to_direction(135.0));
    }

    public function test_wind_deg_southwest(): void
    {
        $this->assertSame('ZW', SlidesBuilder::wind_deg_to_direction(225.0));
    }

    public function test_wind_deg_northwest(): void
    {
        $this->assertSame('NW', SlidesBuilder::wind_deg_to_direction(315.0));
    }

    // =========================================================================
    // wind_speed_to_beaufort()
    // =========================================================================

    public function test_beaufort_calm(): void
    {
        $this->assertSame(0, SlidesBuilder::wind_speed_to_beaufort(0.0));
    }

    public function test_beaufort_light_breeze(): void
    {
        $this->assertSame(2, SlidesBuilder::wind_speed_to_beaufort(2.0));
    }

    public function test_beaufort_moderate_wind(): void
    {
        $this->assertSame(4, SlidesBuilder::wind_speed_to_beaufort(6.5));
    }

    public function test_beaufort_strong_gale(): void
    {
        $this->assertSame(9, SlidesBuilder::wind_speed_to_beaufort(22.0));
    }

    public function test_beaufort_hurricane(): void
    {
        $this->assertSame(12, SlidesBuilder::wind_speed_to_beaufort(40.0));
    }

    public function test_beaufort_boundary_value(): void
    {
        // Exactly 0.3 m/s should be bft 1 (not 0, since 0.3 is NOT < 0.3)
        $this->assertSame(1, SlidesBuilder::wind_speed_to_beaufort(0.3));
    }

    // =========================================================================
    // split_pages()
    // =========================================================================

    public function test_split_pages_single_page(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = SlidesBuilder::split_pages('<p>Hello world</p>');
        $this->assertSame(['<p>Hello world</p>'], $result);
    }

    public function test_split_pages_with_html_separator(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = SlidesBuilder::split_pages('<p>Page one</p><p>---</p><p>Page two</p>');
        $this->assertCount(2, $result);
        $this->assertSame('<p>Page one</p>', $result[0]);
        $this->assertSame('<p>Page two</p>', $result[1]);
    }

    public function test_split_pages_with_multiple_dashes(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = SlidesBuilder::split_pages('<p>One</p><p>-----</p><p>Two</p>');
        $this->assertCount(2, $result);
    }

    public function test_split_pages_filters_empty_parts(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $result = SlidesBuilder::split_pages('<p>---</p><p>Only page</p><p>---</p>');
        $this->assertCount(1, $result);
        $this->assertSame('<p>Only page</p>', $result[0]);
    }

    public function test_split_pages_without_feature_returns_single_page(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn([]); // page_separator not enabled

        $content = '<p>Page one</p><p>---</p><p>Page two</p>';
        $result = SlidesBuilder::split_pages($content);
        $this->assertCount(1, $result);
        $this->assertSame($content, $result[0]);
    }

    public function test_split_pages_empty_content(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $this->assertSame([], SlidesBuilder::split_pages(''));
    }

    public function test_split_pages_whitespace_only(): void
    {
        Functions\expect('get_option')
            ->with('teksttv_features', \Mockery::any())
            ->andReturn(['page_separator']);

        $this->assertSame([], SlidesBuilder::split_pages('   '));
    }

    // =========================================================================
    // build_image_slide()
    // =========================================================================

    public function test_build_image_slide_returns_empty_when_not_scheduled(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'image_id' => 123,
            'date_start' => '2026-05-01',
            'date_end' => '2026-05-31',
        ];

        $this->assertSame([], SlidesBuilder::build_image_slide($block));
    }

    public function test_build_image_slide_returns_empty_when_no_image_id(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $this->assertSame([], SlidesBuilder::build_image_slide(['image_id' => 0]));
    }

    public function test_build_image_slide_returns_slide_with_custom_duration(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->with(42, 'large')->andReturn('https://example.com/image.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('');
        Functions\expect('apply_filters')->with('teksttv_image_attribution', '', 42)->andReturn('');

        $block = ['image_id' => 42, 'duration' => 10];
        $result = SlidesBuilder::build_image_slide($block);

        $this->assertCount(1, $result);
        $this->assertSame('image', $result[0]['type']);
        $this->assertSame(10000, $result[0]['duration']);
        $this->assertSame('https://example.com/image.jpg', $result[0]['url']);
    }

    public function test_build_image_slide_uses_default_duration(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/image.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')->andReturn('');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $block = ['image_id' => 42];
        $result = SlidesBuilder::build_image_slide($block);

        $this->assertSame(7000, $result[0]['duration']);
    }

    public function test_build_image_slide_returns_empty_when_attachment_not_found(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn(false);

        $block = ['image_id' => 999];
        $this->assertSame([], SlidesBuilder::build_image_slide($block));
    }

    // =========================================================================
    // build_commercial_slides() — rotation limit
    // =========================================================================

    public function test_build_commercial_slides_returns_empty_when_no_groups(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['groups' => []];
        $this->assertSame([], SlidesBuilder::build_commercial_slides($block, 'tv1'));
    }

    public function test_build_commercial_slides_returns_empty_when_not_scheduled(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07 12:00:00'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = [
            'groups' => ['sponsors'],
            'date_start' => '2026-05-01',
        ];
        $this->assertSame([], SlidesBuilder::build_commercial_slides($block, 'tv1'));
    }

    public function test_build_commercial_slides_with_campaigns(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'date_start' => '2026-04-01',
                    'date_end' => '2026-04-30',
                    'duration' => 5,
                    'slides' => [100, 101],
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['groups' => ['sponsors']];
        $result = SlidesBuilder::build_commercial_slides($block, 'tv1');

        $this->assertCount(2, $result);
        $this->assertSame('commercial', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('https://example.com/img-100.jpg', $result[0]['url']);
        $this->assertSame('https://example.com/img-101.jpg', $result[1]['url']);
    }

    public function test_build_commercial_slides_filters_by_channel(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv2'], // Not tv1
                    'group' => 'sponsors',
                    'slides' => [100],
                ],
            ]);

        $block = ['groups' => ['sponsors']];
        $result = SlidesBuilder::build_commercial_slides($block, 'tv1');

        $this->assertSame([], $result);
    }

    public function test_build_commercial_slides_rotation_limit(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'slides' => [1, 2, 3, 4, 5],
                ],
            ]);
        Functions\expect('get_option')
            ->with('teksttv_duration_image', 7)
            ->andReturn(7);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = ['groups' => ['sponsors'], 'limit' => 2];
        $result = SlidesBuilder::build_commercial_slides($block, 'tv1');

        // Should only return 2 slides despite 5 available
        $this->assertCount(2, $result);
    }

    public function test_build_commercial_slides_intro_outro(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([
                [
                    'channels' => ['tv1'],
                    'group' => 'sponsors',
                    'slides' => [100],
                    'duration' => 5,
                ],
            ]);
        Functions\expect('wp_get_attachment_url')
            ->andReturnUsing(fn($id) => 'https://example.com/img-' . $id . '.jpg');

        $block = [
            'groups' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = SlidesBuilder::build_commercial_slides($block, 'tv1');

        $this->assertCount(3, $result);
        $this->assertSame('commercial_transition', $result[0]['type']);
        $this->assertSame(5000, $result[0]['duration']);
        $this->assertSame('commercial', $result[1]['type']);
        $this->assertSame('commercial_transition', $result[2]['type']);
    }

    public function test_build_commercial_slides_no_intro_outro_when_no_commercials(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')
            ->with('teksttv_campaigns', [])
            ->andReturn([]);

        $block = [
            'groups' => ['sponsors'],
            'intro_image_id' => 50,
            'outro_image_id' => 51,
        ];
        $result = SlidesBuilder::build_commercial_slides($block, 'tv1');

        // No campaigns matched, so no intro/outro either
        $this->assertSame([], $result);
    }

    // =========================================================================
    // build_image_slide() — caption & attribution
    // =========================================================================

    public function test_build_image_slide_includes_caption(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('Een mooie foto');
        Functions\expect('apply_filters')->andReturn('');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = SlidesBuilder::build_image_slide(['image_id' => 42]);

        $this->assertSame('Een mooie foto', $result[0]['caption']);
    }

    public function test_build_image_slide_includes_attribution(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 42)
            ->andReturn('Foto: Jan Jansen');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = SlidesBuilder::build_image_slide(['image_id' => 42]);

        $this->assertSame('Foto: Jan Jansen', $result[0]['attribution']);
        $this->assertArrayNotHasKey('caption', $result[0]);
    }

    public function test_build_image_slide_includes_both_caption_and_attribution(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('wp_get_attachment_image_url')->andReturn('https://example.com/photo.jpg');
        Functions\expect('wp_get_attachment_caption')->andReturn('Zonsondergang');
        Functions\expect('apply_filters')->andReturn('Foto: ANP');
        Functions\expect('get_option')->with('teksttv_duration_image', 7)->andReturn(7);

        $result = SlidesBuilder::build_image_slide(['image_id' => 42]);

        $this->assertSame('Zonsondergang', $result[0]['caption']);
        $this->assertSame('Foto: ANP', $result[0]['attribution']);
    }

    // =========================================================================
    // build_weather_slide()
    // =========================================================================

    public function test_build_weather_slide_returns_empty_when_no_location(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $block = ['location' => '', 'title' => 'Weer'];
        $this->assertSame([], SlidesBuilder::build_weather_slide($block));
    }

    public function test_build_weather_slide_returns_empty_when_no_provider(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));
        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('');
        Functions\expect('apply_filters')->andReturn(null);
        Functions\expect('error_log')->andReturn(true);

        $block = ['location' => 'Breda,NL', 'title' => 'Weer'];
        $this->assertSame([], SlidesBuilder::build_weather_slide($block));
    }

    public function test_build_weather_slide_formats_output_correctly(): void
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

        // Inject the mock provider via the filter
        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('test-key');
        Functions\expect('apply_filters')
            ->with('teksttv_weather_provider', \Mockery::type(SlidesBuilder::class . '|' . 'object'))
            ->andReturn($mock_provider);

        Functions\expect('date_i18n')->andReturnUsing(function ($format, $timestamp) {
            return date($format, $timestamp);
        });

        $block = ['location' => 'Breda,NL', 'title' => 'Het weer', 'duration' => 20];
        $result = SlidesBuilder::build_weather_slide($block);

        $this->assertCount(1, $result);
        $slide = $result[0];
        $this->assertSame('weather', $slide['type']);
        $this->assertSame(20000, $slide['duration']);
        $this->assertSame('Het weer', $slide['title']);
        $this->assertSame('Breda', $slide['location']);
        $this->assertCount(2, $slide['days']);

        // First day
        $day1 = $slide['days'][0];
        $this->assertSame('vandaag', $day1['day_short']);
        $this->assertSame(8, $day1['temp_min']);
        $this->assertSame(16, $day1['temp_max']);
        $this->assertSame(800, $day1['weather_id']);
        $this->assertSame('Z', $day1['wind_direction']);
        $this->assertSame(4, $day1['wind_beaufort']); // 5.5 m/s = bft 4

        // Second day
        $day2 = $slide['days'][1];
        $this->assertNotSame('vandaag', $day2['day_short']);
        $this->assertSame('W', $day2['wind_direction']);
    }

    public function test_build_weather_slide_returns_empty_when_fetch_fails(): void
    {
        Functions\expect('current_datetime')->andReturn(new \DateTimeImmutable('2026-04-07'));
        Functions\expect('wp_timezone')->andReturn(new \DateTimeZone('UTC'));

        $mock_provider = \Mockery::mock(WeatherProvider::class);
        $mock_provider->shouldReceive('fetch')->andReturn(null);

        Functions\expect('get_option')->with('teksttv_openweather_api_key', '')->andReturn('key');
        Functions\expect('apply_filters')->andReturn($mock_provider);
        Functions\expect('error_log')->andReturn(true);

        $block = ['location' => 'Onbekend', 'title' => 'Weer'];
        $this->assertSame([], SlidesBuilder::build_weather_slide($block));
    }

    // =========================================================================
    // get_sidebar_image_data() — priority chain
    // =========================================================================

    public function test_sidebar_image_override_with_explicit_none(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('0');

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertNull($result);
    }

    public function test_sidebar_image_custom_override(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('42');
        Functions\expect('wp_get_attachment_image_url')->with(42, 'large')->andReturn('https://example.com/custom.jpg');
        Functions\expect('wp_get_attachment_caption')->with(42)->andReturn('');
        Functions\expect('apply_filters')->with('teksttv_image_attribution', '', 42)->andReturn('');

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/custom.jpg', $result['url']);
    }

    public function test_sidebar_image_falls_back_to_category_image(): void
    {
        // No override
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        // No primary category
        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 1)
            ->andReturn('');

        // Category with TekstTV image
        Functions\expect('wp_get_post_categories')->with(1)->andReturn([10, 20]);
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('55');
        Functions\expect('wp_get_attachment_image_url')->with(55, 'large')->andReturn('https://example.com/cat.jpg');
        Functions\expect('wp_get_attachment_caption')->with(55)->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 55)
            ->andReturn('');

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/cat.jpg', $result['url']);
    }

    public function test_sidebar_image_falls_back_to_post_thumbnail(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 1)
            ->andReturn('');

        Functions\expect('wp_get_post_categories')->with(1)->andReturn([10]);
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('');

        // Thumbnail fallback
        Functions\expect('get_post_thumbnail_id')->with(1)->andReturn(77);
        Functions\expect('wp_get_attachment_image_url')->with(77, 'large')->andReturn('https://example.com/thumb.jpg');
        Functions\expect('wp_get_attachment_caption')->with(77)->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 77)
            ->andReturn('');

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/thumb.jpg', $result['url']);
    }

    public function test_sidebar_image_returns_null_when_nothing_found(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 1)
            ->andReturn('');

        Functions\expect('wp_get_post_categories')->with(1)->andReturn([]);
        Functions\expect('get_post_thumbnail_id')->with(1)->andReturn(0);

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertNull($result);
    }

    public function test_sidebar_image_primary_category_takes_precedence(): void
    {
        Functions\expect('get_post_meta')
            ->with(1, '_teksttv_sidebar_image', true)
            ->andReturn('');

        // Primary category has image
        Functions\expect('apply_filters')
            ->with('teksttv_primary_category', \Mockery::any(), 1)
            ->andReturn(10);
        Functions\expect('get_term_meta')
            ->with(10, '_teksttv_category_image', true)
            ->andReturn('88');
        Functions\expect('wp_get_attachment_image_url')->with(88, 'large')->andReturn('https://example.com/primary.jpg');
        Functions\expect('wp_get_attachment_caption')->with(88)->andReturn('');
        Functions\expect('apply_filters')
            ->with('teksttv_image_attribution', '', 88)
            ->andReturn('');

        $result = SlidesBuilder::get_sidebar_image_data(1);
        $this->assertSame('https://example.com/primary.jpg', $result['url']);
    }
}
