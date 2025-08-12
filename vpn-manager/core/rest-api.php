<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function() {
    register_rest_route('hovpnm/v1', '/servers', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\rest_list_servers',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);
    register_rest_route('hovpnm/v1', '/servers/(?P<id>\\d+)', [
        'methods' => 'POST',
        'callback' => __NAMESPACE__ . '\\rest_update_server',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);
});

function rest_list_servers($req) {
    return rest_ensure_response(Servers::all());
}

function rest_update_server($req) {
    $id = (int) $req['id'];
    if (!$id) return new \WP_Error('invalid_id', __('Invalid ID','hovpnm'), ['status' => 400]);
    $payload = $req->get_json_params();
    if (!is_array($payload)) $payload = [];
    $allowed = ['file_name','remote_host','port','protocol','cipher','type','label','location','notes'];
    $data = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $payload)) continue;
        $v = $payload[$k];
        switch ($k) {
            case 'port': $data[$k] = is_numeric($v) ? (int)$v : null; break;
            case 'protocol': $data[$k] = in_array(strtolower((string)$v), ['udp','tcp',''], true) ? (string)strtolower((string)$v) : null; break;
            case 'notes': $data[$k] = wp_kses_post((string)$v); break;
            case 'file_name': $data[$k] = sanitize_file_name((string)$v); break;
            default: $data[$k] = sanitize_text_field((string)$v);
        }
    }
    if (isset($data['file_name']) && $data['file_name'] === '') unset($data['file_name']);
    if (isset($data['remote_host']) && $data['remote_host'] === '') unset($data['remote_host']);
    if (!$data) return rest_ensure_response(['updated' => false]);
    Servers::update($id, $data);
    $updated = Servers::get($id);
    return rest_ensure_response(['updated' => true, 'server' => $updated]);
}
