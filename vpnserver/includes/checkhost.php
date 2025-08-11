<?php
defined('ABSPATH') || exit;

// Simple Check-Host API client and processing utilities

if (!function_exists('vpnpm_checkhost_curated_nodes')):
function vpnpm_checkhost_curated_nodes() {
    // Curated defaults: host => label
    // Admin can still paste any hosts; these are just convenient presets
    $nodes = [
        'ir1.node.check-host.net'        => 'Tehran, Iran',
        'ir2.node.check-host.net'        => 'Mashhad, Iran',
        'ir-tehran.node.check-host.net'  => 'Tehran, Iran',
        'ir-mashhad.node.check-host.net' => 'Mashhad, Iran',
        'ae-dubai.node.check-host.net'   => 'Dubai, UAE',
        'tr-istanbul.node.check-host.net'=> 'Istanbul, Turkey',
        'ru-moscow.node.check-host.net'  => 'Moscow, Russia',
        'de-berlin.node.check-host.net'  => 'Berlin, Germany',
        'nl-amsterdam.node.check-host.net'=> 'Amsterdam, Netherlands',
        'fr-paris.node.check-host.net'   => 'Paris, France',
    ];
    // Return as an array of arrays {host,label}
    $out = [];
    foreach ($nodes as $host => $label) {
        $out[] = ['host' => $host, 'label' => $label];
    }
    return $out;
}
endif;

if (!function_exists('vpnpm_checkhost_label_for_host')):
function vpnpm_checkhost_label_for_host($host) {
    $host = (string) $host;
    $curated = vpnpm_checkhost_curated_nodes();
    foreach ($curated as $row) {
        if (isset($row['host']) && strcasecmp($row['host'], $host) === 0) {
            return (string) $row['label'];
        }
    }
    // Fallback: derive a label from host
    if (strpos($host, 'ir') === 0) return 'Iran node';
    if (strpos($host, 'ae-') === 0) return 'UAE node';
    if (strpos($host, 'tr-') === 0) return 'Turkey node';
    return $host;
}
endif;

if (!function_exists('vpnpm_checkhost_initiate_ping')):
function vpnpm_checkhost_initiate_ping($target, array $nodes = []) {
    $endpoint = 'https://check-host.net/check-ping';
    $body = ['host' => $target];
    if (!empty($nodes)) {
        $body['node'] = $nodes; // multiple nodes allowed
    }
    $response = wp_remote_post($endpoint, [
        'timeout' => 10,
        'body'    => $body,
    ]);
    if (is_wp_error($response)) return [false, $response->get_error_message()];
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) return [false, 'HTTP ' . $code];
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['request_id'])) return [false, 'Invalid response'];
    $rid = $data['request_id'];
    $nodes_map = isset($data['nodes']) && is_array($data['nodes']) ? $data['nodes'] : [];
    return [
        [
            'request_id' => $rid,
            'nodes_map'  => $nodes_map,
        ],
        null
    ];
}
endif;

if (!function_exists('vpnpm_checkhost_poll_result')):
function vpnpm_checkhost_poll_result($request_id) {
    $url = sprintf('https://check-host.net/check-result/%s', rawurlencode($request_id));
    $resp = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($resp)) return [false, $resp->get_error_message()];
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) return [false, 'HTTP ' . $code];
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data)) return [false, 'Invalid response'];
    return [$data, null];
}
endif;

if (!function_exists('vpnpm_checkhost_aggregate_ping_ms')):
function vpnpm_checkhost_aggregate_ping_ms($result_json) {
    // $result_json is decoded array from /check-result
    // Each key is a node id, value is [ [time, ping, status], ... ]
    if (!is_array($result_json)) return null;
    $pings = [];
    foreach ($result_json as $node => $series) {
        if (!is_array($series)) continue;
        foreach ($series as $row) {
            // row can be like [timestamp, [latency, ...], status]
            if (is_array($row) && isset($row[1]) && is_array($row[1]) && isset($row[1][0])) {
                $lat = floatval($row[1][0]) * 1000; // seconds to ms if needed
                if ($lat > 0) $pings[] = $lat;
            }
        }
    }
    if (empty($pings)) return null;
    return (int) round(array_sum($pings) / count($pings));
}
endif;

if (!function_exists('vpnpm_store_checkhost_result')):
function vpnpm_store_checkhost_result($profile_id, $avg_ms, $raw_result) {
    global $wpdb; $table = vpnpm_table_name();
    $wpdb->update($table, [
        'checkhost_ping_avg'     => $avg_ms !== null ? (int)$avg_ms : null,
        'checkhost_ping_json'    => wp_json_encode($raw_result),
        'checkhost_last_checked' => current_time('mysql'),
        'checkhost_last_error'   => null,
    ], ['id' => (int)$profile_id], ['%d','%s','%s','%s'], ['%d']);
}
endif;

if (!function_exists('vpnpm_store_checkhost_error')):
function vpnpm_store_checkhost_error($profile_id, $error_message, $raw_result = null) {
    global $wpdb; $table = vpnpm_table_name();
    $safe = wp_strip_all_tags((string)$error_message);
    $data = [
        'checkhost_last_error'   => $safe,
        'checkhost_last_checked' => current_time('mysql'),
    ];
    if (!is_null($raw_result)) {
        $data['checkhost_ping_json'] = wp_json_encode($raw_result);
    }
    $wpdb->update($table, $data, ['id' => (int)$profile_id]);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[vpnserver] Check-Host error for profile ' . (int)$profile_id . ': ' . $safe);
    }
}
endif;

if (!function_exists('vpnpm_checkhost_rate_limit_sleep')):
function vpnpm_checkhost_rate_limit_sleep() {
    // Basic backoff to be polite
    usleep(250000); // 250ms between calls
}
endif;
