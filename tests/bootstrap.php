<?php

// Bootstrap file is loaded by PHPUnit from the CLI; synthesize ABSPATH
// so the direct-access guard below stays satisfied during test runs.
if (PHP_SAPI === 'cli' && !defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
defined('ABSPATH') || exit;

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

// Minimal WP_Query stub for unit testing methods that instantiate WP_Query.
// Tests set WP_Query::$stubPosts before calling the method under test.
if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var list<object> Posts to return from the stub. Set this in your test. */
        public static array $stubPosts = [];

        /** @var array<string, mixed> The query args passed to the constructor. */
        public array $query_vars = [];

        /** @var list<object> */
        public array $posts = [];

        public int $found_posts = 0;
        public int $max_num_pages = 1;

        private int $current = -1;

        public function __construct(array $args = [])
        {
            $this->query_vars = $args;
            $this->posts = self::$stubPosts;
            $this->found_posts = count($this->posts);
        }

        public function have_posts(): bool
        {
            return ($this->current + 1) < count($this->posts);
        }

        public function the_post(): void
        {
            $this->current++;
        }

        /** Get the current post in the loop (for mocking get_the_ID). */
        public function current_post(): ?object
        {
            return $this->posts[$this->current] ?? null;
        }

        /** Reset stub state between tests. */
        public static function reset(): void
        {
            self::$stubPosts = [];
        }
    }
}
