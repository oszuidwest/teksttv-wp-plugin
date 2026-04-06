<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WP constants used throughout the plugin
if (!defined('TEKSTTV_PLUGIN_DIR')) {
    define('TEKSTTV_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (!defined('TEKSTTV_PLUGIN_URL')) {
    define('TEKSTTV_PLUGIN_URL', 'https://example.com/wp-content/plugins/teksttv/');
}
if (!defined('TEKSTTV_VERSION')) {
    define('TEKSTTV_VERSION', '1.0.0-test');
}

// Provide global stubs for common WP functions that Brain\Monkey cannot
// intercept when called via array_map() or similar PHP internals.
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []): array
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }
        return array_merge($defaults, (array) $args);
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
    {
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}
