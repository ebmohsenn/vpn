<?php
defined('ABSPATH') || exit;

// AJAX: Send test Telegram message
if (!function_exists('vpnpm_ajax_send_telegram_test')):
add_action('wp_ajax_vpnpm_send_telegram_test', 'vpnpm_ajax_send_telegram_test');
function vpnpm_ajax_send_telegram_test() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');

       global $wpdb;
		 $table = $wpdb->prefix . 'vpn_profiles';
	 $rows = $wpdb->get_results("SELECT file_name, status, ping, checkhost_ping_avg, type, location FROM {$table} ORDER BY id ASC");
       $servers_arr = [];
       foreach ((array)$rows as $row) {
		$servers_arr[] = [
		       'name' => esc_html(pathinfo((string)$row->file_name, PATHINFO_FILENAME)),
		       'status' => esc_html(strtolower((string)$row->status)),
					'ping' => $row->ping !== null ? (int)$row->ping : null,
				'ch_ping' => isset($row->checkhost_ping_avg) && $row->checkhost_ping_avg !== null ? (int)$row->checkhost_ping_avg : null,
			'type' => isset($row->type) ? esc_html($row->type) : 'Standard',
			'location' => isset($row->location) ? esc_html($row->location) : '',
	       ];
       }
       $msg = function_exists('vpnpm_format_vpn_status_message_stylish')
	       ? vpnpm_format_vpn_status_message_stylish($servers_arr)
	       : 'VPN Status (Test)';
       $error = null;
       $ok = false;
       if (function_exists('vpnpm_send_telegram_message')) {
	       $ok = vpnpm_send_telegram_message($msg, null, 'MarkdownV2', $error);
       }
       if ($ok) {
	       wp_send_json_success(['message' => __('Telegram message sent.', 'vpnserver')]);
       }
       $errMsg = $error ? $error : __('Telegram not configured or send failed.', 'vpnserver');
       wp_send_json_error(['message' => $errMsg]);
}
endif;

// AJAX: List Check-Host nodes (static curated list for convenience)
if (!function_exists('vpnpm_ajax_list_checkhost_nodes')):
add_action('wp_ajax_vpnpm_list_checkhost_nodes', 'vpnpm_ajax_list_checkhost_nodes');
function vpnpm_ajax_list_checkhost_nodes() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');
	// Since Check-Host does not provide an open directory of nodes, offer a configurable sample list.
	// Admins can paste their own hostnames in settings as needed. These are examples and may change.
	$nodes = function_exists('vpnpm_checkhost_load_nodes') ? vpnpm_checkhost_load_nodes() : (function_exists('vpnpm_checkhost_curated_nodes') ? vpnpm_checkhost_curated_nodes() : []);
	wp_send_json_success(['nodes' => $nodes]);
}
endif;

// AJAX: Get Check-Host detailed results for a server
if (!function_exists('vpnpm_ajax_get_checkhost_details')):
add_action('wp_ajax_vpnpm_get_checkhost_details', 'vpnpm_ajax_get_checkhost_details');
function vpnpm_ajax_get_checkhost_details() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');

	$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
	if (!$id) {
		wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	}
	global $wpdb; $table = $wpdb->prefix . 'vpn_profiles';
	$row = $wpdb->get_row($wpdb->prepare("SELECT file_name, checkhost_ping_json, checkhost_last_checked, checkhost_last_error, checkhost_ping_avg FROM {$table} WHERE id = %d", $id));
	if (defined('WP_DEBUG') && WP_DEBUG && $row) {
		error_log('[vpnserver] Stored Check-Host ping avg: ' . $row->checkhost_ping_avg);
		error_log('[vpnserver] Stored Check-Host last error: ' . $row->checkhost_last_error);
		error_log('[vpnserver] Stored Check-Host raw JSON: ' . substr((string)$row->checkhost_ping_json, 0, 200));
	}
	if (!$row) {
		wp_send_json_error(['message' => __('Profile not found', 'vpnserver')], 404);
	}
	if (empty($row->checkhost_ping_json)) {
		wp_send_json_error(['message' => __('No Check-Host data available yet.', 'vpnserver')]);
	}
	$raw = json_decode((string)$row->checkhost_ping_json, true);
	if (!is_array($raw)) {
		wp_send_json_error(['message' => __('Invalid stored data.', 'vpnserver')]);
	}
	$nodes = function_exists('vpnpm_checkhost_parse_nodes') ? vpnpm_checkhost_parse_nodes($raw) : [];
	$payload = [
		'server' => pathinfo((string)$row->file_name, PATHINFO_FILENAME),
		'updated' => $row->checkhost_last_checked ? (string)$row->checkhost_last_checked : null,
		'nodes' => $nodes,
	];
	if (!empty($row->checkhost_last_error)) {
		$payload['error'] = (string)$row->checkhost_last_error;
	}
	wp_send_json_success($payload);
}
endif;

