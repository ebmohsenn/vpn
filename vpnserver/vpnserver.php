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
// Optional: define these in wp-config.php or here to enable Telegram notifications
// define('VPNSERVER_TELEGRAM_BOT_TOKEN', '123456789:ABCDEF...');
// define('VPNSERVER_TELEGRAM_CHAT_ID', '123456789');
if (!defined('VPNSERVER_TELEGRAM_CHAT_ID')) {
    define('VPNSERVER_TELEGRAM_CHAT_ID', '187417516'); // default chat ID provided by user
}

// Includes
require_once VPNSERVER_PLUGIN_DIR . 'includes/db-functions.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/parser.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/helpers.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/class-vpn-settings.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once VPNSERVER_PLUGIN_DIR . 'admin/admin-page.php';
require_once VPNSERVER_PLUGIN_DIR . 'includes/vpn-telegram-functions.php';

// Telegram functions are now loaded from includes/vpn-telegram-functions.php

// Activation
register_activation_hook(__FILE__, 'vpnserver_activate_plugin');
function vpnserver_activate_plugin() {
    vpnpm_create_tables(); // or vpnserver_create_tables() if you renamed it
    // Initialize settings and schedule cron based on defaults
    if (class_exists('Vpnpm_Settings')) {
        $settings = new Vpnpm_Settings();
        Vpnpm_Settings::maybe_schedule(true);
    }
    flush_rewrite_rules();
}
// Ensure settings class is instantiated (in case activation not just run)
if (class_exists('Vpnpm_Settings')) { new Vpnpm_Settings(); }

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
    // Ensure schema has the 'type' column
    if (function_exists('vpnpm_ensure_schema')) { vpnpm_ensure_schema(); }
    $servers = $wpdb->get_results("SELECT file_name, status, last_checked, ping, type FROM {$table} ORDER BY last_checked DESC");

    if (empty($servers)) {
        echo '<p>' . esc_html__('No VPN servers found.', 'vpnserver') . '</p>';
        return;
    }

    echo '<table class="widefat fixed striped vpnpm-dashboard-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Server Name', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Status', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Type', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Last Checked', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Ping (ms)', 'vpnserver') . '</th>';
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

        $relative_time = human_time_diff(strtotime($server->last_checked), current_time('timestamp'));

    $type = isset($server->type) ? strtolower($server->type) : 'standard';
                echo '<tr>';
    echo '<td>' . $name . '</td>';
    echo '<td><span class="badge ' . esc_attr($status_class) . '">' . ucfirst($status) . '</span></td>';
                echo '<td>' . esc_html(ucfirst($type)) . '</td>';
                $ts = $server->last_checked ? (int) strtotime($server->last_checked) : 0;
                echo '<td title="' . esc_attr($server->last_checked) . '" data-timestamp="' . esc_attr($ts) . '">' . esc_html($relative_time) . ' ago</td>';
    echo '<td>' . ($server->ping !== null ? esc_html($server->ping) . ' ms' : esc_html__('N/A', 'vpnserver')) . '</td>';
    echo '</tr>';
    }

    echo '</tbody></table>';

        // Inline script to enable sorting by clicking on header cells
        echo '<script>(function(){
            const table = document.querySelector(".vpnpm-dashboard-table");
            if (!table) return;
            const tbody = table.querySelector("tbody");
            const getStatusOrder = (txt) => { txt = (txt||"").toLowerCase(); if (txt === "active") return 0; if (txt === "unknown") return 1; if (txt === "down") return 2; return 3; };
            const getTypeOrder = (txt) => { txt = (txt||"").toLowerCase(); return txt === "premium" ? 0 : 1; };
            const parsePing = (txt) => { const m = (txt||"").match(/\d+/); return m ? parseInt(m[0],10) : Number.POSITIVE_INFINITY; };
            const comparers = {
                0: (a,b) => a.localeCompare(b, undefined, {sensitivity:"base"}),
                1: (a,b) => getStatusOrder(a) - getStatusOrder(b),
                2: (a,b) => getTypeOrder(a) - getTypeOrder(b),
                3: (a,b) => (parseInt(a,10)||0) - (parseInt(b,10)||0),
                4: (a,b) => (a===Infinity?Number.POSITIVE_INFINITY:a) - (b===Infinity?Number.POSITIVE_INFINITY:b)
            };
            const valueExtractors = {
                0: (row) => row.children[0].textContent.trim(),
                1: (row) => row.children[1].textContent.trim(),
                2: (row) => row.children[2].textContent.trim(),
                3: (row) => row.children[3].getAttribute("data-timestamp") || "0",
                4: (row) => parsePing(row.children[4].textContent)
            };
            const setAria = (ths, activeIdx, dir) => {
                ths.forEach((th,i)=>{
                    if (i===activeIdx){ th.setAttribute("aria-sort", dir>0?"ascending":"descending"); }
                    else { th.removeAttribute("aria-sort"); }
                    th.style.cursor = "pointer";
                });
            };
            const ths = Array.from(table.querySelectorAll("thead th"));
            ths.forEach((th, idx) => {
                th.addEventListener("click", function(){
                    const dir = th.dataset.sortDir === "asc" ? -1 : 1; // toggle
                    ths.forEach(t=>delete t.dataset.sortDir);
                    th.dataset.sortDir = dir === 1 ? "asc" : "desc";
                    setAria(ths, idx, dir);
                    const rows = Array.from(tbody.querySelectorAll("tr"));
                    const getVal = valueExtractors[idx] || ((r)=>r.children[idx].textContent.trim());
                    const cmp = comparers[idx] || comparers[0];
                    rows.sort((ra, rb) => {
                        const va = getVal(ra); const vb = getVal(rb);
                        const res = cmp(va, vb);
                        return dir * res;
                    });
                    // Re-append in new order
                    const frag = document.createDocumentFragment();
                    rows.forEach(r=>frag.appendChild(r));
                    tbody.appendChild(frag);
                });
            });
        })();</script>';
}

