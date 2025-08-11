<?php
defined('ABSPATH') || exit;

// Settings and AJAX for official Check-Host nodes
add_action('admin_init', function() {
	register_setting('vpnsm_settings', 'vpnsm_checkhost_nodes', [
		'type' => 'array',
		'sanitize_callback' => function($input) {
			$nodes = function_exists('vpnsm_get_checkhost_nodes') ? vpnsm_get_checkhost_nodes() : [];
			if (!is_array($input)) $input = [];
			return array_values(array_intersect($input, array_keys($nodes)));
		},
		'default' => [],
	]);
});

add_action('wp_ajax_vpnsm_refresh_nodes', function() {
	check_ajax_referer('vpnsm_refresh_nodes');
	$nodes = function_exists('vpnsm_get_checkhost_nodes') ? vpnsm_get_checkhost_nodes(true) : [];
	$selected = get_option('vpnsm_checkhost_nodes', []);
	wp_send_json(['nodes' => $nodes, 'selected' => $selected]);
});

function vpnsm_get_checkhost_nodes($force_refresh = false) {
	$transient_key = 'vpnsm_checkhost_nodes';
	$cached = get_transient($transient_key);
	if ($cached && !$force_refresh) {
		return $cached;
	}
	$api_url = 'https://check-host.net/nodes/hosts';
	$response = wp_remote_get($api_url, ['timeout' => 12]);
	$nodes = [];
	if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
		$json = json_decode(wp_remote_retrieve_body($response), true);
		if (is_array($json)) {
			foreach ($json as $host => $info) {
				$country = is_array($info) && isset($info['country']) ? trim((string)$info['country']) : '';
				$city    = is_array($info) && isset($info['city']) ? trim((string)$info['city']) : '';
				if ($host && $country && $city) {
					$nodes[$host] = $country . ' - ' . $city;
				}
			}
		}
	}
	if (empty($nodes)) {
		$nodes = [
			'de-fra01.check-host.net' => 'Germany - Frankfurt',
			'us-nyc01.check-host.net' => 'USA - New York',
			'gb-lon01.check-host.net' => 'UK - London',
			'fr-par01.check-host.net' => 'France - Paris',
			'ru-mow01.check-host.net' => 'Russia - Moscow',
			'sg-sin01.check-host.net' => 'Singapore - Singapore',
			'jp-tyo01.check-host.net' => 'Japan - Tokyo',
		];
	}
	set_transient($transient_key, $nodes, 12 * HOUR_IN_SECONDS);
	return $nodes;
}

if (!function_exists('vpnpm_get_upload_dir')):
function vpnpm_get_upload_dir() {
	$upload = wp_upload_dir();
	$dir = trailingslashit($upload['basedir']) . 'vpn-profile-manager/';
	if (!file_exists($dir)) {
		wp_mkdir_p($dir);
	}
	return $dir;
}
endif;

if (!function_exists('vpnpm_get_upload_url')):
function vpnpm_get_upload_url() {
	$upload = wp_upload_dir();
	return trailingslashit($upload['baseurl']) . 'vpn-profile-manager/';
}
endif;

if (!function_exists('vpnpm_config_file_path')):
function vpnpm_config_file_path($id) {
	$dir = vpnpm_get_upload_dir();
	return $dir . $id . '.ovpn';
}
endif;

if (!function_exists('vpnpm_store_config_file')):
function vpnpm_store_config_file($id, $tmp_path) {
	$dest = vpnpm_config_file_path($id);
	return @move_uploaded_file($tmp_path, $dest);
}
endif;

if (!function_exists('vpnpm_delete_config_file')):
function vpnpm_delete_config_file($id) {
	$path = vpnpm_config_file_path($id);
	if (file_exists($path)) {
		@unlink($path);
	}
}
endif;

if (!function_exists('vpnpm_status_class')):
function vpnpm_status_class($status) {
	$status = strtolower((string)$status);
	if ($status === 'active' || $status === 'up') return 'badge badge-green';
	if ($status === 'down') return 'badge badge-red';
	if ($status === 'unknown') return 'badge badge-gray';
	return 'badge badge-blue';
}
endif;

if (!function_exists('vpnpm_sanitize_text')):
function vpnpm_sanitize_text($text) {
	return sanitize_textarea_field($text);
}
endif;

