<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

// Column registry
class ColumnsRegistry {
    public static $cols = [];
    public static $actions = [];
}

function register_server_column($id, $label, $callback) {
    ColumnsRegistry::$cols[$id] = [
        'label' => $label,
        'cb' => $callback,
    ];
}

function remove_server_column($id) {
    unset(ColumnsRegistry::$cols[$id]);
}

function add_server_action($label, $callback) {
    // Backward compatible simple action (no icon)
    ColumnsRegistry::$actions[] = [
        'id' => sanitize_title($label),
        'label' => $label,
        'icon' => '',
        'title' => $label,
        'cb' => $callback,
    ];
}

function add_server_action_ex($id, $icon_html, $title, $callback) {
    ColumnsRegistry::$actions[] = [
        'id' => sanitize_title($id),
        'label' => $title,
        'icon' => $icon_html,
        'title' => $title,
        'cb' => $callback,
    ];
}

function remove_server_action($id) {
    $id = sanitize_title($id);
    ColumnsRegistry::$actions = array_values(array_filter(ColumnsRegistry::$actions, function($a) use ($id){
        return ($a['id'] ?? '') !== $id;
    }));
}

// Hooks for extensions
// do_action('vpnpm_before_dashboard_render')
// do_action('vpnpm_after_dashboard_render')

// Core server ping: action button and AJAX
add_action('init', function(){
    // Always have a Ping action in core
    remove_server_action('ping');
    add_server_action_ex('ping', '', __('Ping','hovpnm'), __NAMESPACE__ . '\\core_action_ping');
});

function core_action_ping($server){ return '#'; }

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    wp_enqueue_script('hovpnm-core-ping', HOVPNM_PLUGIN_URL . 'core/assets/js/core-ping.js', ['jquery'], HOVPNM_VERSION, true);
    wp_localize_script('hovpnm-core-ping', 'HOVPNM_CP', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hovpnm'),
        'msgPinging' => __('Pinging...','hovpnm'),
        'msgPing' => __('Ping','hovpnm'),
        'msgActive' => __('Active','hovpnm'),
        'msgDown' => __('Down','hovpnm'),
    ]);
});

add_action('wp_ajax_hovpnm_core_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    $resp = core_compute_and_update_ping($id);
    if (!$resp) wp_send_json_error(['message'=>'Not found'], 404);
    wp_send_json_success($resp);
});

function core_compute_and_update_ping($id){
    global $wpdb; $t = DB::table_name();
    $server = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
    if (!$server) return null;
    $host = $server->remote_host; $port = $server->port ?: 1194; $proto = isset($server->protocol)? strtolower($server->protocol):'';
    $timeout = (int) get_option('hovpnm_server_ping_timeout', 3); if($timeout<1||$timeout>30){$timeout=3;}
    $errno=0; $err=''; $ok=false; $ms=null; $method='socket';
    $start = microtime(true);
    if ($proto==='udp') {
        $fp = @stream_socket_client('udp://' . $host . ':' . $port, $errno, $err, (float)$timeout);
        if ($fp) { $ok=true; fclose($fp);}    
    } else {
        $fp = @fsockopen($host, $port, $errno, $err, (float)$timeout);
        if ($fp) { $ok=true; fclose($fp);}    
    }
    $elapsed = (int) round((microtime(true)-$start)*1000);
    if ($ok) { $ms = $elapsed; }
    if (!$ok && function_exists('exec')) {
        $method='exec-ping';
        $ms = core_exec_ping_ms($host, $timeout);
        $ok = is_int($ms) && $ms>=0; if(!$ok){ $ms=null; }
    }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log(sprintf('[HOVPNM] core_ping id=%d host=%s method=%s ms=%s err="%s"', $id, $host, $method, ($ms===null?'null':$ms), (string)$err)); }
    $update=['ping_server_avg'=>$ok?$ms:null,'ping_server_last_checked'=>current_time('mysql')];
    if ($proto!=='udp') { $update['status']=$ok?'active':'down'; }
    $wpdb->update($t, $update, ['id'=>$id]);
    $hist = $wpdb->prefix . 'vpn_ping_history';
    $wpdb->insert($hist, ['server_id'=>$id,'source'=>'server','ping_value'=>$ok?$ms:null,'location'=>detect_location_for_host($host),'status'=>($proto!=='udp')?($ok?'active':'down'):'unknown','timestamp'=>current_time('mysql')]);
    $resp=['id'=>$id,'ping'=>($ok?$ms:null)]; if($proto!=='udp'){ $resp['status']=$ok?'active':'down'; }
    return $resp;
}

function core_exec_ping_ms($host, $timeoutSec=3){
    $host = escapeshellarg($host); $out=[]; $ms=null; $isDarwin = stripos(PHP_OS, 'Darwin')===0;
    if ($isDarwin) { @exec("ping -c 1 " . $host . " 2>&1", $out); }
    else { $to=max(1,(int)$timeoutSec); @exec("ping -c 1 -W ".$to." ".$host." 2>&1", $out); }
    foreach ((array)$out as $line){ if (preg_match('/time[=\s]([0-9]+\.?[0-9]*)\s*ms/i',$line,$m)){ $ms=(int)round((float)$m[1]); break; } }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[HOVPNM] core_exec_ping output: '.implode(' | ',$out)); }
    return $ms;
}
