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

add_action('wp_ajax_hovpnm_ch_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $force = !empty($_POST['force']) ? true : false;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    list($ok, $value) = ch_compute_and_update($id, $force);
    if (!$ok) { wp_send_json_error(['message' => 'Check-Host error']); }
    wp_send_json_success(['id'=>$id,'ping'=>$value]);
});

// Scheduler/internal trigger: reuse same logic; no response needed
add_action('hovpnm_internal_checkhost_ping', function($id){ ch_compute_and_update($id, true); });

// History endpoint (used by More Ping modal)
add_action('wp_ajax_hovpnm_ch_history', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    global $wpdb; $hist = $wpdb->prefix . 'vpn_ping_history';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT timestamp, ping_value, status, location, source FROM {$hist} WHERE server_id=%d ORDER BY timestamp DESC LIMIT 200", $id));
    wp_send_json_success(['items' => $rows]);
});
