<?php
/**
 * Verify the packaged plugin and seed its Playground.
 */

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (!is_plugin_active('teksttv/teksttv.php')) {
    throw new RuntimeException('The packaged TekstTV plugin is not active.');
}

foreach (['vendor/autoload.php', 'assets/admin.js'] as $required_file) {
    if (!is_file(WP_PLUGIN_DIR . '/teksttv/' . $required_file)) {
        throw new RuntimeException('The packaged TekstTV plugin is missing ' . $required_file . '.');
    }
}

update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules();

require __DIR__ . '/fixtures.php';
