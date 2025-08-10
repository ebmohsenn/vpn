<?php
defined('ABSPATH') || exit;

if (!function_exists('vpnpm_table_name')):
function vpnpm_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'vpn_profiles';
}
endif;

if (!function_exists('vpnpm_create_tables')):
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
		ping int(11) DEFAULT NULL,
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
endif;

if (!function_exists('vpnpm_insert_profile')):
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
endif;

if (!function_exists('vpnpm_get_all_profiles')):
function vpnpm_get_all_profiles() {
	global $wpdb;
	$table = vpnpm_table_name();
	return $wpdb->get_results("SELECT id, file_name, remote_host, port, protocol, status, ping, last_checked FROM {$table}");
}
endif;

if (!function_exists('vpnpm_get_profile_by_id')):
function vpnpm_get_profile_by_id($id) {
	global $wpdb;
	$table = vpnpm_table_name();
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
}
endif;

if (!function_exists('vpnpm_update_status')):
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
endif;

if (!function_exists('vpnpm_delete_profile')):
function vpnpm_delete_profile($id) {
	global $wpdb;
	$table = vpnpm_table_name();
	return $wpdb->delete($table, ['id' => (int)$id], ['%d']);
}
endif;

if (!function_exists('vpnpm_update_profile')):
function vpnpm_update_profile($id, $data) {
	global $wpdb;
	$table = vpnpm_table_name();

	$allowed = ['file_name','remote_host','port','protocol','cipher','status','notes'];
	$update_data = [];
	$formats = [];
	foreach ($allowed as $key) {
		if (array_key_exists($key, $data)) {
			$update_data[$key] = $data[$key];
			if (in_array($key, ['port'])) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}
	}
	if (empty($update_data)) return false;

	return $wpdb->update($table, $update_data, ['id' => (int)$id], $formats, ['%d']);
}
endif;

