<?php
defined('ABSPATH') || exit;

function vpnpm_parse_ovpn_content($content) {
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $data = [];
    $current_tag = null;
    $tag_content = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
            continue;
        }

        if (preg_match('/^<(\w+)>$/', $line, $matches)) {
            $current_tag = $matches[1];
            $tag_content = [];
            continue;
        }

        if ($current_tag && preg_match('/^<\/' . preg_quote($current_tag, '/') . '>$/', $line)) {
            $data[$current_tag] = implode("\n", $tag_content);
            $current_tag = null;
            $tag_content = [];
            continue;
        }

        if ($current_tag) {
            $tag_content[] = $line;
            continue;
        }

        if (preg_match('/^(\S+)(?:\s+(.*))?$/', $line, $matches)) {
            $key = $matches[1];
            $value = isset($matches[2]) ? $matches[2] : true;

            if ($key === 'dhcp-option' && $value !== true) {
                $parts = explode(' ', $value, 2);
                if (count($parts) === 2) {
                    $key .= ' ' . $parts[0];
                    $value = $parts[1];
                }
            }

            $data[$key] = $value;
        }
    }

    if (!isset($data['remote'])) {
        return false;
    }
    if (!isset($data['port'])) {
        $data['port'] = 1194;
    }
    if (!isset($data['proto'])) {
        $data['proto'] = 'udp';
    }

    return [
        'remote_host' => $data['remote'],
        'port' => $data['port'],
        'protocol' => $data['proto'] . '-client',
        'raw' => $data
    ];
}