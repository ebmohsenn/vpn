<?php
namespace HOVPNM\Ext\Telegram;
if (!defined('ABSPATH')) { exit; }

const OPT = 'hovpnm_telegram';
function defaults(){ return ['token'=>'', 'chat_ids'=>'']; }

add_action('admin_init', function(){
    register_setting('hovpnm_telegram_group', OPT, [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
        'default' => defaults(),
    ]);
    add_settings_section('hovpnm_tg_section', __('Telegram Settings','hovpnm'), function(){
        echo '<p>' . esc_html__('Configure your Telegram bot.', 'hovpnm') . '</p>';
    }, 'hovpnm_telegram');
    add_settings_field('token', __('Bot Token','hovpnm'), __NAMESPACE__ . '\\field_token', 'hovpnm_telegram', 'hovpnm_tg_section');
    add_settings_field('chat_ids', __('Chat IDs','hovpnm'), __NAMESPACE__ . '\\field_chat_ids', 'hovpnm_telegram', 'hovpnm_tg_section');
});

function sanitize($in){
    $out = defaults();
    $out['token'] = isset($in['token']) ? sanitize_text_field($in['token']) : '';
    $out['chat_ids'] = isset($in['chat_ids']) ? sanitize_text_field($in['chat_ids']) : '';
    return $out;
}

function field_token(){ $o = get_option(OPT, defaults()); echo '<input type="text" class="regular-text" name="' . esc_attr(OPT) . '[token]" value="' . esc_attr($o['token']) . '" />'; }
function field_chat_ids(){ $o = get_option(OPT, defaults()); echo '<input type="text" class="regular-text" name="' . esc_attr(OPT) . '[chat_ids]" value="' . esc_attr($o['chat_ids']) . '" placeholder="12345, -10012345" />'; }

add_action('admin_menu', function(){
    add_submenu_page('hovpnm', __('Telegram','hovpnm'), __('Telegram','hovpnm'), 'manage_options', 'hovpnm-telegram', __NAMESPACE__ . '\\settings_page');
});

function settings_page(){
    echo '<div class="wrap"><h1>' . esc_html__('Telegram','hovpnm') . '</h1><form method="post" action="options.php">';
    settings_fields('hovpnm_telegram_group');
    do_settings_sections('hovpnm_telegram');
    submit_button();
    echo '</form></div>';
}
