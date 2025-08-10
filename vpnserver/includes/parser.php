<?php
defined('ABSPATH') || exit;

function vpnpm_parse_ovpn($contents) {
	$remote_host = '';
	$port = 0;
	$protocol = '';
	$cipher = '';
	$notes = [];

	$lines = preg_split('/\r?\n/', (string)$contents);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
			continue;
		}
		if (stripos($line, 'remote ') === 0) {
			// remote host [port] [proto]
			$parts = preg_split('/\s+/', $line);
			// parts[0] is 'remote'
			$remote_host = isset($parts[1]) ? $parts[1] : $remote_host;
			if (isset($parts[2]) && is_numeric($parts[2])) {
				$port = (int)$parts[2];
			}
			if (isset($parts[3])) {
				$protocol = strtolower($parts[3]);
			}
			continue;
		}
		if (stripos($line, 'proto ') === 0) {
			$parts = preg_split('/\s+/', $line);
			$protocol = isset($parts[1]) ? strtolower($parts[1]) : $protocol;
			continue;
		}
		if (stripos($line, 'port ') === 0) {
			$parts = preg_split('/\s+/', $line);
			$port = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : $port;
			continue;
		}
		if (stripos($line, 'cipher ') === 0) {
			$parts = preg_split('/\s+/', $line, 2);
			$cipher = isset($parts[1]) ? $parts[1] : $cipher;
			continue;
		}
		// collect non-empty, non-comment lines as possible notes context
		$notes[] = $line;
	}

	if (empty($remote_host)) {
		return new WP_Error('parse_error', __('No remote host found in .ovpn file', 'vpnserver'));
	}

	return [
		'remote_host' => $remote_host,
		'port'        => $port ?: 1194,
		'protocol'    => in_array($protocol, ['tcp','udp'], true) ? $protocol : '',
		'cipher'      => $cipher,
		'notes'       => implode("\n", array_slice($notes, 0, 5)),
	];
}

function vpnpm_parse_ovpn_file($file_path) {
	$contents = @file_get_contents($file_path);
	if ($contents === false) {
		return new WP_Error('read_error', __('Could not read uploaded file', 'vpnserver'));
	}
	return vpnpm_parse_ovpn($contents);
}

// End of parser
