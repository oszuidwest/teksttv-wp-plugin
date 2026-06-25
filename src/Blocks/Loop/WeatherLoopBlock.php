<?php

namespace TekstTV\Blocks\Loop;

use TekstTV\BlockRegistry;
use TekstTV\Blocks\Contracts\LoopBlock;
use TekstTV\Helpers;
use TekstTV\OpenWeatherProvider;
use TekstTV\WeatherProvider;

final class WeatherLoopBlock implements LoopBlock
{
    private const WEATHER_DURATION = 15000;

    private static ?WeatherProvider $weather_provider = null;

    private static bool $weather_provider_resolved = false;

    public static function register(): void
    {
        BlockRegistry::register('weather', [
            'label' => __('Weer', 'teksttv-wp-plugin'),
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

    /**
     * @param array<string, mixed> $block
     */
    public static function render_fields(int|string $index, array $block, string $prefix): void
    {
        $location = $block['location'] ?? '';
        $title = $block['title'] ?? '';
        $duration = $block['duration'] ?? '';

        ?>
        <div class="teksttv-block-fields">
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Locatie', 'teksttv-wp-plugin'); ?></label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][location]" value="<?php echo esc_attr((string) $location); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Breda,NL', 'teksttv-wp-plugin'); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Titel', 'teksttv-wp-plugin'); ?></label>
                <input type="text" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr((string) $title); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Het weer', 'teksttv-wp-plugin'); ?>" />
            </div>
            <div class="teksttv-block-field">
                <label><?php esc_html_e('Duur', 'teksttv-wp-plugin'); ?></label>
                <input type="number" name="<?php echo esc_attr($prefix); ?>[<?php echo esc_attr((string) $index); ?>][duration]" value="<?php echo esc_attr((string) $duration); ?>" min="1" max="120" class="small-text" placeholder="15" /> <span class="teksttv-unit">sec</span>
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
            $saved['duration'] = absint($dur);
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public static function build(array $block, string $channel = ''): array
    {
        if (!Helpers::is_block_scheduled($block)) {
            return [];
        }

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

        $duration = !empty($block['duration']) ? (int) $block['duration'] * 1000 : self::WEATHER_DURATION;

        $days_output = [];
        foreach ($weather['days'] as $index => $day) {
            $date = $day['date'];
            $days_output[] = [
                'date' => date_i18n('l j M', $date->getTimestamp()),
                'day_short' => $index === 0 ? 'vandaag' : date_i18n('D', $date->getTimestamp()),
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
