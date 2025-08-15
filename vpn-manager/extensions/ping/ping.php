<?php
namespace HOVPNM\Ext\Ping;
if (!defined('ABSPATH')) { exit; }

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action_ex;

// Register column and action
add_action('init', function(){
    register_server_column('ping', __('Ping (Server)','hovpnm'), __NAMESPACE__ . '\\col_ping');
    add_server_action_ex('ping', '', __('Ping','hovpnm'), __NAMESPACE__ . '\\action_ping');
});

function col_ping($s) {
    return !empty($s->ping_server_avg) ? intval($s->ping_server_avg) . ' ms' : 'N/A';
}

function action_ping($server) { return '#'; }

// Enqueue JS on dashboard page
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    wp_enqueue_script('hovpnm-ext-ping', HOVPNM_PLUGIN_URL . 'extensions/ping/assets/js/ping.js', ['jquery'], HOVPNM_VERSION, true);
    wp_localize_script('hovpnm-ext-ping', 'HOVPNM_PING', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hovpnm'),
        'msgPinging' => __('Pinging...','hovpnm'),
        'msgPing' => __('Ping','hovpnm'),
        'msgActive' => __('Active','hovpnm'),
        'msgDown' => __('Down','hovpnm'),
    ]);
});

// AJAX handler
add_action('wp_ajax_hovpnm_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    $resp = ping_compute_and_update($id);
    if (!$resp) wp_send_json_error(['message'=>'Not found'], 404);
    wp_send_json_success($resp);
});

// Compute + update ping results, update history/status, and return response
function ping_compute_and_update($id) {
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $server = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
    if (!$server) return null;
    $host = $server->remote_host; $port = $server->port ?: 1194; $proto = isset($server->protocol) ? strtolower($server->protocol) : '';
    $timeout = (int) get_option('hovpnm_server_ping_timeout', 3); if ($timeout < 1 || $timeout > 30) { $timeout = 3; }

    $errno = 0; $err = ''; $ok = false; $ms = null; $method = 'socket';
    $start = microtime(true);
    if ($proto === 'udp') {
        $fp = @stream_socket_client('udp://' . $host . ':' . $port, $errno, $err, (float)$timeout);
        if ($fp) { $ok = true; fclose($fp); }
    } else {
        $fp = @fsockopen($host, $port, $errno, $err, (float)$timeout);
        if ($fp) { $ok = true; fclose($fp); }
    }
    $elapsed = (int) round((microtime(true) - $start) * 1000);
    if ($ok) { $ms = $elapsed; }

    if (!$ok && function_exists('exec')) {
        $method = 'exec-ping';
        $ms = os_exec_ping_ms($host, $timeout);
        $ok = is_int($ms) && $ms >= 0; if (!$ok) { $ms = null; }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[HOVPNM] ext_ping id=%d host=%s method=%s ms=%s err="%s"', $id, $host, $method, ($ms===null?'null':$ms), (string)$err));
    }

    $update = ['ping_server_avg' => ($ok ? $ms : null), 'ping_server_last_checked' => current_time('mysql')];
    if ($proto !== 'udp') { $update['status'] = $ok ? 'active' : 'down'; }
    $wpdb->update($t, $update, ['id' => $id]);

    $hist = $wpdb->prefix . 'vpn_ping_history';
    $wpdb->insert($hist, [
        'server_id' => $id,
        'source' => 'server',
        'ping_value' => $ok ? $ms : null,
        'location' => \HOVPNM\Core\detect_location_for_host($host),
        'status' => ($proto !== 'udp') ? ($ok ? 'active' : 'down') : 'unknown',
        'timestamp' => current_time('mysql'),
    ]);

    $resp = ['id' => $id, 'ping' => ($ok ? $ms : null)];
    if ($proto !== 'udp') { $resp['status'] = $ok ? 'active' : 'down'; }
    if (!$ok && $err) { $resp['error'] = $err; }
    return $resp;
}

// Parse OS ping output; return int ms or null
function os_exec_ping_ms($host, $timeoutSec = 3) {
    $host = escapeshellarg($host); $out = []; $ms = null; $isDarwin = stripos(PHP_OS, 'Darwin') === 0;
    if ($isDarwin) {
        @exec('ping -c 1 ' . $host . ' 2>&1', $out);
    } else {
        $to = max(1, (int)$timeoutSec);
        @exec('ping -c 1 -W ' . $to . ' ' . $host . ' 2>&1', $out);
    }
    foreach ((array)$out as $line) {
        if (preg_match('/time[=\s]([0-9]+\.?[0-9]*)\s*ms/i', $line, $m)) { $ms = (int) round((float)$m[1]); break; }
    }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[HOVPNM] os_exec_ping output: ' . implode(' | ', $out)); }
    return $ms;
}

// Allow scheduler to trigger pings per server id
add_action('hovpnm_internal_server_ping', __NAMESPACE__ . '\\ping_compute_and_update');
