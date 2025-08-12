<?php
namespace HOVPNM\Ext\Graphs;
if (!defined('ABSPATH')) { exit; }

const OPT = 'hovpnm_graphs';
function defaults(){ return ['enabled' => 1]; }

add_action('admin_init', function(){
    register_setting('hovpnm_graphs_group', OPT, [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
        'default' => defaults(),
    ]);
    add_settings_section('hovpnm_graphs_section', __('Graphs Settings','hovpnm'), function(){
        echo '<p>' . esc_html__('Toggle historical logging and graphs.', 'hovpnm') . '</p>';
    }, 'hovpnm_graphs');
    add_settings_field('enabled', __('Enable history','hovpnm'), __NAMESPACE__ . '\\field_enabled', 'hovpnm_graphs', 'hovpnm_graphs_section');
});

function sanitize($in){
    $out = defaults();
    $out['enabled'] = !empty($in['enabled']) ? 1 : 0;
    return $out;
}

function field_enabled(){
    $o = get_option(OPT, defaults());
    $c = !empty($o['enabled']);
    echo '<label><input type="checkbox" name="' . esc_attr(OPT) . '[enabled]" value="1" ' . checked($c, true, false) . '> ' . esc_html__('Enable logging', 'hovpnm') . '</label>';
}

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Graphs','hovpnm'), __('Graphs','hovpnm'), 'manage_options', 'hovpnm-graphs', __NAMESPACE__ . '\\settings_page');
});

function settings_page(){
    echo '<div class="wrap"><h1>' . esc_html__('Graphs','hovpnm') . '</h1><form method="post" action="options.php">';
    settings_fields('hovpnm_graphs_group');
    do_settings_sections('hovpnm_graphs');
    submit_button();
    echo '</form></div>';
}
