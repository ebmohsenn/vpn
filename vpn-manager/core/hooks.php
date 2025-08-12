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
    ColumnsRegistry::$actions[] = [
        'label' => $label,
        'cb' => $callback,
    ];
}

// Hooks for extensions
// do_action('vpnpm_before_dashboard_render')
// do_action('vpnpm_after_dashboard_render')
