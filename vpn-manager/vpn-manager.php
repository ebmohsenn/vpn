<?php
/**
 * Plugin Name: HO VPN Server Manager
 * Plugin URI: https://ebmohsen.com
 * Description: Modular VPN server manager with extensions. Display name: HO VPN Manager.
 * Version: 2.0.0
 * Author: Mohsen Eb
 * Author URI: https://ebmohsen.com
 * License: GPLv2 or later
 * Text Domain: hovpnm
 */

// Security
if (!defined('ABSPATH')) { exit; }

// Define constants
define('HOVPNM_VERSION', '2.0.0');
define('HOVPNM_PLUGIN_FILE', __FILE__);
define('HOVPNM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HOVPNM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Core bootstrap
require_once HOVPNM_PLUGIN_DIR . 'core/bootstrap.php';

// Activation/Deactivation
register_activation_hook(__FILE__, function() {
    \HOVPNM\Core\DB::activate();
});

register_deactivation_hook(__FILE__, function() {
    \HOVPNM\Core\DB::deactivate();
});

// Load translations at init (WordPress 6.7+ requirement)
add_action('init', function() {
    load_plugin_textdomain('hovpnm', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Load extensions based on active list
add_action('plugins_loaded', function() {
    \HOVPNM\Core\Bootstrap::load_extensions();
});
