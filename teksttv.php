<?php
/**
 * Plugin Name: TekstTV
 * Description: Beheer tekst-tv slides en lever ze via een REST API voor de TekstTV frontend.
 * Version: 0.0.3
 * Author: ZuidWest
 * Author URI: https://www.zuidwesttv.nl/
 * Plugin URI: https://github.com/oszuidwest/teksttv-wp-plugin
 * License: GPL-2.0-or-later
 * Text Domain: teksttv-wp-plugin
 * Requires at least: 7.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TEKSTTV_VERSION', '0.0.3');
define('TEKSTTV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEKSTTV_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

TekstTV\Plugin::init();

TekstTV\Updater::init(__FILE__);


register_activation_hook(__FILE__, function () {
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_teksttv');
        $admin->add_cap('manage_teksttv_campaigns');
        $admin->add_cap('manage_teksttv_content');
        $admin->add_cap('edit_teksttv');
    }

    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap('edit_teksttv');
    }

    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    foreach (['administrator', 'editor'] as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_teksttv');
            $role->remove_cap('manage_teksttv_campaigns');
            $role->remove_cap('manage_teksttv_content');
            $role->remove_cap('edit_teksttv');
        }
    }

    flush_rewrite_rules();
});
