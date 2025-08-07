<?php
/**
 * Plugin Name: VPN Profile Manager
 * Description: Upload and manage OpenVPN profiles with server status.
 * Version: 1.0
 * Author: Your Name
 */

defined('ABSPATH') || exit;

// Activation hook to create DB table
register_activation_hook(__FILE__, 'vpnpm_create_table');

function vpnpm_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'vpn_profiles';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        file_name VARCHAR(255) NOT NULL,
        remote_host VARCHAR(255) NOT NULL,
        port INT(5) NOT NULL,
        protocol VARCHAR(10) NOT NULL,
        status VARCHAR(20) DEFAULT 'unknown',
        notes TEXT DEFAULT '',
        last_checked DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Only define the function once
function vpnpm_add_admin_menu() {
    add_menu_page(
        'VPN Profiles',
        'VPN Profiles',
        'manage_options',
        'vpn-profiles',
        'vpnpm_render_admin_page'
    );
}
add_action('admin_menu', 'vpnpm_add_admin_menu');

// Only load admin page code in admin area
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
}