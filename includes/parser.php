<?php
defined('ABSPATH') || exit;

/**
 * Parse .ovpn file content.
 * Returns: ['remote_host','port','protocol','cipher','notes']
 */
function vpnpm_parse_ovpn($content) {
    $remote_host = null;
    $port = null;
    $protocol = null;
    $cipher = null;
    $notes = [];

    $lines = preg_split('/\R/', $content);
    foreach ($lines as $line) {
        $trim = trim($line);

        // Collect top comments as notes
        if ($trim === '') {
            continue;
        }
        if ($trim[0] === '#' || $trim[0] === ';') {
            if (count($notes) < 6) {
                $notes[] = ltrim($trim, "#; \t");
            }
            // continue parsing other lines
        }

        // remote <host> [port] [proto]
        if (preg_match('/^remote\s+([^\s]+)(?:\s+(\d+))?(?:\s+(tcp|tcp-client|udp|udp6|tcp6))?/i', $trim, $m)) {
            if ($remote_host === null) {
                $remote_host = $m[1];
                if (!empty($m[2])) {
                    $port = (int)$m[2];
                }
                if (!empty($m[3])) {
                    $protocol = stripos($m[3], 'tcp') !== false ? 'tcp' : 'udp';
                }
            }
            continue;
        }

        // proto <tcp|udp|tcp-client|udp6|tcp6>
        if (preg_match('/^proto\s+([^\s]+)/i', $trim, $m)) {
            $p = strtolower($m[1]);
            $protocol = (strpos($p, 'tcp') !== false) ? 'tcp' : 'udp';
            continue;
        }

        // port <num>
        if (preg_match('/^port\s+(\d+)/i', $trim, $m)) {
            $port = (int)$m[1];
            continue;
        }

        // cipher <name>
        if (preg_match('/^cipher\s+([^\s]+)/i', $trim, $m)) {
            $cipher = $m[1];
            continue;
        }
    }

    if (!$port) {
        $port = 1194; // OpenVPN default if not specified
    }

    return [
        'remote_host' => $remote_host ?: '',
        'port'        => (int)$port,
        'protocol'    => $protocol ?: null,
        'cipher'      => $cipher ?: null,
        'notes'       => !empty($notes) ? implode("\n", $notes) : null,
    ];
}

/**
 * Convenience: parse a file path.
 */
function vpnpm_parse_ovpn_file($file_path) {
    $content = @file_get_contents($file_path);
    if ($content === false) {
        return new WP_Error('vpnpm_read_error', __('Could not read uploaded file.', 'vpnpm'));
    }
    return vpnpm_parse_ovpn($content);
}
