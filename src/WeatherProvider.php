<?php

namespace TekstTV;

/**
 * Interface for weather data providers.
 *
 * Implementations fetch forecast data and return it in a normalized format
 * matching the TekstTV frontend WeatherSlideData schema.
 */
interface WeatherProvider
{
    /**
     * Fetch weather forecast for a location.
     *
     * @param string $location Human-readable location (e.g. "Breda,NL")
     * @return array|null Normalized weather data or null on failure.
     *   Expected shape: ['city' => string, 'days' => [['date' => DateTime, ...]]]
     */
    public function fetch(string $location): ?array;
}
