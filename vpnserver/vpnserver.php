<?php
/**
 * Plugin Name: vpnserver
 * Description: Upload, parse, manage, test, and download OpenVPN (.ovpn) profiles.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

// Constants
if (!defined('VPNSERVER_VERSION')) define('VPNSERVER_VERSION', '1.0.0');
if (!defined('VPNSERVER_PLUGIN_DIR')) define('VPNSERVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('VPNSERVER_PLUGIN_URL')) define('VPNSERVER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once VPNSERVER_PLUGIN_DIR . 'includes/db-functions.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/parser.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/helpers.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once VPNSERVER_PLUGIN_DIR . 'admin/admin-page.php';

// Activation
register_activation_hook(__FILE__, 'vpnserver_activate_plugin');
function vpnserver_activate_plugin() {
    vpnpm_create_tables(); // or vpnserver_create_tables() if you renamed it
    flush_rewrite_rules();
}

// Admin menu
add_action('admin_menu', 'vpnserver_add_admin_menu');
function vpnserver_add_admin_menu() {
    add_menu_page(
        __('VPN Manager', 'vpnserver'),
        __('VPN Manager', 'vpnserver'),
        'manage_options',
        'vpn-manager',
        'vpnpm_admin_page',
        'dashicons-shield',
        30
    );
}

// Admin assets
add_action('admin_enqueue_scripts', 'vpnserver_admin_assets');
function vpnserver_admin_assets($hook) {
    // Ensure scripts load on our admin page and its subpages
    if (strpos((string)$hook, 'vpn-manager') === false) {
        return;
    }
    $css_rel = 'assets/css/admin.css';
    $js_rel  = 'assets/js/admin.js';
    $css_path = VPNSERVER_PLUGIN_DIR . $css_rel;
    $js_path  = VPNSERVER_PLUGIN_DIR . $js_rel;

    // If assets are missing on the server, show an admin notice and skip enqueue to avoid 404s
    if (!file_exists($css_path) || !file_exists($js_path)) {
        add_action('admin_notices', function() use ($css_path, $js_path) {
            $missing = [];
            if (!file_exists($css_path)) $missing[] = $css_path;
            if (!file_exists($js_path))  $missing[] = $js_path;
            echo '<div class="notice notice-error"><p>'
                . esc_html__('VPN Server assets are missing on the server:', 'vpnserver')
                . ' ' . esc_html(implode(', ', $missing)) . '</p></div>';
        });
        return;
    }

    // Build URLs robustly and cache-bust with file modification time
    $css_url = plugins_url($css_rel, __FILE__);
    $js_url  = plugins_url($js_rel, __FILE__);
    $css_ver = @filemtime($css_path) ?: VPNSERVER_VERSION;
    $js_ver  = @filemtime($js_path) ?: VPNSERVER_VERSION;

    wp_enqueue_style('vpnserver-admin', $css_url, [], $css_ver);
    wp_enqueue_script('vpnserver-admin', $js_url, ['jquery'], $js_ver, true);
    // Keep legacy object name for compatibility with existing JS (vpnpmAjax)
    $data = [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vpnpm-nonce'),
        'strings' => [
            'testing' => __('Testing...', 'vpnserver'),
            'tested'  => __('Tested', 'vpnserver'),
            'confirmDelete' => __('Delete this server? This cannot be undone.', 'vpnserver'),
        ],
    ];
    wp_localize_script('vpnserver-admin', 'vpnpmAjax', $data);
    // Also provide new name in case future JS expects vpnserverAjax
    wp_localize_script('vpnserver-admin', 'vpnserverAjax', $data);
}

// Add Dashboard Widget for VPN Server Manager
add_action('wp_dashboard_setup', 'vpnpm_add_dashboard_widget');
function vpnpm_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'vpnpm_dashboard_widget',
        __('VPN Server Manager', 'vpnserver'),
        'vpnpm_render_dashboard_widget'
    );
}

function vpnpm_render_dashboard_widget() {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';
    $servers = $wpdb->get_results("SELECT file_name, status, last_checked FROM {$table} ORDER BY last_checked DESC");

    if (empty($servers)) {
        echo '<p>' . esc_html__('No VPN servers found.', 'vpnserver') . '</p>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Server Name', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Status', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Last Checked', 'vpnserver') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($servers as $server) {
        $name = esc_html(pathinfo($server->file_name, PATHINFO_FILENAME));
        $status = strtolower($server->status);
        $last_checked = $server->last_checked ? esc_html($server->last_checked) : esc_html__('Never', 'vpnserver');

        $status_class = 'badge-gray';
        if ($status === 'active') {
            $status_class = 'badge-green';
        } elseif ($status === 'down') {
            $status_class = 'badge-red';
        }

        echo '<tr>';
        echo '<td>' . $name . '</td>';
        echo '<td><span class="badge ' . esc_attr($status_class) . '">' . ucfirst($status) . '</span></td>';
        echo '<td>' . $last_checked . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}
