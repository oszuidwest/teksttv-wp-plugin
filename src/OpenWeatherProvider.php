<?php

namespace TekstTV;

use DateTime;

class OpenWeatherProvider implements WeatherProvider
{
    private const WEATHER_CACHE_DURATION = 3600; // 1 hour
    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function fetch(string $location): ?array
    {
        $cache_key = 'teksttv_weather_' . sanitize_title($location);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $coords = $this->geocode($location);
            if (!$coords) {
                return null;
            }

            $data = $this->fetch_onecall($coords['lat'], $coords['lon']);
            if (!$data) {
                return null;
            }

            $result = $this->process($data, $coords['name']);
            set_transient($cache_key, $result, self::WEATHER_CACHE_DURATION);

            return $result;
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('TekstTV OpenWeather error: ' . $e->getMessage());
            return null;
        }
    }

    private function geocode(string $location): ?array
    {
        $cache_key = 'teksttv_geo_' . sanitize_title($location);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg([
            'q' => $location,
            'limit' => 1,
            'appid' => $this->api_key,
        ], 'https://api.openweathermap.org/geo/1.0/direct');

        $response = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data) || !isset($data[0]['lat'])) {
            return null;
        }

        $coords = [
            'lat' => $data[0]['lat'],
            'lon' => $data[0]['lon'],
            'name' => $data[0]['local_names']['nl'] ?? $data[0]['name'] ?? $location,
        ];

        set_transient($cache_key, $coords, WEEK_IN_SECONDS);

        return $coords;
    }

    private function fetch_onecall(float $lat, float $lon): ?array
    {
        $url = add_query_arg([
            'lat' => $lat,
            'lon' => $lon,
            'appid' => $this->api_key,
            'units' => 'metric',
            'lang' => 'nl',
            'exclude' => 'minutely,hourly,alerts',
        ], 'https://api.openweathermap.org/data/3.0/onecall');

        $response = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || !isset($data['daily'])) {
            return null;
        }

        return $data;
    }

    private function process(array $data, string $city_name): array
    {
        $days = [];
        $timezone = wp_timezone();

        foreach ($data['daily'] as $day_data) {
            $date = new DateTime('@' . $day_data['dt']);
            $date->setTimezone($timezone);

            $days[] = [
                'date' => $date,
                'temp_min' => $day_data['temp']['min'],
                'temp_max' => $day_data['temp']['max'],
                'weather_id' => $day_data['weather'][0]['id'] ?? 0,
                'icon' => $day_data['weather'][0]['icon'] ?? '01d',
                'description' => ucfirst($day_data['weather'][0]['description'] ?? ''),
                'wind_speed' => $day_data['wind_speed'] ?? 0,
                'wind_deg' => $day_data['wind_deg'] ?? 0,
            ];
        }

        return [
            'city' => $city_name,
            'days' => $days,
        ];
    }
}