// Add custom cron interval for 10 minutes
add_filter('cron_schedules', 'vpnpm_add_cron_interval');
function vpnpm_add_cron_interval($schedules) {
    $schedules['fifteen_minutes'] = [
        'interval' => 600, // 10 minutes in seconds
        'display'  => __('Every 10 Minutes', 'vpnserver')
    ];
    return $schedules;
}

// Schedule the cron event on plugin activation
register_activation_hook(__FILE__, 'vpnpm_schedule_cron');
function vpnpm_schedule_cron() {
    if (!wp_next_scheduled('vpnpm_test_all_servers_cron')) {
        wp_schedule_event(time(), 'ten_minutes', 'vpnpm_test_all_servers_cron');
    }
}

// Clear the cron event on plugin deactivation
register_deactivation_hook(__FILE__, 'vpnpm_clear_cron');
function vpnpm_clear_cron() {
    $timestamp = wp_next_scheduled('vpnpm_test_all_servers_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'vpnpm_test_all_servers_cron');
    }
}

// Hook the cron event to the function
add_action('vpnpm_test_all_servers_cron', 'vpnpm_test_all_servers');
function vpnpm_test_all_servers() {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';
    $servers = $wpdb->get_results("SELECT id, remote_host, port FROM {$table}");

    $lines = [];
    foreach ($servers as $server) {
        $ping = vpnpm_get_server_ping($server->remote_host, $server->port);
        $status = $ping !== false ? 'active' : 'down';
        $now = current_time('mysql');

        $wpdb->update(
            $table,
            [
                'status'       => $status,
                'last_checked' => $now,
                'ping'         => $ping !== false ? $ping : null,
            ],
            ['id' => $server->id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        $lines[] = sprintf('#%d | status: %s | ping: %s', (int) $server->id, $status, ($ping !== false ? ($ping . ' ms') : 'N/A'));
    }

    // Respect settings: send telegram only if enabled and if any lines
    if (!empty($lines) && class_exists('Vpnpm_Settings')) {
        $opts = Vpnpm_Settings::get_settings();
        if (!empty($opts['enable_telegram'])) {
            $title = 'VPN Status Update - ' . date_i18n('Y-m-d H:i');
            $summary = $title . "\n" . implode("\n", $lines);

            // If multiple chat IDs configured, temporarily override constant-based chat ID
            $idsCsv = isset($opts['telegram_chat_ids']) ? (string) $opts['telegram_chat_ids'] : '';
            $ids = array_filter(array_map('trim', explode(',', $idsCsv)));
            if ($ids) {
                foreach ($ids as $id) {
                    vpnpm_send_telegram_message($summary, $id);
                }
            } else {
                vpnpm_send_telegram_message($summary);
            }
        }
    }
}

// Helper function to get server ping
define('VPNPM_PING_TIMEOUT', 3); // Timeout in seconds
function vpnpm_get_server_ping($host, $port) {
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, VPNPM_PING_TIMEOUT);
    if (!$fp) {
        return false;
    }
    fclose($fp);
    return round((microtime(true) - $start) * 1000); // Return ping in milliseconds
}
