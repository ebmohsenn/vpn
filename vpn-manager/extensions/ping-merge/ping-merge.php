<?php
namespace HOVPNM\Ext\PingMerge;
if (!defined('ABSPATH')) { exit; }

use function HOVPNM\Core\add_server_action_ex;
use function HOVPNM\Core\remove_server_action;

add_action('init', function(){
    // Replace separate ping buttons with one combined Ping when any ping extension(s) active
    $active = get_option('vpnpm_active_extensions', []);
    if (!is_array($active)) $active = [];
    $hasServer = in_array('server-ping', $active, true);
    $hasCH = in_array('checkhost-ping', $active, true);
    if ($hasServer || $hasCH) {
        if ($hasServer) remove_server_action('server-ping');
        if ($hasCH) remove_server_action('checkhost-ping');
        remove_server_action('ping');
        add_server_action_ex('ping', '', __('Ping','hovpnm'), __NAMESPACE__ . '\action_ping');
    }
});

// Action callback placeholder to render the merged Ping button
function action_ping($server){ return '#'; }

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    wp_enqueue_script('hovpnm-ping-merge', HOVPNM_PLUGIN_URL . 'extensions/ping-merge/assets/js/ping-merge.js', ['jquery'], HOVPNM_VERSION, true);
});
