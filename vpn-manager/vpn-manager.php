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
    if (!wp_next_scheduled('hovpnm_purge_deleted')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'hovpnm_purge_deleted');
    }
});

register_deactivation_hook(__FILE__, function() {
    \HOVPNM\Core\DB::deactivate();
    $ts = wp_next_scheduled('hovpnm_purge_deleted');
    if ($ts) wp_unschedule_event($ts, 'hovpnm_purge_deleted');
});

// Load translations at init (WordPress 6.7+ requirement)
add_action('init', function() {
    load_plugin_textdomain('hovpnm', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Load extensions based on active list
add_action('plugins_loaded', function() {
    \HOVPNM\Core\Bootstrap::load_extensions();
});

add_action('hovpnm_purge_deleted', function(){
    global $wpdb; $del_table = $wpdb->prefix . 'vpn_profiles_deleted';
    $threshold = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
    $wpdb->query($wpdb->prepare("DELETE FROM {$del_table} WHERE deleted_at < %s", $threshold));
});

// Ensure DB migrations run after updates without requiring deactivation/activation
add_action('init', function(){
    $stored = get_option('hovpnm_db_version');
    if ($stored !== HOVPNM_VERSION) {
        \HOVPNM\Core\DB::migrate();
        update_option('hovpnm_db_version', HOVPNM_VERSION);
    }
});

// One-time schema cleanup: drop legacy Check-Host columns even if version didn't change
add_action('init', function(){
    if (!get_option('hovpnm_migr_drop_checkhost_done')) {
        \HOVPNM\Core\DB::migrate();
        update_option('hovpnm_migr_drop_checkhost_done', 1);
    }
}, 20);

// Remove legacy options for deprecated ping providers
add_action('init', function(){
    delete_option('hovpnm_checkhost');
    delete_option('hovpnm_sched_sources');
});

// Custom intervals for scheduler
add_filter('cron_schedules', function($schedules){
    $schedules['five_minutes'] = ['interval' => 5*60, 'display' => __('Every 5 minutes','hovpnm')];
    $schedules['fifteen_minutes'] = ['interval' => 15*60, 'display' => __('Every 15 minutes','hovpnm')];
    $schedules['thirty_minutes'] = ['interval' => 30*60, 'display' => __('Every 30 minutes','hovpnm')];
    $schedules['six_hours'] = ['interval' => 6*HOUR_IN_SECONDS, 'display' => __('Every 6 hours','hovpnm')];
    return $schedules;
});

// Schedule/Reschedule auto-ping on settings change
add_action('update_option_hovpnm_sched_interval', function($old, $new){
    $ts = wp_next_scheduled('hovpnm_run_scheduler');
    if ($ts) wp_unschedule_event($ts, 'hovpnm_run_scheduler');
    wp_schedule_event(time() + 60, $new, 'hovpnm_run_scheduler');
}, 10, 2);

register_activation_hook(__FILE__, function(){
    $int = get_option('hovpnm_sched_interval', 'hourly');
    if (!wp_next_scheduled('hovpnm_run_scheduler')) {
        wp_schedule_event(time() + 60, $int, 'hovpnm_run_scheduler');
    }
    // Clean up legacy scheduler sources option
    delete_option('hovpnm_sched_sources');
});

add_action('hovpnm_run_scheduler', function(){
    // Server-only ping source (Check-Host removed)
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $servers = $wpdb->get_results("SELECT id FROM {$t}");
    foreach ($servers as $s) {
        $id = (int)$s->id;
        // Trigger server ping via internal hook
        do_action('hovpnm_internal_server_ping', $id);
    }
});
