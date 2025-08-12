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
		type varchar(20) DEFAULT 'standard',
		label varchar(20) DEFAULT 'standard',
		location varchar(191) DEFAULT NULL,
		checkhost_ping_avg int(11) DEFAULT NULL,
		checkhost_ping_json longtext NULL,
		checkhost_last_checked datetime DEFAULT NULL,
		checkhost_last_error text NULL,
		notes text NULL,
		last_checked datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY remote_host (remote_host),
		KEY status (status)
	) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);

	// History table: store ping snapshots every ~12h for up to 6 days
	$hist = $wpdb->prefix . 'vpn_ping_history';
	$sql2 = "CREATE TABLE {$hist} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		source varchar(20) NOT NULL DEFAULT 'server',
		ping int(11) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY profile_id (profile_id),
		KEY created_at (created_at)
	) $charset_collate;";
	dbDelta($sql2);
}
endif;

// Ensure new columns exist on runtime (safe no-op if already present)
if (!function_exists('vpnpm_ensure_schema')):
function vpnpm_ensure_schema() {
	global $wpdb;
	$table = vpnpm_table_name();
	// Check 'type'
	$has_type = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'type'));
	if (!$has_type) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN type varchar(20) DEFAULT 'standard' AFTER ping");
	}
	// Check 'label'
	$has_label = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'label'));
	if (!$has_label) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN label varchar(20) DEFAULT 'standard' AFTER type");
	}
	// Check 'location'
	$has_location = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'location'));
	if (!$has_location) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN location varchar(191) DEFAULT NULL AFTER label");
	}
	// Check Check-Host columns
	$has_ch_avg = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'checkhost_ping_avg'));
	if (!$has_ch_avg) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN checkhost_ping_avg int(11) DEFAULT NULL AFTER location");
	}
	$has_ch_json = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'checkhost_ping_json'));
	if (!$has_ch_json) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN checkhost_ping_json longtext NULL AFTER checkhost_ping_avg");
	}
	$has_ch_ts = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'checkhost_last_checked'));
	if (!$has_ch_ts) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN checkhost_last_checked datetime DEFAULT NULL AFTER checkhost_ping_json");
	}
	$has_ch_err = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'checkhost_last_error'));
	if (!$has_ch_err) {
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN checkhost_last_error text NULL AFTER checkhost_last_checked");
	}
}
endif;

// Insert ping history
if (!function_exists('vpnpm_insert_ping_history')):
function vpnpm_insert_ping_history($profile_id, $ping, $source = 'server') {
	global $wpdb; $hist = $wpdb->prefix . 'vpn_ping_history';
	$wpdb->insert($hist, [
		'profile_id' => (int)$profile_id,
		'source'     => substr((string)$source, 0, 20),
		'ping'       => is_null($ping) ? null : (int)$ping,
		'created_at' => current_time('mysql'),
	], ['%d','%s','%d','%s']);
	return (int)$wpdb->insert_id;
}
endif;

// Fetch last N days history aggregated per 12h slots
if (!function_exists('vpnpm_get_ping_history')):
function vpnpm_get_ping_history($profile_id, $days = 6) {
	global $wpdb; $hist = $wpdb->prefix . 'vpn_ping_history';
	$since = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - ($days * DAY_IN_SECONDS));
	// Group by 12-hour windows in site timezone by rounding to nearest half-day
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT 
			profile_id,
			source,
			FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(created_at)/(12*3600))*(12*3600)) AS slot,
			AVG(ping) AS avg_ping,
			MIN(ping) AS min_ping,
			MAX(ping) AS max_ping,
			COUNT(*) AS samples
		 FROM {$hist}
		 WHERE profile_id = %d AND created_at >= %s
		 GROUP BY profile_id, source, slot
		 ORDER BY slot DESC",
		(int)$profile_id, $since
	));
	return $rows ?: [];
}
endif;

if (!function_exists('vpnpm_insert_profile')):
function vpnpm_insert_profile($data) {
	global $wpdb;
	$table = vpnpm_table_name();

    vpnpm_ensure_schema();

	$defaults = [
		'file_name'   => '',
		'remote_host' => '',
		'port'        => null,
		'protocol'    => null,
		'cipher'      => null,
		'status'      => 'unknown',
		'type'        => 'standard',
		'label'       => 'standard',
		'location'    => null,
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
			'%s', // type
			'%s', // label
			'%s', // location
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
	vpnpm_ensure_schema();
	return $wpdb->get_results("SELECT id, file_name, remote_host, port, protocol, status, ping, type, label, location, checkhost_ping_avg, checkhost_last_checked, checkhost_last_error, last_checked FROM {$table}");
}
endif;

if (!function_exists('vpnpm_get_profile_by_id')):
function vpnpm_get_profile_by_id($id) {
	global $wpdb;
	$table = vpnpm_table_name();
	vpnpm_ensure_schema();
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

    vpnpm_ensure_schema();

	$allowed = ['file_name','remote_host','port','protocol','cipher','status','notes','type','label','location'];
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

