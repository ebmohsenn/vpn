<?php
defined('ABSPATH') || exit;

function vpnpm_insert_profile($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';

    $wpdb->insert($table, [
        'file_name' => $data['file_name'],
        'remote_host' => $data['remote_host'],
        'port' => $data['port'],
        'protocol' => $data['protocol'],
        'status' => 'unknown',
    ]);
}

function vpnpm_get_all_profiles() {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';

    return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
}

function vpnpm_update_status($id, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';

    $wpdb->update(
        $table,
        [
            'status' => $status,
            'last_checked' => current_time('mysql')
        ],
        ['id' => $id]
    );
}
function vpnpm_get_profile_by_id($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
}

function vpnpm_update_profile($id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';

    $wpdb->update(
        $table,
        [
            'file_name' => $data['file_name'],
            'remote_host' => $data['remote_host'],
            'port' => $data['port'],
            'protocol' => $data['protocol'],
            'notes' => $data['notes'],
        ],
        ['id' => $id]
    );
}
function vpnpm_delete_profile($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';
    return $wpdb->delete($table, ['id' => $id], ['%d']);
}