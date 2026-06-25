<?php
/**
 * PHPStan bootstrap file — defines constants and stubs for runtime symbols.
 */

// Bootstrap file is loaded by PHPStan from the CLI; synthesize ABSPATH
// so the direct-access guard below stays satisfied during static analysis.
if (PHP_SAPI === 'cli' && !defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
defined('ABSPATH') || exit;

define('TEKSTTV_VERSION', '0.0.2');
define('TEKSTTV_PLUGIN_DIR', __DIR__ . '/');
define('TEKSTTV_PLUGIN_URL', '/');
