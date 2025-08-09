<?php
defined('ABSPATH') || exit;

function vpnpm_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'vpn_profiles';
}

function vpnpm_create_tables() {
    global $wpdb;
    $table = vpnpm_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        file_name varchar(255) NOT NULL,
        remote_host varchar(255) NOT NULL,
        port int(11) DEFAULT NULL,
        protocol varchar(20) DEFAULT NULL,
        cipher varchar(100) DEFAULT NULL,
        status varchar(20) DEFAULT 'unknown',
        notes text NULL,
        last_checked datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY remote_host (remote_host),
        KEY status (status)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function vpnpm_insert_profile($data) {
    global $wpdb;
    $table = vpnpm_table_name();

    $defaults = [
        'file_name'   => '',
        'remote_host' => '',
        'port'        => null,
        'protocol'    => null,
        'cipher'      => null,
        'status'      => 'unknown',
        'notes'       => null,
        'last_checked'=> null,
        'created_at'  => current_time('mysql')
    ];
    $data = wp_parse_args($data, $defaults);

    $inserted = $wpdb->insert(
        $table,
        $data,
        [
            '%s', // file_name
            '%s', // remote_host
            '%d', // port
            '%s', // protocol
            '%s', // cipher
            '%s', // status
            '%s', // notes
            '%s', // last_checked
            '%s', // created_at
        ]
    );

    if ($inserted) {
        return (int) $wpdb->insert_id;
    }
    return false;
}

function vpnpm_get_all_profiles() {
    global $wpdb;
    $table = vpnpm_table_name();
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
}

function vpnpm_get_profile_by_id($id) {
    global $wpdb;
    $table = vpnpm_table_name();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
}

function vpnpm_update_status($id, $status) {
    global $wpdb;
    $table = vpnpm_table_name();
    return $wpdb->update(
        $table,
        [
            'status'       => $status,
            'last_checked' => current_time('mysql'),
        ],
        ['id' => (int)$id],
        ['%s','%s'],
        ['%d']
    );
}

function vpnpm_delete_profile($id) {
    global $wpdb;
    $table = vpnpm_table_name();
    return $wpdb->delete($table, ['id' => (int)$id], ['%d']);
}
