<?php
namespace HOVPNM\Ext\Scheduler;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/settings.php';

add_action('init', __NAMESPACE__ . '\\maybe_schedule');
add_action('hovpnm_cron_ping', __NAMESPACE__ . '\\run_ping');

function maybe_schedule() {
    $o = get_option(OPT, defaults());
    $enabled = !empty($o['enabled']);
    if ($enabled && !wp_next_scheduled('hovpnm_cron_ping')) {
        wp_schedule_event(time() + 60, 'hourly', 'hovpnm_cron_ping');
    }
    if (!$enabled && ($ts = wp_next_scheduled('hovpnm_cron_ping'))) {
        wp_unschedule_event($ts, 'hovpnm_cron_ping');
    }
}

function run_ping() {
    // Placeholder: perform pings and maybe update history + alerts
    do_action('hovpnm_alert', 'Ping Run', 'Scheduled ping run completed.');
}
