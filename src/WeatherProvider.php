<?php

namespace TekstTV;

/**
 * Provide weather data in the frontend's normalized schema.
 */
interface WeatherProvider
{
    /**
     * @param string $location Human-readable location (e.g. "Breda,NL")
     * @return array<string, mixed>|null Normalized weather data or null on failure.
     *   Expected shape: ['city' => string, 'days' => [['date' => DateTime, ...]]]
     */
    public function fetch(string $location): ?array;
}
