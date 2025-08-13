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
    // Store in ping history only; do not touch status or schema
    global $wpdb; $hist = $wpdb->prefix . 'vpn_ping_history';
    $host = $wpdb->get_var($wpdb->prepare('SELECT remote_host FROM ' . \HOVPNM\Core\DB::table_name() . ' WHERE id=%d', $id));
    $wpdb->insert($hist, [
        'server_id' => $id,
        'source' => 'checkhost',
        'ping_value' => $value,
        'location' => \HOVPNM\Core\detect_location_for_host($host),
        'status' => 'unknown',
        'timestamp' => current_time('mysql'),
    ]);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[HOVPNM] ch_ping id=%d ping=%d ms', $id, $value));
    }
    return [true, $value];
}

add_action('wp_ajax_hovpnm_ch_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $force = !empty($_POST['force']);
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    list($ok, $val) = ch_compute_and_update($id, $force);
    if (!$ok) { wp_send_json_error(['ping'=>null]); }
    wp_send_json_success(['ping' => (int)$val]);
});

// History endpoint (used by More Ping modal)
add_action('wp_ajax_hovpnm_ch_history', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    global $wpdb; $hist = $wpdb->prefix . 'vpn_ping_history';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT timestamp, ping_value, status, location FROM {$hist} WHERE server_id=%d AND source=%s ORDER BY timestamp DESC LIMIT 100", $id, 'checkhost'), ARRAY_A);
    wp_send_json_success(['items' => array_map(function($r){
        $r['ping_value'] = isset($r['ping_value']) ? (int)$r['ping_value'] : null; return $r; }, $rows)]);
});
