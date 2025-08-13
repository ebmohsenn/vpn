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
    $host = $server->remote_host;
    $port = $server->port ?: 1194;
    $proto = isset($server->protocol) ? strtolower($server->protocol) : '';
    $errno = 0; $errstr = '';
    // Configurable timeout (seconds), default 3s, clamp 1-30
    $timeout = intval(get_option('hovpnm_server_ping_timeout', 3));
    if ($timeout < 1 || $timeout > 30) { $timeout = 3; }

    $method = 'socket';
    $ok = false; $ms = null;
    $start = microtime(true);
    if ($proto === 'udp') {
        $fp = @stream_socket_client('udp://' . $host . ':' . $port, $errno, $errstr, (float)$timeout);
        if ($fp) { $ok = true; fclose($fp); }
    } else {
        $fp = @fsockopen($host, $port, $errno, $errstr, (float)$timeout);
        if ($fp) { $ok = true; fclose($fp); }
    }
    $elapsed = (int) round((microtime(true) - $start) * 1000);
    if ($ok) { $ms = $elapsed; }

    // Fallback to exec('ping') if sockets failed
    if (!$ok && function_exists('exec')) {
        $method = 'exec-ping';
        $ms = exec_ping_ms($host, $timeout);
        $ok = is_int($ms) && $ms >= 0; // ms is int on success
        if (!$ok) { $ms = null; }
    }

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $msg = sprintf('[HOVPNM] srv_ping id=%d host=%s method=%s ms=%s errno=%d err="%s"', (int)$id, (string)$host, $method, ($ms===null?'null':(string)$ms), (int)$errno, (string)$errstr);
        error_log($msg);
    }

    $avg = $ok ? $ms : null; $now = current_time('mysql');
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
        'ping_value' => $ok ? $ms : null,
        'location' => \HOVPNM\Core\detect_location_for_host($host),
        'status' => ($proto !== 'udp') ? ($ok ? 'active' : 'down') : 'unknown',
        'timestamp' => current_time('mysql'),
    ]);
    $resp = ['id'=>$id,'ping'=>($ok ? $ms : null)];
    if ($proto !== 'udp') { $resp['status'] = $ok ? 'active' : 'down'; }
    if (!$ok && $errstr) { $resp['error'] = $errstr; }
    return $resp;
}

// Helper: attempt OS ping and parse ms (returns int ms or null)
function exec_ping_ms($host, $timeoutSec = 3) {
    $host = escapeshellarg($host);
    $ms = null; $out = [];
    $isDarwin = stripos(PHP_OS, 'Darwin') === 0;
    if ($isDarwin) {
        // macOS: no simple per-packet timeout; rely on default, single packet
        @exec("ping -c 1 " . $host . " 2>&1", $out);
    } else {
        // Linux: -c 1 (one packet), -W timeout in seconds for reply
        $to = max(1, (int)$timeoutSec);
        @exec("ping -c 1 -W " . (int)$to . " " . $host . " 2>&1", $out);
    }
    if (is_array($out)) {
        foreach ($out as $line) {
            if (preg_match('/time[=\s]([0-9]+\.?[0-9]*)\s*ms/i', $line, $m)) {
                $val = (float)$m[1];
                $ms = (int) round($val);
                break;
            }
        }
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[HOVPNM] exec_ping output: ' . implode(' | ', $out));
    }
    return $ms;
}

// Internal scheduler hook
add_action('hovpnm_internal_server_ping', __NAMESPACE__ . '\\srv_compute_and_update');
