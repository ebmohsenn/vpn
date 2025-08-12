<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin-ui.php';

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action;

add_action('plugins_loaded', function(){
    register_server_column('ch_ping', __('Ping (Check-Host)','hovpnm'), __NAMESPACE__ . '\\col_ch_ping');
    add_server_action(__('More Ping','hovpnm'), __NAMESPACE__ . '\\action_more_ping');
});

function col_ch_ping($s) {
    $val = get_transient('hovpnm_ch_ping_' . (int)$s->id);
    return $val !== false ? intval($val) . ' ms' : 'N/A';
}

function action_more_ping($server) {
    return '#'; // Placeholder; UI is handled in admin-ui.php
}
