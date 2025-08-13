<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin-ui.php';

use function HOVPNM\Core\register_server_column;
use function HOVPNM\Core\add_server_action;
use function HOVPNM\Core\add_server_action_ex;

// Disabled per request: Check-Host features removed

function col_ch_ping($s) {
    $val = get_transient('hovpnm_ch_ping_' . (int)$s->id);
    return $val !== false ? intval($val) . ' ms' : 'N/A';
}

function action_more_ping($server) {
    return '#'; // Placeholder; UI is handled in admin-ui.php
}

function action_ping($server) { return '#'; }

// Admin JS wiring for ping buttons via external file
// Disabled per request: Check-Host scripts not enqueued
