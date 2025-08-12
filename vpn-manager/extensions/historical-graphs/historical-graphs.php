<?php
namespace HOVPNM\Ext\Graphs;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/rest.php';

use function HOVPNM\Core\register_server_column;

add_action('plugins_loaded', function(){
    register_server_column('graphs', __('History','hovpnm'), __NAMESPACE__ . '\\col_graphs');
});

function col_graphs($s) {
    return '<button class="button" data-graph="' . (int)$s->id . '">Charts</button>';
}
