<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

// Internal function to compute/update Check-Host ping. Returns [ok, value] where ok=false on error; never updates status.
function ch_compute_and_update($id, $force = false) {
    $id = (int)$id; if (!$id) return [false, null];
    $cache_key = 'hovpnm_ch_ping_' . $id;
    $opts = get_option(OPT, defaults());
    $ttl = isset($opts['cache_ttl']) ? (int)$opts['cache_ttl'] : 300;
    $value = $force ? false : get_transient($cache_key);
    if ($value === false) {
        // Placeholder: simulate an aggregated value; replace with real API call in production
        $value = rand(40, 180);
        set_transient($cache_key, $value, $ttl);
    }
    // Validate value: numeric and realistic (1..1000 ms)
    if (!is_numeric($value)) return [false, null];
    $value = (int)$value;
    if ($value <= 0 || $value > 1000) return [false, null];
    // Update aggregates only (do not touch status)
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $now = current_time('mysql');
    $wpdb->update($t, [
        'checkhost_ping_avg' => $value,
        'checkhost_last_checked' => $now,
    ], ['id' => $id]);
    return [true, $value];
}

// Disabled per request: no Check-Host AJAX or scheduler hooks

// History endpoint (used by More Ping modal)
// Disabled per request: no history endpoint
