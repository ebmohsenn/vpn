<?php
namespace HOVPNM\Ext\Failover;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/settings.php';

use HOVPNM\Core\Servers;
use function HOVPNM\Core\register_server_column;

// Register column
add_action('init', function(){
    register_server_column('failover', __('Failover','hovpnm'), __NAMESPACE__ . '\col_failover');
});

// Simple health check based on thresholds
function is_unhealthy($server) {
    $s = get_settings();
    $latency = isset($server->ping) ? (int)$server->ping : null;
    $status = isset($server->status) ? (string)$server->status : 'unknown';
    if ($status && strtolower($status) !== 'up') return true;
    if ($latency === null || $latency <= 0) return true;
    if ($latency > (int)$s['latency_threshold']) return true;
    return false;
}

function server_display_name($server) {
    $name = $server->file_name ? pathinfo($server->file_name, PATHINFO_FILENAME) : ('#' . (int)$server->id);
    return $name;
}

function compute_suggestions($server, $limit = 3) {
    $s = get_settings();
    $latency_threshold = (int)$s['latency_threshold'];
    $all = Servers::all();
    $candidates = [];
    foreach ($all as $row) {
        if ((int)$row->id === (int)$server->id) continue;
        // Prefer same protocol or type/label when available
        $score = 0;
        if (!empty($server->protocol) && $row->protocol === $server->protocol) $score += 3;
        if (!empty($server->type) && $row->type === $server->type) $score += 2;
        if (!empty($server->label) && $row->label === $server->label) $score += 1;
        $row_ping = isset($row->ping) ? (int)$row->ping : PHP_INT_MAX;
        $row_status = isset($row->status) ? strtolower($row->status) : 'unknown';
        $healthy = $row_status === 'up' && $row_ping > 0 && $row_ping <= $latency_threshold;
        if (!$healthy) continue;
        $candidates[] = [
            'server' => $row,
            'score' => $score,
        ];
    }
    // Sort by score desc then ping asc
    usort($candidates, function($a, $b){
        if ($a['score'] === $b['score']) {
            $ap = (int)$a['server']->ping; $bp = (int)$b['server']->ping;
            if ($ap === $bp) return 0; return ($ap < $bp) ? -1 : 1;
        }
        return ($a['score'] > $b['score']) ? -1 : 1;
    });
    $out = [];
    foreach ($candidates as $c) {
        $sv = $c['server'];
        $out[] = [
            'id' => (int)$sv->id,
            'name' => server_display_name($sv),
            'ping' => (int)$sv->ping,
            'location' => (string)($sv->location ?? ''),
            'remote_host' => (string)($sv->remote_host ?? ''),
            'protocol' => (string)($sv->protocol ?? ''),
            'type' => (string)($sv->type ?? ''),
            'label' => (string)($sv->label ?? ''),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

function col_failover($s) {
    $unhealthy = is_unhealthy($s);
    $status_badge = $unhealthy
        ? '<span style="color:#b32d2e">' . esc_html__('Unhealthy', 'hovpnm') . '</span>'
        : '<span style="color:#198754">' . esc_html__('Healthy', 'hovpnm') . '</span>';
    $ping = isset($s->ping) ? (int)$s->ping : 0;
    $html = $status_badge . ' <small>(' . esc_html($ping) . ' ms)</small>';
    if ($unhealthy) {
        $html .= ' <button type="button" class="button hovpnm-failover-btn" data-id="' . (int)$s->id . '">' . esc_html__('Recommend', 'hovpnm') . '</button>';
    }
    return $html;
}

// AJAX: get suggestions for server
add_action('wp_ajax_hovpnm_failover_suggestions', __NAMESPACE__ . '\ajax_suggestions');
function ajax_suggestions() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Forbidden','hovpnm')], 403);
    check_ajax_referer('hovpnm_failover', 'nonce');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) wp_send_json_error(['message' => __('Invalid server','hovpnm')], 400);
    $cache_key = 'hovpnm_failover_' . $id;
    $cached = get_transient($cache_key);
    if ($cached) {
        wp_send_json_success(['suggestions' => $cached, 'cached' => true]);
    }
    $server = Servers::get($id);
    if (!$server) wp_send_json_error(['message' => __('Not found','hovpnm')], 404);
    $sugs = compute_suggestions($server, 5);
    set_transient($cache_key, $sugs, 5 * MINUTE_IN_SECONDS);
    wp_send_json_success(['suggestions' => $sugs, 'cached' => false]);
}

// Admin script to handle button clicks
add_action('admin_enqueue_scripts', function($hook){
    // Only on HO VPN Manager dashboard
    if ($hook !== 'toplevel_page_hovpnm') return;
    $nonce = wp_create_nonce('hovpnm_failover');
    $js = 'jQuery(function($){\n'
        . ' $(document).on("click", ".hovpnm-failover-btn", function(e){ e.preventDefault(); var id=$(this).data("id"); var btn=$(this); btn.prop("disabled",true).text("' . esc_js(__('Loading...','hovpnm')) . '");\n'
        . ' $.post(ajaxurl, { action: "hovpnm_failover_suggestions", id: id, nonce: "' . esc_js($nonce) . '" }, function(res){\n'
        . '   btn.prop("disabled",false).text("' . esc_js(__('Recommend','hovpnm')) . '");\n'
        . '   if(!res || !res.success){ alert("' . esc_js(__('Failed to load recommendations','hovpnm')) . '"); return; }\n'
        . '   var list = res.data && res.data.suggestions ? res.data.suggestions : [];\n'
        . '   if(!list.length){ alert("' . esc_js(__('No suitable alternatives found right now.','hovpnm')) . '"); return; }\n'
        . '   var msg = "' . esc_js(__('Recommended alternatives:','hovpnm')) . '\n";\n'
        . '   list.forEach(function(it,idx){ msg += (idx+1)+". "+it.name+" ["+it.protocol+"] - "+it.ping+" ms - "+(it.location||"-")+"\\nHost: "+(it.remote_host||"-")+"\n\n"; });\n'
        . '   alert(msg);\n'
        . ' });\n'
        . ' });\n'
        . '});';
    wp_add_inline_script('jquery-core', $js);
});

