<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin-ui.php';

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action;
use function HOVPNM\Core\add_server_action_ex;

add_action('init', function(){
    register_server_column('ch_ping', __('Ping (Check-Host.Net)','hovpnm'), __NAMESPACE__ . '\\col_ch_ping');
    add_server_action_ex('checkhost-ping', '', __('Ping (Check-Host.Net)','hovpnm'), __NAMESPACE__ . '\\action_ping');
    add_server_action_ex('more-ping', '', __('More Ping','hovpnm'), __NAMESPACE__ . '\\action_more_ping');
});

function col_ch_ping($s) {
    $val = get_transient('hovpnm_ch_ping_' . (int)$s->id);
    return $val !== false ? intval($val) . ' ms' : 'N/A';
}

function action_more_ping($server) {
    return '#'; // Placeholder; UI is handled in admin-ui.php
}

function action_ping($server) { return '#'; }

// Admin JS wiring for ping buttons via external file
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
    wp_enqueue_script('hovpnm-checkhost', HOVPNM_PLUGIN_URL . 'extensions/checkhost-ping/assets/js/checkhost.js', ['jquery'], HOVPNM_VERSION, true);
    wp_localize_script('hovpnm-checkhost', 'HOVPNM_CH', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hovpnm'),
        'msgPinging' => __('Pinging...','hovpnm'),
    'msgPing' => __('Ping (Check-Host.Net)','hovpnm'),
        'msgPingFailed' => __('Ping failed','hovpnm'),
        'msgMorePing' => __('More Ping','hovpnm'),
        'msgPingLabel' => __('Ping:','hovpnm'),
        'msgCHPing' => __('Check-Host Ping:','hovpnm'),
        'msgEditTitle' => __('Edit Server','hovpnm'),
    'msgClose' => __('Close','hovpnm'),
    'msgActive' => __('Active','hovpnm'),
    'msgDown' => __('Down','hovpnm'),
    'historyAction' => 'hovpnm_ch_history',
    ]);
});
