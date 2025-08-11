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
require_once VPNSERVER_PLUGIN_DIR . 'includes/checkhost.php';

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
    if (strpos((string)$hook, 'vpn-manager') === false && strpos((string)$hook, 'settings_page_vpn-manager-settings') === false) {
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
    $servers = $wpdb->get_results("SELECT file_name, status, last_checked, ping, checkhost_ping_avg, type, location FROM {$table} ORDER BY last_checked DESC");

    if (empty($servers)) {
        echo '<p>' . esc_html__('No VPN servers found.', 'vpnserver') . '</p>';
        return;
    }

    echo '<table class="widefat fixed striped vpnpm-dashboard-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Server Name', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Status', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Type', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Location', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Last Checked', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Ping S (ms)', 'vpnserver') . '</th>';
    echo '<th>' . esc_html__('Ping CH (ms)', 'vpnserver') . '</th>';
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
                $loc = isset($server->location) ? $server->location : '';
                echo '<td>' . esc_html($loc) . '</td>';
                $ts = $server->last_checked ? (int) strtotime($server->last_checked) : 0;
                echo '<td title="' . esc_attr($server->last_checked) . '" data-timestamp="' . esc_attr($ts) . '">' . esc_html($relative_time) . ' ago</td>';
    echo '<td>' . ($server->ping !== null ? esc_html($server->ping) . ' ms' : esc_html__('N/A', 'vpnserver')) . '</td>';
    $chv = isset($server->checkhost_ping_avg) ? $server->checkhost_ping_avg : null;
    echo '<td>' . ($chv !== null ? esc_html((int)$chv) . ' ms' : esc_html__('N/A', 'vpnserver')) . '</td>';
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
                4: (a,b) => (parseInt(a,10)||0) - (parseInt(b,10)||0), // timestamp
                5: (a,b) => (parseInt(a,10)||0) - (parseInt(b,10)||0), // server ping
                6: (a,b) => (parseInt(a,10)||0) - (parseInt(b,10)||0), // CH ping
            };
            const valueExtractors = {
                0: (row) => row.children[0].textContent.trim(),
                1: (row) => row.children[1].textContent.trim(),
                2: (row) => row.children[2].textContent.trim(),
                4: (row) => row.children[4].getAttribute("data-timestamp") || "0",
                5: (row) => parsePing(row.children[5].textContent),
                6: (row) => parsePing(row.children[6].textContent),
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
    $servers = $wpdb->get_results("SELECT id, file_name, remote_host, port, status, ping, type, location, checkhost_last_checked FROM {$table}");

    $opts = class_exists('Vpnpm_Settings') ? Vpnpm_Settings::get_settings() : [];
    $ping_source = isset($opts['ping_source']) ? $opts['ping_source'] : 'server';
    $node_str = isset($opts['checkhost_nodes']) ? (string)$opts['checkhost_nodes'] : '';
    $nodes = array_values(array_filter(array_map('trim', explode(',', $node_str))));

    // Update statuses and pings
    foreach ($servers as $server) {
        if ($ping_source === 'checkhost' && function_exists('vpnpm_checkhost_initiate_ping')) {
            // Skip frequent checks: reuse existing CH value if checked within last 5 minutes
            $recent = false;
            if (!empty($server->checkhost_last_checked)) {
                $last = strtotime($server->checkhost_last_checked);
                if ($last && (current_time('timestamp') - $last) < 5 * 60) {
                    $recent = true;
                }
            }

            // Initiate Check-Host ping and poll result with small retries
            $avg = null; $raw = null; $status = 'down';
            if (!$recent) {
                list($init, $err) = vpnpm_checkhost_initiate_ping($server->remote_host, $nodes);
                if ($init && isset($init['request_id'])) {
                    $request_id = $init['request_id'];
                    $attempts = 0; $max_attempts = 8; // ~ a few seconds total
                    do {
                        vpnpm_checkhost_rate_limit_sleep();
                        list($res, $perr) = vpnpm_checkhost_poll_result($request_id);
                        $attempts++;
                        if ($res && is_array($res)) {
                            $raw = $res;
                            $avg = vpnpm_checkhost_aggregate_ping_ms($res);
                            // Some nodes may yet be pending; break if we have at least something
                            if ($avg !== null || $attempts >= $max_attempts) {
                                break;
                            }
                        }
                    } while ($attempts < $max_attempts);
                }
                // Store results
                vpnpm_store_checkhost_result($server->id, $avg, $raw);
            } else {
                // Leave existing value as-is, fetch for telegram summary
                $avg = $wpdb->get_var($wpdb->prepare("SELECT checkhost_ping_avg FROM {$table} WHERE id = %d", $server->id));
            }
            // Also compute server-local ping for fallback and UI dual display
            $local_ping = vpnpm_get_server_ping($server->remote_host, $server->port);
            $status = ($local_ping !== false || $avg !== null) ? 'active' : 'down';
            $now = current_time('mysql');
            $wpdb->update($table, [
                'status'       => $status,
                'last_checked' => $now,
                'ping'         => $local_ping !== false ? $local_ping : null,
            ], ['id' => $server->id], ['%s','%s','%d'], ['%d']);

            $server->status = $status;
            $server->ping = $local_ping !== false ? $local_ping : null;
            $server->checkhost_ping_avg = $avg;
        } else {
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
            $server->status = $status;
            $server->ping = $ping !== false ? $ping : null;
            $server->checkhost_ping_avg = null;
        }
    }

    // Fetch updated servers for summary
    $rows = $wpdb->get_results("SELECT file_name, status, ping, checkhost_ping_avg, type, location FROM {$table}");
    $servers_arr = [];
    foreach ($rows as $row) {
        $servers_arr[] = [
            'name' => esc_html(pathinfo((string)$row->file_name, PATHINFO_FILENAME)),
            'status' => esc_html(strtolower((string)$row->status)),
            'ping' => $row->ping !== null ? (int)$row->ping : null,
            'ch_ping' => isset($row->checkhost_ping_avg) && $row->checkhost_ping_avg !== null ? (int)$row->checkhost_ping_avg : null,
            'type' => isset($row->type) ? esc_html($row->type) : 'Standard',
            'location' => isset($row->location) ? esc_html($row->location) : '',
        ];
    }

    // Respect settings: send telegram only if enabled and if any servers
    if (!empty($servers_arr) && class_exists('Vpnpm_Settings')) {
        $opts = Vpnpm_Settings::get_settings();
        if (!empty($opts['enable_telegram'])) {
            $msg = function_exists('vpnpm_format_vpn_status_message_stylish')
                ? vpnpm_format_vpn_status_message_stylish($servers_arr)
                : 'VPN Status update.';
            vpnpm_send_telegram_message($msg, null, 'MarkdownV2');
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