// AJAX: Clear Check-Host cooldown so tests can resume immediately
if (!function_exists('vpnpm_ajax_clear_checkhost_cooldown')):
add_action('wp_ajax_vpnpm_clear_checkhost_cooldown', 'vpnpm_ajax_clear_checkhost_cooldown');
function vpnpm_ajax_clear_checkhost_cooldown() {
	check_ajax_referer('vpnpm-nonce');
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	delete_transient('vpnpm_checkhost_cooldown');
	wp_send_json_success(['message' => __('Cooldown cleared. You can retry Check-Host now.', 'vpnserver')]);
}
endif;

// AJAX: Test server
if (!function_exists('vpnpm_ajax_test_server')):
add_action('wp_ajax_vpnpm_test_server', 'vpnpm_ajax_test_server');
function vpnpm_ajax_test_server() {
	if (!current_user_can('manage_options')) {
	wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');

	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if (!$id) {
	wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	}
	$profile = vpnpm_get_profile_by_id($id);
	if (!$profile) {
	wp_send_json_error(['message' => __('Profile not found', 'vpnserver')], 404);
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
	$ping_ms = (int)round((microtime(true) - $start) * 1000);
	global $wpdb;
	$table = $wpdb->prefix . 'vpn_profiles';
	$wpdb->update(
		$table,
		[
			'status'       => $status,
			'ping'         => $ping_ms,
			'last_checked' => current_time('mysql')
		],
		['id' => $id],
		['%s', '%d', '%s'],
		['%d']
	);
	if (function_exists('vpnpm_insert_ping_history')) {
		vpnpm_insert_ping_history($id, $ping_ms, 'server');
	}
	// Location update: if empty or auto-update enabled
	$opts = function_exists('Vpnpm_Settings::get_settings') ? Vpnpm_Settings::get_settings() : (class_exists('Vpnpm_Settings') ? Vpnpm_Settings::get_settings() : []);
	$auto_update = !empty($opts['auto_update_location']);
	$current_location = isset($profile->location) ? trim($profile->location) : '';
	if ($auto_update || $current_location === '') {
		if (function_exists('vpnpm_update_server_location')) {
			vpnpm_update_server_location($id, $host);
		}
	}
	wp_send_json_success([
		'id'           => $id,
		'status'       => $status,
		'last_checked' => current_time('mysql'),
		'latency_ms'   => $ping_ms,
		'ping'         => $ping_ms,
		'error'        => $err,
	]);
}
endif;

// AJAX: Test Check-Host ping for a single server and update cache
if (!function_exists('vpnpm_ajax_test_server_checkhost')):
add_action('wp_ajax_vpnpm_test_server_checkhost', 'vpnpm_ajax_test_server_checkhost');
function vpnpm_ajax_test_server_checkhost() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');

	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if (!$id) {
		wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	}
	$profile = vpnpm_get_profile_by_id($id);
	if (!$profile) {
		wp_send_json_error(['message' => __('Profile not found', 'vpnserver')], 404);
	}
	if (!function_exists('vpnpm_checkhost_initiate_ping')) {
		wp_send_json_error(['message' => __('Check-Host integration not available.', 'vpnserver')], 500);
	}
	// Use selected official nodes from settings (array of hostnames)
	$nodes = get_option('vpnsm_checkhost_nodes', []);
	if (!is_array($nodes) || empty($nodes)) {
		$nodes = ['de-fra01.check-host.net', 'us-nyc01.check-host.net']; // fallback
	}

	// Initiate and poll
	list($init, $err) = vpnpm_checkhost_initiate_ping($profile->remote_host, $nodes);
	$avg = null; $raw = null;
	if ($init && isset($init['request_id'])) {
		$request_id = $init['request_id'];
		$attempts = 0; $max_attempts = 8;
		do {
			vpnpm_checkhost_rate_limit_sleep();
			list($res, $perr) = vpnpm_checkhost_poll_result($request_id);
			$attempts++;
			if ($res && is_array($res)) {
				$raw = $res;
				$avg = vpnpm_checkhost_aggregate_ping_ms($res);
				if ($avg !== null || $attempts >= $max_attempts) {
					break;
				}
			}
		} while ($attempts < $max_attempts);
	} else {
		// Initiation failed
		if (function_exists('vpnpm_store_checkhost_error')) {
			vpnpm_store_checkhost_error($id, $err ?: 'Check-Host init failed');
		}
	}
	if ($avg !== null || $raw !== null) {
		vpnpm_store_checkhost_result($id, $avg, $raw);
		if (!is_null($avg) && function_exists('vpnpm_insert_ping_history')) {
			vpnpm_insert_ping_history($id, (int)$avg, 'checkhost');
		}
	} elseif (function_exists('vpnpm_store_checkhost_error')) {
		vpnpm_store_checkhost_error($id, isset($perr) && $perr ? $perr : ($err ?: 'No data from Check-Host'));
	}

	global $wpdb; $table = $wpdb->prefix . 'vpn_profiles';
	$row2 = $wpdb->get_row($wpdb->prepare("SELECT checkhost_last_checked, checkhost_last_error FROM {$table} WHERE id = %d", $id));
	$ch_ts = $row2 ? $row2->checkhost_last_checked : null;
	$ch_err = $row2 ? $row2->checkhost_last_error : null;

	wp_send_json_success([
		'id' => $id,
		'ch_ping' => $avg !== null ? (int)$avg : null,
		'ch_last_checked' => $ch_ts,
		'ch_error' => $ch_err,
	]);
}
endif;

// AJAX: Download config
if (!function_exists('vpnpm_ajax_download_config')):
add_action('wp_ajax_vpnpm_download_config', 'vpnpm_ajax_download_config');
function vpnpm_ajax_download_config() {
	if (!current_user_can('manage_options')) {
	wp_die(__('Unauthorized', 'vpnserver'));
	}
	check_admin_referer('vpnpm-nonce');

	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if (!$id) {
		wp_die(__('Invalid ID.', 'vpnserver'));
	}

	$profile = vpnpm_get_profile_by_id($id);
	if (!$profile) {
		wp_die(__('Profile not found.', 'vpnserver'));
	}

	$path = vpnpm_config_file_path($id);
	if (!file_exists($path)) {
		wp_die(__('Config file not found.', 'vpnserver'));
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
endif;

// AJAX: Delete profile
if (!function_exists('vpnpm_ajax_delete_profile')):
add_action('wp_ajax_vpnpm_delete_profile', 'vpnpm_ajax_delete_profile');
function vpnpm_ajax_delete_profile() {
	if (!current_user_can('manage_options')) {
	wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');

	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if (!$id) {
		wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	}

	// Delete file if exists
	vpnpm_delete_config_file($id);

	// Delete DB row
	$deleted = vpnpm_delete_profile($id);
	if ($deleted === false) {
		wp_send_json_error(['message' => __('Failed to delete profile.', 'vpnserver')]);
	}

	wp_send_json_success(['id' => $id]);
}
endif;

// AJAX: Get profile (for edit modal)
if (!function_exists('vpnpm_ajax_get_profile')):
add_action('wp_ajax_vpnpm_get_profile', 'vpnpm_ajax_get_profile');
function vpnpm_ajax_get_profile() {
	if (!current_user_can('manage_options')) {
	wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if (!$id) wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	$p = vpnpm_get_profile_by_id($id);
	if (!$p) wp_send_json_error(['message' => __('Not found', 'vpnserver')], 404);

	wp_send_json_success([
		'id' => (int)$p->id,
		'file_name' => $p->file_name,
		'remote_host' => $p->remote_host,
		'port' => (int)$p->port,
		'protocol' => $p->protocol,
		'cipher' => $p->cipher,
		'status' => $p->status,
		'notes' => $p->notes,
		'label' => $p->label ?? 'standard', // Add label field
		'type' => $p->type ?? 'standard',
		'location' => $p->location ?? '',
		'last_checked' => $p->last_checked,
	]);
}
endif;

// AJAX: Update profile (edit modal save)
if (!function_exists('vpnpm_ajax_update_profile')):
add_action('wp_ajax_vpnpm_update_profile', 'vpnpm_ajax_update_profile');
function vpnpm_ajax_update_profile() {
	if (!current_user_can('manage_options')) {
	wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');
    $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'standard';
    if (!in_array(strtolower($type), ['standard','premium'], true)) {
        $type = 'standard';
    }
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if (!$id) wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);

	$data = [
		'remote_host' => isset($_POST['remote_host']) ? sanitize_text_field(wp_unslash($_POST['remote_host'])) : null,
		'port'        => isset($_POST['port']) ? (int)$_POST['port'] : null,
		'protocol'    => isset($_POST['protocol']) ? sanitize_text_field(wp_unslash($_POST['protocol'])) : null,
		'cipher'      => isset($_POST['cipher']) ? sanitize_text_field(wp_unslash($_POST['cipher'])) : null,
		'status'      => isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : null,
		'notes'       => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : null,
		'label'       => isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : 'standard', // Add label field
		'type'        => $type,
		'location'    => isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : null,
	];
	// remove nulls
	$data = array_filter($data, function($v){ return $v !== null; });
	if (isset($data['protocol']) && !in_array(strtolower($data['protocol']), ['tcp','udp'], true)) {
		unset($data['protocol']);
	}
	if (isset($data['status']) && !in_array(strtolower($data['status']), ['active','down','unknown'], true)) {
		$data['status'] = 'unknown';
	}

	$ok = vpnpm_update_profile($id, $data);
	if ($ok === false) {
		wp_send_json_error(['message' => __('No changes or update failed.', 'vpnserver')]);
	}
	$p = vpnpm_get_profile_by_id($id);

	wp_send_json_success([
		'id' => (int)$p->id,
		'remote_host' => $p->remote_host,
		'port' => (int)$p->port,
		'protocol' => $p->protocol,
		'cipher' => $p->cipher,
		'status' => $p->status,
		'ping' => isset($p->ping) ? (int)$p->ping : null,
		'notes' => $p->notes,
		'label' => $p->label ?? 'standard', // Add label field
		'type' => $p->type ?? 'standard',
		'location' => $p->location ?? '',
		'last_checked' => $p->last_checked,
	]);
}
endif;

// AJAX: Bulk upload profiles
if (!function_exists('vpnpm_ajax_bulk_upload_profiles')):
add_action('wp_ajax_vpnpm_bulk_upload_profiles', 'vpnpm_ajax_bulk_upload_profiles');
function vpnpm_ajax_bulk_upload_profiles() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
    }
    check_ajax_referer('vpnpm-upload');

    if (empty($_FILES['ovpn_files']['name'])) {
        wp_send_json_error(['message' => __('No files uploaded.', 'vpnserver')], 400);
    }

    $uploaded_files = $_FILES['ovpn_files'];
    $results = [];

    foreach ($uploaded_files['name'] as $index => $file_name) {
        $tmp_name = $uploaded_files['tmp_name'][$index];
        $error = $uploaded_files['error'][$index];

        if ($error !== UPLOAD_ERR_OK) {
            $results[] = [
                'file_name' => $file_name,
                'status' => 'error',
                'message' => __('File upload error.', 'vpnserver')
            ];
            continue;
        }

        $file_content = file_get_contents($tmp_name);
        if (!$file_content) {
            $results[] = [
                'file_name' => $file_name,
                'status' => 'error',
                'message' => __('Failed to read file content.', 'vpnserver')
            ];
            continue;
        }

        $parsed_data = vpnpm_parse_ovpn_file($file_content);
        if (!$parsed_data) {
            $results[] = [
                'file_name' => $file_name,
                'status' => 'error',
                'message' => __('Invalid .ovpn file format.', 'vpnserver')
            ];
            continue;
        }

        $inserted = vpnpm_insert_profile($parsed_data);
        if (!$inserted) {
            $results[] = [
                'file_name' => $file_name,
                'status' => 'error',
                'message' => __('Failed to save profile to database.', 'vpnserver')
            ];
            continue;
        }

        $results[] = [
            'file_name' => $file_name,
            'status' => 'success',
            'message' => __('Profile uploaded successfully.', 'vpnserver')
        ];
    }

    wp_send_json_success(['results' => $results]);
}
endif;

// AJAX: Get all server statuses
if (!function_exists('vpnpm_ajax_get_all_status')):
add_action('wp_ajax_vpnpm_get_all_status', 'vpnpm_ajax_get_all_status');
function vpnpm_ajax_get_all_status() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';
	$servers = $wpdb->get_results("SELECT id, status, ping, checkhost_ping_avg, last_checked, checkhost_last_checked, checkhost_last_error FROM {$table}");

	$data = array_map(function($server) {
        return [
            'id'           => (int) $server->id,
            'status'       => esc_html($server->status),
            'ping'         => $server->ping !== null ? (int) $server->ping : null,
			'ch_ping'      => isset($server->checkhost_ping_avg) && $server->checkhost_ping_avg !== null ? (int)$server->checkhost_ping_avg : null,
			'last_checked' => esc_html($server->last_checked),
			'ch_last_checked' => isset($server->checkhost_last_checked) ? esc_html($server->checkhost_last_checked) : null,
			'ch_error' => isset($server->checkhost_last_error) ? esc_html($server->checkhost_last_error) : null,
        ];
    }, $servers);

    wp_send_json_success(['servers' => $data]);
}
endif;

// Cron scheduling and ping processing is handled in vpnserver.php with settings-aware logic.

// AJAX: Get ping history for a server (last 6 days, 12h slots)
if (!function_exists('vpnpm_ajax_get_ping_history')):
add_action('wp_ajax_vpnpm_get_ping_history', 'vpnpm_ajax_get_ping_history');
function vpnpm_ajax_get_ping_history() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('Unauthorized', 'vpnserver')], 403);
	}
	check_ajax_referer('vpnpm-nonce');
	$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
	if (!$id) wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);
	if (!function_exists('vpnpm_get_ping_history')) {
		wp_send_json_error(['message' => __('History not available', 'vpnserver')]);
	}
	$rows = vpnpm_get_ping_history($id, 6);
	// Normalize payload grouped by source
	$bySource = [];
	foreach ($rows as $r) {
		$src = $r->source;
		if (!isset($bySource[$src])) $bySource[$src] = [];
		$bySource[$src][] = [
			'slot'    => $r->slot,
			'avg'     => is_null($r->avg_ping) ? null : (int)$r->avg_ping,
			'min'     => is_null($r->min_ping) ? null : (int)$r->min_ping,
			'max'     => is_null($r->max_ping) ? null : (int)$r->max_ping,
			'samples' => (int)$r->samples,
		];
	}
	wp_send_json_success(['history' => $bySource]);
}
endif;

// End of ajax-handlers