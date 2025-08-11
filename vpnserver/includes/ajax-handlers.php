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
	if (!function_exists('vpnpm_checkhost_curated_nodes')) {
		wp_send_json_success(['nodes' => []]);
	}
	$nodes = vpnpm_checkhost_curated_nodes(); // [{host,label}]
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
	$row = $wpdb->get_row($wpdb->prepare("SELECT file_name, checkhost_ping_json, checkhost_last_checked FROM {$table} WHERE id = %d", $id));
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
	$nodes = [];
	foreach ($raw as $nodeName => $series) {
		$samples = 0; $succ = 0; $latencies = [];
		if (is_array($series)) {
			foreach ($series as $rowItem) {
				$samples++;
				if (is_array($rowItem) && isset($rowItem[1]) && is_array($rowItem[1]) && isset($rowItem[1][0])) {
					$lat = floatval($rowItem[1][0]) * 1000; // to ms
					if ($lat > 0) { $latencies[] = $lat; $succ++; }
				}
			}
		}
		$avg = !empty($latencies) ? round(array_sum($latencies) / count($latencies)) : null;
		$min = !empty($latencies) ? (int) round(min($latencies)) : null;
		$max = !empty($latencies) ? (int) round(max($latencies)) : null;
		$loss = $samples > 0 ? round((($samples - $succ) / $samples) * 100) : null;
		$label = function_exists('vpnpm_checkhost_label_for_host') ? vpnpm_checkhost_label_for_host($nodeName) : $nodeName;
		$nodes[] = [
			'node' => (string)$nodeName,
			'label'=> (string)$label,
			'avg'  => $avg,
			'min'  => $min,
			'max'  => $max,
			'loss' => $loss,
			'samples' => $samples,
		];
	}
	usort($nodes, function($a,$b){
		$av = $a['avg'] ?? PHP_INT_MAX; $bv = $b['avg'] ?? PHP_INT_MAX;
		return $av <=> $bv;
	});
	wp_send_json_success([
		'server' => pathinfo((string)$row->file_name, PATHINFO_FILENAME),
		'updated' => $row->checkhost_last_checked ? (string)$row->checkhost_last_checked : null,
		'nodes' => $nodes,
	]);
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
	$servers = $wpdb->get_results("SELECT id, status, ping, checkhost_ping_avg, last_checked, checkhost_last_checked FROM {$table}");

	$data = array_map(function($server) {
        return [
            'id'           => (int) $server->id,
            'status'       => esc_html($server->status),
            'ping'         => $server->ping !== null ? (int) $server->ping : null,
			'ch_ping'      => isset($server->checkhost_ping_avg) && $server->checkhost_ping_avg !== null ? (int)$server->checkhost_ping_avg : null,
            'last_checked' => esc_html($server->last_checked),
			'ch_last_checked' => isset($server->checkhost_last_checked) ? esc_html($server->checkhost_last_checked) : null,
        ];
    }, $servers);

    wp_send_json_success(['servers' => $data]);
}
endif;

// Cron scheduling and ping processing is handled in vpnserver.php with settings-aware logic.

// End of ajax-handlers