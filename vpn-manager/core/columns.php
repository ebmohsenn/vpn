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
    // Avoid duplicating with the Server Ping extension's 'srv_ping' when active
    $active = get_option('vpnpm_active_extensions', []);
    if (!is_array($active)) $active = [];
    if (!in_array('server-ping', $active, true)) {
        register_server_column('ping', __('Ping (Server)','hovpnm'), function($s){
            if (!empty($s->ping_server_avg)) {
                return intval($s->ping_server_avg) . ' ms';
            }
            return 'N/A';
        });
    }
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
// New signature: id, icon_html, title, callback. Kept for extension authors.
function vpnpm_add_server_action($id, $icon_html, $title, $callback) { add_server_action_ex($id, $icon_html, $title, $callback); }
