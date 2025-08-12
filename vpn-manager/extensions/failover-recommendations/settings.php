<?php
namespace HOVPNM\Ext\Failover;
if (!defined('ABSPATH')) { exit; }

// Settings: thresholds for deciding a failover recommendation
add_action('admin_init', function(){
    register_setting('hovpnm_failover', 'hovpnm_failover_settings');
    add_settings_section('hovpnm_failover_main', __('Failover Thresholds','hovpnm'), function(){
        echo '<p>' . esc_html__('Recommend alternatives when a server seems unhealthy.', 'hovpnm') . '</p>';
    }, 'hovpnm_failover');

    add_settings_field('latency_threshold', __('Latency threshold (ms)','hovpnm'), __NAMESPACE__ . '\\field_latency', 'hovpnm_failover', 'hovpnm_failover_main');
    add_settings_field('loss_threshold', __('Packet loss threshold (%)','hovpnm'), __NAMESPACE__ . '\\field_loss', 'hovpnm_failover', 'hovpnm_failover_main');
});

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Failover','hovpnm'), __('Failover','hovpnm'), 'manage_options', 'hovpnm-failover', __NAMESPACE__ . '\\render_settings');
});

function defaults(){
    return [
        'latency_threshold' => 300,
        'loss_threshold' => 50,
    ];
}

function get_settings(){
    $s = get_option('hovpnm_failover_settings', []);
    return wp_parse_args($s, defaults());
}

function field_latency(){
    $s = get_settings();
    echo '<input type="number" min="0" step="10" name="hovpnm_failover_settings[latency_threshold]" value="' . esc_attr($s['latency_threshold']) . '" />';
}
function field_loss(){
    $s = get_settings();
    echo '<input type="number" min="0" max="100" step="1" name="hovpnm_failover_settings[loss_threshold]" value="' . esc_attr($s['loss_threshold']) . '" />';
}

function render_settings(){
    echo '<div class="wrap"><h1>' . esc_html__('Failover Recommendations','hovpnm') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('hovpnm_failover');
    do_settings_sections('hovpnm_failover');
    submit_button();
    echo '</form></div>';
}
