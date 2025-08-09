<?php
defined('ABSPATH') || exit;

// AJAX: Test server
add_action('wp_ajax_vpnpm_test_server', 'vpnpm_ajax_test_server');
function vpnpm_ajax_test_server() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized', 'vpnpm')], 403);
    }
    check_ajax_referer('vpnpm-nonce');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        wp_send_json_error(['message' => __('Invalid ID', 'vpnpm')], 400);
    }
    $profile = vpnpm_get_profile_by_id($id);
    if (!$profile) {
        wp_send_json_error(['message' => __('Profile not found', 'vpnpm')], 404);
    }

    $host = $profile->remote_host;
    $port = (int)$profile->port ?: 1194;
    $timeout = 3;

    $status = 'down';
    $err = null;
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp) {
        $status = 'active';
        fclose($fp);
    } else {
        $err = "$errno $errstr";
    }
    vpnpm_update_status($id, $status);

    $profile = vpnpm_get_profile_by_id($id);
    wp_send_json_success([
        'id'           => $id,
        'status'       => $status,
        'last_checked' => $profile ? $profile->last_checked : current_time('mysql'),
        'latency_ms'   => (int)round((microtime(true) - $start) * 1000),
        'error'        => $err,
    ]);
}

// AJAX: Download config
add_action('wp_ajax_vpnpm_download_config', 'vpnpm_ajax_download_config');
function vpnpm_ajax_download_config() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'vpnpm'));
    }
    check_admin_referer('vpnpm-nonce');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        wp_die(__('Invalid ID.', 'vpnpm'));
    }

    $profile = vpnpm_get_profile_by_id($id);
    if (!$profile) {
        wp_die(__('Profile not found.', 'vpnpm'));
    }

    $path = vpnpm_config_file_path($id);
    if (!file_exists($path)) {
        wp_die(__('Config file not found.', 'vpnpm'));
    }

    nocache_headers();
    header('Content-Description: File Transfer');
    header('Content-Type: application/x-openvpn-profile');
    header('Content-Disposition: attachment; filename="' . basename($profile->file_name) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
