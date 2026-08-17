<?php

// Satisfy the direct-access guard during CLI analysis.
if (PHP_SAPI === 'cli' && !defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
defined('ABSPATH') || exit;

// Packaging validates the production value from teksttv.php.
define('TEKSTTV_VERSION', 'phpstan-analysis');
define('TEKSTTV_PLUGIN_DIR', __DIR__ . '/');
define('TEKSTTV_PLUGIN_URL', '/');
