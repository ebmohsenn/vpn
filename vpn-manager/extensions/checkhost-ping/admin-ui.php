<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_hovpnm_ch_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $force = !empty($_POST['force']) ? true : false;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    $cache_key = 'hovpnm_ch_ping_' . $id;
    $opts = get_option(OPT, defaults());
    $ttl = isset($opts['cache_ttl']) ? (int)$opts['cache_ttl'] : 300;
    $value = $force ? false : get_transient($cache_key);
    if ($value === false) {
        // Placeholder: simulate an aggregated value
        $value = rand(40, 180);
        set_transient($cache_key, $value, $ttl);
    }
    // Update profile row with aggregates and status
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $now = current_time('mysql');
    $wpdb->update($t, [
        'checkhost_ping_avg' => (int)$value,
        'checkhost_last_checked' => $now,
        'status' => ($value > 0 && $value < 10000) ? 'active' : 'down',
    ], ['id' => $id]);
    wp_send_json_success(['id'=>$id,'ping'=>$value]);
});

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
