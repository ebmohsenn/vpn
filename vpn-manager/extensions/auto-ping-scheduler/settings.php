<?php
namespace HOVPNM\Ext\Scheduler;
if (!defined('ABSPATH')) { exit; }

const OPT = 'hovpnm_scheduler';
function defaults(){ return ['enabled'=>0]; }

add_action('admin_init', function(){
    register_setting('hovpnm_scheduler_group', OPT, [
        'type'=>'array',
        'sanitize_callback'=>__NAMESPACE__ . '\\sanitize',
        'default'=>defaults(),
    ]);
    add_settings_section('hovpnm_sched_section', __('Scheduler','hovpnm'), function(){
        echo '<p>' . esc_html__('Enable periodic pings via WP-Cron.', 'hovpnm') . '</p>';
    }, 'hovpnm_scheduler');
    add_settings_field('enabled', __('Enable','hovpnm'), __NAMESPACE__ . '\\field_enabled', 'hovpnm_scheduler', 'hovpnm_sched_section');
});

function sanitize($in){ $o = defaults(); $o['enabled'] = !empty($in['enabled']) ? 1 : 0; return $o; }
function field_enabled(){ $o = get_option(OPT, defaults()); echo '<label><input type="checkbox" name="' . esc_attr(OPT) . '[enabled]" value="1" ' . checked(!empty($o['enabled']), true, false) . '> ' . esc_html__('Enable scheduler', 'hovpnm') . '</label>'; }

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Scheduler','hovpnm'), __('Scheduler','hovpnm'), 'manage_options', 'hovpnm-scheduler', __NAMESPACE__ . '\\settings_page');
});

function settings_page(){
    echo '<div class="wrap"><h1>' . esc_html__('Scheduler','hovpnm') . '</h1><form method="post" action="options.php">';
    settings_fields('hovpnm_scheduler_group');
    do_settings_sections('hovpnm_scheduler');
    submit_button();
    echo '</form></div>';
}
