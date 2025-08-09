<?php
/**
 * Plugin Name: vpn-server-manager
 * Description: Upload, parse, manage, test, and download OpenVPN (.ovpn) profiles.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

// Constants
define('VPNPM_VERSION', '1.0.0');
define('VPNPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VPNPM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once VPNPM_PLUGIN_DIR . 'includes/db-functions.php';
require_once VPNPM_PLUGIN_DIR . 'includes/parser.php';
require_once VPNPM_PLUGIN_DIR . 'includes/helpers.php';
require_once VPNPM_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once VPNPM_PLUGIN_DIR . 'admin/admin-page.php';

// Activation
register_activation_hook(__FILE__, 'vpnpm_activate_plugin');
function vpnpm_activate_plugin() {
    vpnpm_create_tables();
    flush_rewrite_rules();
}

// Admin menu
add_action('admin_menu', 'vpnpm_add_admin_menu');
function vpnpm_add_admin_menu() {
    add_menu_page(
        __('VPN Manager', 'vpnpm'),
        __('VPN Manager', 'vpnpm'),
        'manage_options',
        'vpn-manager',
        'vpnpm_admin_page',
        'dashicons-shield',
        30
    );
}

// Admin assets
add_action('admin_enqueue_scripts', 'vpnpm_admin_assets');
function vpnpm_admin_assets($hook) {
    if ($hook !== 'toplevel_page_vpn-manager') {
        return;
    }
    wp_enqueue_style('vpnpm-admin', VPNPM_PLUGIN_URL . 'assets/css/admin.css', [], VPNPM_VERSION);
    wp_enqueue_script('vpnpm-admin', VPNPM_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], VPNPM_VERSION, true);
    wp_localize_script('vpnpm-admin', 'vpnpmAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vpnpm-nonce'),
        'strings' => [
            'testing' => __('Testing...', 'vpnpm'),
            'tested'  => __('Tested', 'vpnpm'),
        ],
    ]);
}
