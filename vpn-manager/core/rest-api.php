<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function() {
    register_rest_route('hovpnm/v1', '/servers', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\rest_list_servers',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);
});

function rest_list_servers($req) {
    return rest_ensure_response(Servers::all());
}
