<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Helpers;
use TekstTV\OpenWeatherProvider;
use TekstTV\WeatherProvider;

final class WeatherLoopBlock
{
    private const DEFAULT_DURATION_SECONDS = 15;

    /** @var array<int, string> */
    private const DUTCH_WEEKDAYS = [
        1 => 'maandag',
        2 => 'dinsdag',
        3 => 'woensdag',
        4 => 'donderdag',
        5 => 'vrijdag',
        6 => 'zaterdag',
        7 => 'zondag',
    ];

    /** @var array<int, string> */
    private const DUTCH_WEEKDAYS_SHORT = [
        1 => 'ma',
        2 => 'di',
        3 => 'wo',
        4 => 'do',
        5 => 'vr',
        6 => 'za',
        7 => 'zo',
    ];

    /** @var array<int, string> */
    private const DUTCH_MONTHS_SHORT = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mrt',
        4 => 'apr',
        5 => 'mei',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'okt',
        11 => 'nov',
        12 => 'dec',
    ];

    private static ?WeatherProvider $weather_provider = null;

    private static bool $weather_provider_resolved = false;

    public static function register(): void
    {
        BlockRegistry::register('weather', [
            'label' => 'Weer',
            'icon' => 'cloud',
            'color' => '#72aee6',
            'context' => 'loop',
            'render' => [self::class, 'render_fields'],
            'save' => [self::class, 'save'],
            'build' => [self::class, 'build'],
        ]);
    }

    /**
     * Get the weather provider instance.
     * Filterable via 'teksttv_weather_provider' for custom implementations.
     */
    public static function getWeatherProvider(): ?WeatherProvider
    {
        if (self::$weather_provider_resolved) {
            return self::$weather_provider;
        }

        $api_key = get_option('teksttv_openweather_api_key', '');

        $provider = !empty($api_key) ? new OpenWeatherProvider($api_key) : null;

        /**
         * Filter the weather provider.
         *
         * @param WeatherProvider|null $provider The provider instance.
         */
        self::$weather_provider = apply_filters('teksttv_weather_provider', $provider);
        self::$weather_provider_resolved = true;

        return self::$weather_provider;
    }

    /**
     * Reset the cached weather provider. Useful for testing.
     */
    public static function resetWeatherProvider(): void
    {
        self::$weather_provider = null;
        self::$weather_provider_resolved = false;
    }

    public static function wind_deg_to_direction(float $deg): string
    {
        $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
        $index = (int) round($deg / 22.5) % 16;
        return $directions[$index];
    }

    public static function wind_speed_to_beaufort(float $speed): int
    {
        $scale = [0.3, 1.6, 3.4, 5.5, 8.0, 10.8, 13.9, 17.2, 20.8, 24.5, 28.5, 32.7];
        foreach ($scale as $bft => $threshold) {
            if ($speed < $threshold) {
                return $bft;
            }
        }
        return 12;
    }

    private static function format_dutch_date(\DateTimeInterface $date): string
    {
        $weekday = self::DUTCH_WEEKDAYS[(int) $date->format('N')];
        $month = self::DUTCH_MONTHS_SHORT[(int) $date->format('n')];

        return sprintf('%s %d %s', $weekday, (int) $date->format('j'), $month);
    }

    private static function format_dutch_day_short(\DateTimeInterface $date): string
    {
        return self::DUTCH_WEEKDAYS_SHORT[(int) $date->format('N')];
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function render_fields(int|string $index, array $block, string $prefix): void
    {
        $location = $block['location'] ?? '';
        $title = $block['title'] ?? '';
        $duration = $block['duration'] ?? '';
        $location_id = Helpers::field_id($prefix, $index, 'location');
        $title_id = Helpers::field_id($prefix, $index, 'title');
        $duration_id = Helpers::field_id($prefix, $index, 'duration');

        ?>
        <div class="teksttv-field-grid">
            <div class="teksttv-field teksttv-field--text">
                <label for="<?php echo esc_attr($location_id); ?>" data-teksttv-label="location"><?php echo esc_html('Locatie'); ?></label>
                <input type="text" id="<?php echo esc_attr($location_id); ?>" data-teksttv-field="location" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][location]" value="<?php echo esc_attr((string) $location); ?>" class="regular-text" placeholder="<?php echo esc_attr('Breda, NL'); ?>" autocomplete="off" data-summary data-summary-empty="<?php echo esc_attr('Geen locatie'); ?>" />
            </div>
            <div class="teksttv-field teksttv-field--text">
                <label for="<?php echo esc_attr($title_id); ?>" data-teksttv-label="title"><?php echo esc_html('Titel'); ?></label>
                <input type="text" id="<?php echo esc_attr($title_id); ?>" data-teksttv-field="title" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr((string) $title); ?>" class="regular-text" placeholder="<?php echo esc_attr('Het weer'); ?>" autocomplete="off" />
            </div>
            <div class="teksttv-field teksttv-field--compact">
                <label for="<?php echo esc_attr($duration_id); ?>" data-teksttv-label="duration"><?php echo esc_html('Duur'); ?></label>
                <div class="teksttv-input-with-unit">
                    <input type="number" id="<?php echo esc_attr($duration_id); ?>" data-teksttv-field="duration" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration]" value="<?php echo esc_attr((string) $duration); ?>" min="1" max="120" class="small-text" placeholder="<?php echo esc_attr((string) self::DEFAULT_DURATION_SECONDS); ?>" />
                    <span class="teksttv-unit">sec</span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function save(array $raw): array
    {
        $saved = [
            'location' => sanitize_text_field($raw['location'] ?? ''),
            'title' => sanitize_text_field($raw['title'] ?? ''),
        ];

        $dur = $raw['duration'] ?? '';
        if ($dur !== '') {
            $saved['duration'] = Helpers::clamp_int($dur, 1, 120);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        $location = $block['location'] ?? '';
        $title = $block['title'] ?? '';
        if (empty($location)) {
            return [];
        }

        $provider = self::getWeatherProvider();
        if (!$provider) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV: No weather provider configured. Check OpenWeather API key in settings.');
            return [];
        }

        $weather = $provider->fetch($location);
        if (!$weather || empty($weather['days'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('TekstTV: Weather fetch failed for location "%s".', $location));
            return [];
        }

        $duration = Helpers::fixed_duration_ms($block['duration'] ?? null, self::DEFAULT_DURATION_SECONDS);

        $days_output = [];
        foreach ($weather['days'] as $index => $day) {
            $date = $day['date'];
            $days_output[] = [
                'date' => self::format_dutch_date($date),
                'day_short' => $index === 0 ? 'vandaag' : self::format_dutch_day_short($date),
                'temp_min' => (int) round($day['temp_min']),
                'temp_max' => (int) round($day['temp_max']),
                'weather_id' => $day['weather_id'],
                'description' => $day['description'],
                'icon' => $day['icon'],
                'wind_direction' => self::wind_deg_to_direction($day['wind_deg'] ?? 0),
                'wind_beaufort' => self::wind_speed_to_beaufort($day['wind_speed'] ?? 0),
            ];
        }

        return [[
            'type' => 'weather',
            'duration' => $duration,
            'title' => $title,
            'location' => $weather['city'],
            'days' => $days_output,
        ]
        ];
    }
}
