<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

function option($key, $default = null) {
    $all = get_option('hovpnm_options', []);
    return isset($all[$key]) ? $all[$key] : $default;
}

function update_option_value($key, $value) {
    $all = get_option('hovpnm_options', []);
    if (!is_array($all)) $all = [];
    $all[$key] = $value;
    update_option('hovpnm_options', $all);
}

function is_extension_active($slug) {
    $active = get_option('vpnpm_active_extensions', []);
    return is_array($active) && in_array($slug, $active, true);
}

function asset_url($rel) {
    return trailingslashit(\plugin_dir_url(dirname(__DIR__))) . 'vpn-manager/core/assets/' . ltrim($rel, '/');
}
