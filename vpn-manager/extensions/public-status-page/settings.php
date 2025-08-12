<?php
namespace HOVPNM\Ext\PublicStatus;
if (!defined('ABSPATH')) { exit; }

const OPT = 'hovpnm_status_page';
function defaults(){ return ['logo'=>'', 'primary_color'=>'#0055aa']; }

add_action('admin_init', function(){
    register_setting('hovpnm_status_group', OPT, [
        'type'=>'array',
        'sanitize_callback'=>__NAMESPACE__ . '\\sanitize',
        'default'=>defaults(),
    ]);
    add_settings_section('hovpnm_status_section', __('Public Status Page','hovpnm'), function(){
        echo '<p>' . esc_html__('Customize branding.', 'hovpnm') . '</p>';
    }, 'hovpnm_status');
    add_settings_field('logo', __('Logo URL','hovpnm'), __NAMESPACE__ . '\\field_logo', 'hovpnm_status', 'hovpnm_status_section');
    add_settings_field('primary_color', __('Primary Color','hovpnm'), __NAMESPACE__ . '\\field_color', 'hovpnm_status', 'hovpnm_status_section');
});

function sanitize($in){
    $o = defaults();
    $o['logo'] = isset($in['logo']) ? esc_url_raw($in['logo']) : '';
    $o['primary_color'] = isset($in['primary_color']) ? sanitize_hex_color($in['primary_color']) : '#0055aa';
    return $o;
}

function field_logo(){ $o = get_option(OPT, defaults()); echo '<input type="url" class="regular-text" name="' . esc_attr(OPT) . '[logo]" value="' . esc_attr($o['logo']) . '" />'; }
function field_color(){ $o = get_option(OPT, defaults()); echo '<input type="text" class="regular-text" name="' . esc_attr(OPT) . '[primary_color]" value="' . esc_attr($o['primary_color']) . '" />'; }

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Status Page','hovpnm'), __('Status Page','hovpnm'), 'manage_options', 'hovpnm-status', __NAMESPACE__ . '\\settings_page');
});

function settings_page(){
    echo '<div class="wrap"><h1>' . esc_html__('Status Page','hovpnm') . '</h1><form method="post" action="options.php">';
    settings_fields('hovpnm_status_group');
    do_settings_sections('hovpnm_status');
    submit_button();
    echo '</form></div>';
}
