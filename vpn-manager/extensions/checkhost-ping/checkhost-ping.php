<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin-ui.php';

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action;

add_action('init', function(){
    register_server_column('ch_ping', __('Ping (Check-Host)','hovpnm'), __NAMESPACE__ . '\\col_ch_ping');
    add_server_action(__('Ping','hovpnm'), __NAMESPACE__ . '\\action_ping');
    add_server_action(__('More Ping','hovpnm'), __NAMESPACE__ . '\\action_more_ping');
});

function col_ch_ping($s) {
    $val = get_transient('hovpnm_ch_ping_' . (int)$s->id);
    return $val !== false ? intval($val) . ' ms' : 'N/A';
}

function action_more_ping($server) {
    return '#'; // Placeholder; UI is handled in admin-ui.php
}

function action_ping($server) {
    return '#'; // handled via JS
}

// Admin JS wiring for ping button
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    $nonce = wp_create_nonce('hovpnm');
    $js = 'jQuery(function($){\n'
        . ' $(document).on("click", ".button[data-action=ping]", function(e){ e.preventDefault(); var btn=$(this), id=btn.data("id"); btn.prop("disabled",true).text("' . esc_js(__('Pinging...','hovpnm')) . '");\n'
        . ' $.post(ajaxurl, { action: "hovpnm_ch_ping", id: id, _ajax_nonce: "' . esc_js($nonce) . '" }, function(res){\n'
        . '   btn.prop("disabled",false).text("' . esc_js(__('Ping','hovpnm')) . '");\n'
        . '   if(!res || !res.success){ alert("' . esc_js(__('Ping failed','hovpnm')) . '"); return; }\n'
        . '   var ping=parseInt(res.data.ping,10);\n'
        . '   // Update Check-Host Ping column if present\n'
        . '   var row=btn.closest("tr");\n'
        . '   row.find("td").each(function(){ if($(this).text().match(/Ping \(Check-Host\)/i)) return; });\n'
        . '   // Since we don\'t have column labels per cell, just set any cell containing N/A near end when appropriate is tricky. Simpler: set a data attribute for CH ping cell.\n'
        . '   // For now, show a toast and reload to re-render columns accurately.\n'
        . '   alert("' . esc_js(__('Ping:','hovpnm')) . ' "+ping+" ms");\n'
        . '   setTimeout(function(){ location.reload(); }, 200);\n'
        . ' });\n'
        . ' });\n'
        . '});';
    wp_add_inline_script('jquery-core', $js);
});
