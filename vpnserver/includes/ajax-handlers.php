<?php
defined('ABSPATH') || exit;

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
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if (!$id) wp_send_json_error(['message' => __('Invalid ID', 'vpnserver')], 400);

	$data = [
		'remote_host' => isset($_POST['remote_host']) ? sanitize_text_field(wp_unslash($_POST['remote_host'])) : null,
		'port'        => isset($_POST['port']) ? (int)$_POST['port'] : null,
		'protocol'    => isset($_POST['protocol']) ? sanitize_text_field(wp_unslash($_POST['protocol'])) : null,
		'cipher'      => isset($_POST['cipher']) ? sanitize_text_field(wp_unslash($_POST['cipher'])) : null,
		'status'      => isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : null,
		'notes'       => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : null,
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
		'notes' => $p->notes,
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
    $servers = $wpdb->get_results("SELECT id, status, ping, last_checked FROM {$table}");

    $data = array_map(function($server) {
        return [
            'id'           => (int) $server->id,
            'status'       => esc_html($server->status),
            'ping'         => $server->ping !== null ? (int) $server->ping : null,
            'last_checked' => esc_html($server->last_checked),
        ];
    }, $servers);

    wp_send_json_success(['servers' => $data]);
}
endif;

// Schedule ping test every 10 minutes
if ( ! wp_next_scheduled( 'vpnpm_cron_ping_servers' ) ) {
    wp_schedule_event( time(), 'ten_minutes', 'vpnpm_cron_ping_servers' );
}
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['ten_minutes'] = [
        'interval' => 600,
        'display'  => __( 'Every 10 Minutes' ),
    ];
    return $schedules;
});

add_action( 'vpnpm_cron_ping_servers', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'vpn_profiles';
    $servers = $wpdb->get_results( "SELECT id FROM {$table}" );
    if ( $servers ) {
        foreach ( $servers as $srv ) {
            // Simulate AJAX ping update
            $profile = vpnpm_get_profile_by_id( $srv->id );
            if ( ! $profile ) continue;

            $host = $profile->remote_host;
            $port = (int)$profile->port ?: 1194;
            $timeout = 3;

            $status = 'down';
            $start = microtime(true);
            $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($fp) {
                $status = 'active';
                fclose($fp);
            }
            $ping_ms = (int)round((microtime(true) - $start) * 1000);

            $wpdb->update(
                $table,
                [
                    'status'       => $status,
                    'ping'         => $ping_ms,
                    'last_checked' => current_time('mysql')
                ],
                ['id' => $srv->id],
                ['%s', '%d', '%s'],
                ['%d']
            );
        }
    }
});

// End of ajax-handlers