// Admin upload handler (Add Server)
add_action('admin_post_vpnpm_add_server', 'vpnpm_handle_upload');
function vpnpm_handle_upload() {
	if (!current_user_can('manage_options')) {
		wp_die(__('Unauthorized', 'vpnserver'));
	}
	check_admin_referer('vpnpm-upload');

	if (empty($_FILES['ovpn_file']) || $_FILES['ovpn_file']['error'] !== UPLOAD_ERR_OK) {
		wp_redirect(add_query_arg('vpnpm_msg', 'upload_error', admin_url('admin.php?page=vpn-manager')));
		exit;
	}

	$file = $_FILES['ovpn_file'];
	$name = sanitize_file_name($file['name']);
	if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'ovpn') {
		wp_redirect(add_query_arg('vpnpm_msg', 'invalid_type', admin_url('admin.php?page=vpn-manager')));
		exit;
	}

	$parse = vpnpm_parse_ovpn_file($file['tmp_name']);
	if (is_wp_error($parse)) {
		wp_redirect(add_query_arg('vpnpm_msg', 'parse_error', admin_url('admin.php?page=vpn-manager')));
		exit;
	}

	$notes_input = isset($_POST['notes']) ? vpnpm_sanitize_text($_POST['notes']) : '';
	$notes = trim($notes_input . "\n" . ($parse['notes'] ?? ''));
	$notes = $notes !== '' ? $notes : null;

	$data = [
		'file_name'   => $name,
		'remote_host' => $parse['remote_host'],
		'port'        => (int)$parse['port'],
		'protocol'    => $parse['protocol'],
		'cipher'      => $parse['cipher'],
		'status'      => 'unknown',
		'notes'       => $notes,
		'last_checked'=> null,
		'created_at'  => current_time('mysql'),
	];

	$id = vpnpm_insert_profile($data);
	if (!$id) {
		wp_redirect(add_query_arg('vpnpm_msg', 'db_error', admin_url('admin.php?page=vpn-manager')));
		exit;
	}

	if (!vpnpm_store_config_file($id, $file['tmp_name'])) {
		vpnpm_delete_profile($id);
		wp_redirect(add_query_arg('vpnpm_msg', 'store_error', admin_url('admin.php?page=vpn-manager')));
		exit;
	}

	wp_redirect(add_query_arg('vpnpm_msg', 'added', admin_url('admin.php?page=vpn-manager')));
	exit;
}

// Admin upload handler (Add Servers - Bulk Upload)
add_action('admin_post_vpnpm_add_servers', 'vpnpm_handle_bulk_upload');
function vpnpm_handle_bulk_upload() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'vpnserver'));
    }
    check_admin_referer('vpnpm-upload');

    if (empty($_FILES['ovpn_files']['name'])) {
        wp_redirect(add_query_arg('vpnpm_msg', 'upload_error', admin_url('admin.php?page=vpn-manager')));
        exit;
    }

    $uploaded_files = $_FILES['ovpn_files'];
    $errors = [];
    $success_count = 0;

    foreach ($uploaded_files['name'] as $index => $file_name) {
        $tmp_name = $uploaded_files['tmp_name'][$index];
        $error = $uploaded_files['error'][$index];

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = sprintf(__('File upload error: %s', 'vpnserver'), $file_name);
            continue;
        }

        $parse = vpnpm_parse_ovpn_file($tmp_name);
        if (is_wp_error($parse)) {
            $errors[] = sprintf(__('Failed to parse file: %s', 'vpnserver'), $file_name);
            continue;
        }

        $data = [
            'file_name'   => sanitize_file_name($file_name),
            'remote_host' => $parse['remote_host'],
            'port'        => (int)$parse['port'],
            'protocol'    => $parse['protocol'],
            'cipher'      => $parse['cipher'],
            'status'      => 'unknown',
            'notes'       => $parse['notes'],
            'last_checked'=> null,
            'created_at'  => current_time('mysql'),
        ];

        $id = vpnpm_insert_profile($data);
        if (!$id) {
            $errors[] = sprintf(__('Database error for file: %s', 'vpnserver'), $file_name);
            continue;
        }

        if (!vpnpm_store_config_file($id, $tmp_name)) {
            vpnpm_delete_profile($id);
            $errors[] = sprintf(__('Failed to store file: %s', 'vpnserver'), $file_name);
            continue;
        }

        $success_count++;
    }

    if ($success_count > 0) {
        wp_redirect(add_query_arg('vpnpm_msg', 'added', admin_url('admin.php?page=vpn-manager')));
    } else {
        wp_redirect(add_query_arg('vpnpm_msg', 'upload_error', admin_url('admin.php?page=vpn-manager')));
    }
    exit;
}
// End of helpers
