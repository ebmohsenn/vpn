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
});

add_action('hovpnm_run_scheduler', function(){
    $sources = get_option('hovpnm_sched_sources', ['server','checkhost']);
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $servers = $wpdb->get_results("SELECT id FROM {$t}");
    foreach ($servers as $s) {
        $id = (int)$s->id;
        if (in_array('server',$sources,true)) {
            // Trigger server ping via AJAX-like internal call
            do_action('hovpnm_internal_server_ping', $id);
        }
        if (in_array('checkhost',$sources,true)) {
            do_action('hovpnm_internal_checkhost_ping', $id);
        }
    }
});
