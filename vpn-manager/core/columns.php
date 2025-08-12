<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

// Default columns
add_action('init', function(){
    register_server_column('status', __('Status','hovpnm'), function($s){
        $st = isset($s->status) ? strtolower($s->status) : 'unknown';
        $class = $st === 'active' ? 'color:green;' : ($st === 'down' ? 'color:#a00;' : 'color:#555;');
        return '<span style="' . esc_attr($class) . '">' . esc_html(ucfirst($st)) . '</span>';
    });
    register_server_column('ping', __('Ping (Server)','hovpnm'), function($s){
        return isset($s->ping) ? intval($s->ping) . ' ms' : 'N/A';
    });
    register_server_column('type', __('Type','hovpnm'), function($s){
        $t = isset($s->type)? $s->type : 'standard';
        return esc_html(ucfirst($t));
    });
    register_server_column('location', __('Location','hovpnm'), function($s){
        return esc_html($s->location ?? '');
    });
});

// Public API re-exports
function vpnpm_register_server_column($id, $label, $callback) { register_server_column($id, $label, $callback); }
function vpnpm_add_server_action($label, $callback) { add_server_action($label, $callback); }
