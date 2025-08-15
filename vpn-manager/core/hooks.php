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

// Ping is provided by the Ping extension. Core leaves the slot free so extensions can attach.
