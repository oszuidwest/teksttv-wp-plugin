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

// Common WordPress time constants used across the plugin.
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
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

// Minimal WP_Error stub so domain code can construct errors in unit tests.
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /**
         * @param mixed $data
         */
        public function __construct(
            public string $code = '',
            public string $message = '',
            public $data = null
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /** @return mixed */
        public function get_error_data()
        {
            return $this->data;
        }
    }
}

// Minimal WP_Post stub for passing typed posts into domain methods.
if (!class_exists('WP_Post')) {
    #[\AllowDynamicProperties]
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_content = '';
    }
}

// Minimal WP_REST_Response stub so REST callbacks can be unit tested without
// pulling in WordPress. Captures data and status for assertions.
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var mixed */
        public $data;
        public int $status;

        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /** @return mixed */
        public function get_data()
        {
            return $this->data;
        }
    }
}
