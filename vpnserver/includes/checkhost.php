<?php
defined('ABSPATH') || exit;

// Simple Check-Host API client and processing utilities

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
        'checkhost_ping_avg' => $avg_ms !== null ? (int)$avg_ms : null,
        'checkhost_ping_json' => wp_json_encode($raw_result),
        'checkhost_last_checked' => current_time('mysql'),
    ], ['id' => (int)$profile_id], ['%d','%s','%s'], ['%d']);
}
endif;

if (!function_exists('vpnpm_checkhost_rate_limit_sleep')):
function vpnpm_checkhost_rate_limit_sleep() {
    // Basic backoff to be polite
    usleep(250000); // 250ms between calls
}
endif;
