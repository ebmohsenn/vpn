<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

const OPT = 'hovpnm_checkhost';

function defaults() { return ['nodes' => [], 'cache_ttl' => 300]; }

add_action('admin_init', function(){
    register_setting('hovpnm_checkhost_group', OPT, [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
        'default' => defaults(),
    ]);
    add_settings_section('hovpnm_ch_section', __('Check-Host Settings','hovpnm'), function(){
        echo '<p>' . esc_html__('Configure nodes and caching.', 'hovpnm') . '</p>';
    }, 'hovpnm_checkhost');
    add_settings_field('nodes', __('Nodes','hovpnm'), __NAMESPACE__ . '\\field_nodes', 'hovpnm_checkhost', 'hovpnm_ch_section');
    add_settings_field('cache_ttl', __('Cache TTL (s)','hovpnm'), __NAMESPACE__ . '\\field_ttl', 'hovpnm_checkhost', 'hovpnm_ch_section');
});

function sanitize($in) {
    $out = defaults();
    $out['nodes'] = isset($in['nodes']) && is_array($in['nodes']) ? array_values(array_map('sanitize_text_field', $in['nodes'])) : [];
    $out['cache_ttl'] = isset($in['cache_ttl']) ? max(60, (int)$in['cache_ttl']) : 300;
    return $out;
}

function field_nodes() {
    $opts = get_option(OPT, defaults());
    $val = isset($opts['nodes']) && is_array($opts['nodes']) ? implode(", ", $opts['nodes']) : '';
    echo '<input type="text" class="regular-text" name="' . esc_attr(OPT) . '[nodes]" value="' . esc_attr($val) . '" placeholder="de-fra01.check-host.net, us-nyc01.check-host.net" />';
}
function field_ttl() {
    $opts = get_option(OPT, defaults());
    $val = isset($opts['cache_ttl']) ? (int)$opts['cache_ttl'] : 300;
    echo '<input type="number" name="' . esc_attr(OPT) . '[cache_ttl]" min="60" value="' . esc_attr($val) . '" />';
}

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Check-Host','hovpnm'), __('Check-Host','hovpnm'), 'manage_options', 'hovpnm-checkhost', __NAMESPACE__ . '\\settings_page');
});

function settings_page() {
    echo '<div class="wrap"><h1>' . esc_html__('Check-Host','hovpnm') . '</h1><form method="post" action="options.php">';
    settings_fields('hovpnm_checkhost_group');
    do_settings_sections('hovpnm_checkhost');
    submit_button();
    echo '</form></div>';
}
