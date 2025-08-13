<?php
namespace HOVPNM\Ext\ServerPing;
if (!defined('ABSPATH')) { exit; }

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action_ex;

add_action('init', function(){
    register_server_column('srv_ping', __('Ping (Server)','hovpnm'), __NAMESPACE__ . '\\col_srv_ping');
    add_server_action_ex('server-ping', '', __('Ping','hovpnm'), __NAMESPACE__ . '\\action_srv_ping');
});

function col_srv_ping($s) {
    return !empty($s->ping_server_avg) ? intval($s->ping_server_avg) . ' ms' : 'N/A';
}

function action_srv_ping($server) { return '#'; }

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    wp_enqueue_script('hovpnm-server-ping', HOVPNM_PLUGIN_URL . 'extensions/server-ping/assets/js/server-ping.js', ['jquery'], HOVPNM_VERSION, true);
    wp_localize_script('hovpnm-server-ping', 'HOVPNM_SP', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hovpnm'),
        'msgPinging' => __('Pinging...','hovpnm'),
        'msgPing' => __('Ping (Server)','hovpnm'),
        'msgPingFailed' => __('Ping failed','hovpnm'),
    'msgActive' => __('Active','hovpnm'),
    'msgDown' => __('Down','hovpnm'),
    ]);
});

add_action('wp_ajax_hovpnm_srv_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    $resp = srv_compute_and_update($id);
    if (!$resp) wp_send_json_error(['message'=>'Not found'], 404);
    wp_send_json_success($resp);
});

// Reusable compute/update for server-local ping; returns payload for UI updates.
function srv_compute_and_update($id) {
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $server = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
    if (!$server) return null;
    $start = microtime(true);
    $ok = false;
    $host = $server->remote_host;
    $port = $server->port ?: 1194;
    $proto = isset($server->protocol) ? strtolower($server->protocol) : '';
    $errno = 0; $errstr = '';
    if ($proto === 'udp') {
        $fp = @stream_socket_client('udp://' . $host . ':' . $port, $errno, $errstr, 2.0);
        if ($fp) { $ok = true; fclose($fp); }
    } else {
        $fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
        if ($fp) { $ok = true; fclose($fp); }
    }
    $ms = (int) round((microtime(true) - $start) * 1000);
    $avg = $ms; $now = current_time('mysql');
    $update = [
        'ping_server_avg' => $avg,
        'ping_server_last_checked' => $now,
    ];
    if ($proto !== 'udp') { $update['status'] = $ok ? 'active' : 'down'; }
    $wpdb->update($t, $update, ['id' => $id]);
    $hist = $wpdb->prefix . 'vpn_ping_history';
    $wpdb->insert($hist, [
        'server_id' => $id,
        'source' => 'server',
        'ping_value' => $ms,
        'location' => \HOVPNM\Core\detect_location_for_host($host),
        'status' => ($proto !== 'udp') ? ($ok ? 'active' : 'down') : 'unknown',
        'timestamp' => current_time('mysql'),
    ]);
    $resp = ['id'=>$id,'ping'=>$ms];
    if ($proto !== 'udp') { $resp['status'] = $ok ? 'active' : 'down'; }
    return $resp;
}

// Internal scheduler hook
add_action('hovpnm_internal_server_ping', __NAMESPACE__ . '\\srv_compute_and_update');
