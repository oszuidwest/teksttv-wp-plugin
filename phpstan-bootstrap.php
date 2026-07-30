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

// Static analysis only needs the constant's type; production metadata is
// validated directly from teksttv.php by the packaging and release scripts.
define('TEKSTTV_VERSION', 'phpstan-analysis');
define('TEKSTTV_PLUGIN_DIR', __DIR__ . '/');
define('TEKSTTV_PLUGIN_URL', '/');
