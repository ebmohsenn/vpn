<?php
namespace HOVPNM\Ext\ServerPing;
if (!defined('ABSPATH')) { exit; }

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action_ex;

add_action('init', function(){
    register_server_column('srv_ping', __('Ping (Server)','hovpnm'), __NAMESPACE__ . '\\col_srv_ping');
    add_server_action_ex('server-ping', '<span class="dashicons dashicons-rss"></span>', __('Ping (Server)','hovpnm'), __NAMESPACE__ . '\\action_srv_ping');
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
    ]);
});

add_action('wp_ajax_hovpnm_srv_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $server = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
    if (!$server) wp_send_json_error(['message'=>'Not found'], 404);
    // Simple HEAD request as a placeholder for real ping to OpenVPN server
    $start = microtime(true);
    $ok = false;
    $host = $server->remote_host;
    $port = $server->port ?: 1194;
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
    if ($fp) { $ok = true; fclose($fp); }
    $ms = (int) round((microtime(true) - $start) * 1000);
    // Update aggregates
    $avg = $ms; $now = current_time('mysql');
    $wpdb->update($t, [
        'ping_server_avg' => $avg,
        'ping_server_last_checked' => $now,
        'status' => $ok ? 'active' : 'down',
    ], ['id' => $id]);
    // Insert into history
    $hist = $wpdb->prefix . 'vpn_ping_history';
    $wpdb->insert($hist, [
        'server_id' => $id,
        'source' => 'server',
        'ping_value' => $ms,
        'location' => \HOVPNM\Core\detect_location_for_host($host),
        'status' => $ok ? 'active' : 'down',
        'timestamp' => current_time('mysql'),
    ]);
    wp_send_json_success(['id'=>$id,'ping'=>$ms]);
});
