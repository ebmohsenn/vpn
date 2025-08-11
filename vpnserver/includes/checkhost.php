<?php
defined('ABSPATH') || exit;

// Check-Host API client and processing utilities (official endpoints)

// Cooldown helpers to avoid hammering when blocked (403)
if (!function_exists('vpnpm_checkhost_is_blocked')):
function vpnpm_checkhost_is_blocked() {
    return (bool) get_transient('vpnpm_checkhost_cooldown');
}
endif;
if (!function_exists('vpnpm_checkhost_mark_blocked')):
function vpnpm_checkhost_mark_blocked($minutes = 60) {
    set_transient('vpnpm_checkhost_cooldown', 1, max(60, $minutes * MINUTE_IN_SECONDS));
}
endif;

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

if (!function_exists('vpnpm_checkhost_load_nodes')):
/**
 * Load node list from Check-Host API, cache it in a transient, and fallback to curated.
 * Returns array of [host,label].
 */
function vpnpm_checkhost_load_nodes() {
    $cached = get_transient('vpnpm_checkhost_nodes');
    if (is_array($cached) && !empty($cached)) return $cached;
    $out = [];
    // Official list of nodes with labels is not formally documented; attempt a best-effort fetch
    $resp = wp_remote_get('https://check-host.net/nodes/hosts', ['timeout' => 10]);
    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if (is_array($json)) {
            foreach ($json as $row) {
                // Expecting entries like {host: ..., location: ...} or similar shape
                if (is_array($row)) {
                    $host = isset($row['host']) ? (string)$row['host'] : '';
                    $label = isset($row['location']) ? (string)$row['location'] : (isset($row['label']) ? (string)$row['label'] : $host);
                    if ($host !== '') {
                        $out[] = ['host' => $host, 'label' => $label];
                    }
                } elseif (is_string($row)) {
                    $out[] = ['host' => $row, 'label' => $row];
                }
            }
        }
    }
    if (empty($out)) {
        $out = vpnpm_checkhost_curated_nodes();
    }
    set_transient('vpnpm_checkhost_nodes', $out, 6 * HOUR_IN_SECONDS);
    return $out;
}
endif;

if (!function_exists('vpnpm_checkhost_initiate_ping')):
/**
 * Start a Check-Host ping test using the official API.
 * - POST https://check-host.net/check-ping
 * - Required: host
 * - Optional: node=node-hostname repeated per selection, max_nodes=int
 * Returns: [array $init|false, string|null $error]
 */
function vpnpm_checkhost_initiate_ping($target, array $nodes = [], $max_nodes = null) {
    if (vpnpm_checkhost_is_blocked()) {
        return [false, 'Check-Host temporarily disabled due to previous 403 (cooldown active).'];
    }
    $endpoint = 'https://check-host.net/check-ping';
    // Build body with repeated node= parameters (official style)
    $pairs = [ 'host=' . rawurlencode($target) ];
    // If too many nodes are selected, let Check-Host choose a subset to avoid very long requests
    $nodes = array_values(array_filter(array_map('trim', $nodes)));
    $include_nodes = count($nodes) > 0 && count($nodes) <= 5;
    if ($max_nodes === null && !$include_nodes) {
        $max_nodes = 3; // reasonable default subset
    }
    if ($max_nodes !== null) {
        $pairs[] = 'max_nodes=' . rawurlencode((string)(int)$max_nodes);
    }
    if ($include_nodes) {
        foreach ($nodes as $n) {
            if ($n !== '') {
                $pairs[] = 'node=' . rawurlencode($n);
            }
        }
    }
    $body = implode('&', $pairs);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[vpnserver] Check-Host API request: ' . $endpoint . ' BODY: ' . $body);
    }
    $headers = [
        'Content-Type'      => 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept'            => 'application/json, */*;q=0.8',
        'User-Agent'        => 'VPNServerManager/1.0 (+https://wordpress.org; WP ' . get_bloginfo('version') . ')',
        'Referer'           => 'https://check-host.net/',
        'Origin'            => 'https://check-host.net',
        'X-Requested-With'  => 'XMLHttpRequest',
        'Accept-Language'   => get_locale() ? str_replace('_', '-', get_locale()) . ',en;q=0.8' : 'en-US,en;q=0.8',
    ];
    $response = wp_remote_post($endpoint, [
        'timeout' => 15,
        'headers' => $headers,
        'body'    => $body,
    ]);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[vpnserver] Check-Host API response: ' . print_r($response, true));
    }
    if (is_wp_error($response)) return [false, $response->get_error_message()];
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        // Fallback to GET if POST blocked (e.g., 403/405)
        $url = $endpoint . '?' . $body;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[vpnserver] POST returned HTTP ' . $code . ', trying GET: ' . $url);
        }
        $response = wp_remote_get($url, [ 'timeout' => 15, 'headers' => $headers ]);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[vpnserver] Check-Host GET response: ' . print_r($response, true));
        }
        if (is_wp_error($response)) return [false, $response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $snippet = substr((string) wp_remote_retrieve_body($response), 0, 200);
            if ($code === 403) {
                vpnpm_checkhost_mark_blocked(60);
            }
            return [false, 'HTTP ' . $code . ($snippet ? ' Body: ' . $snippet : '')];
        }
    }
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
/**
 * Poll Check-Host for results by request_id until available.
 * Returns: [array $json|false, string|null $error]
 */
function vpnpm_checkhost_poll_result($request_id) {
    $url = sprintf('https://check-host.net/check-result/%s', rawurlencode($request_id));
    $headers = [
        'Accept'            => 'application/json, */*;q=0.8',
        'User-Agent'        => 'VPNServerManager/1.0 (+https://wordpress.org; WP ' . get_bloginfo('version') . ')',
        'Referer'           => 'https://check-host.net/',
        'Origin'            => 'https://check-host.net',
        'X-Requested-With'  => 'XMLHttpRequest',
        'Accept-Language'   => get_locale() ? str_replace('_', '-', get_locale()) . ',en;q=0.8' : 'en-US,en;q=0.8',
    ];
    $resp = wp_remote_get($url, ['timeout' => 15, 'headers' => $headers]);
    if (is_wp_error($resp)) return [false, $resp->get_error_message()];
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        $snippet = substr((string) wp_remote_retrieve_body($resp), 0, 200);
        if ($code === 403) {
            vpnpm_checkhost_mark_blocked(60);
        }
        return [false, 'HTTP ' . $code . ($snippet ? ' Body: ' . $snippet : '')];
    }
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

if (!function_exists('vpnpm_checkhost_parse_nodes')):
/**
 * Parse per-node stats (avg/min/max/loss/samples) from Check-Host result JSON.
 * Returns an array of [node, label, avg, min, max, loss, samples].
 */
function vpnpm_checkhost_parse_nodes($result_json) {
    if (!is_array($result_json)) return [];
    $out = [];
    foreach ($result_json as $nodeName => $series) {
        $samples = 0; $succ = 0; $latencies = [];
        if (is_array($series)) {
            foreach ($series as $rowItem) {
                $samples++;
                if (is_array($rowItem) && isset($rowItem[1]) && is_array($rowItem[1]) && isset($rowItem[1][0])) {
                    $lat = floatval($rowItem[1][0]) * 1000; // seconds -> ms
                    if ($lat > 0) { $latencies[] = $lat; $succ++; }
                }
            }
        }
        $avg = !empty($latencies) ? (int) round(array_sum($latencies) / count($latencies)) : null;
        $min = !empty($latencies) ? (int) round(min($latencies)) : null;
        $max = !empty($latencies) ? (int) round(max($latencies)) : null;
        $loss = $samples > 0 ? (int) round((($samples - $succ) / $samples) * 100) : null;
        $label = function_exists('vpnpm_checkhost_label_for_host') ? vpnpm_checkhost_label_for_host($nodeName) : $nodeName;
        $out[] = [
            'node' => (string)$nodeName,
            'label'=> (string)$label,
            'avg'  => $avg,
            'min'  => $min,
            'max'  => $max,
            'loss' => $loss,
            'samples' => $samples,
        ];
    }
    usort($out, function($a,$b){ $av = $a['avg'] ?? PHP_INT_MAX; $bv = $b['avg'] ?? PHP_INT_MAX; return $av <=> $bv; });
    return $out;
}
endif;

if (!function_exists('vpnpm_store_checkhost_result')):
function vpnpm_store_checkhost_result($profile_id, $avg_ms, $raw_result) {
    global $wpdb; $table = vpnpm_table_name();
    $last_checked = current_time('mysql');
    $wpdb->update($table, [
        'checkhost_ping_avg'     => $avg_ms !== null ? (int)$avg_ms : null,
        'checkhost_ping_json'    => wp_json_encode($raw_result),
        'checkhost_last_checked' => $last_checked,
        'checkhost_last_error'   => null,
    ], ['id' => (int)$profile_id], ['%d','%s','%s','%s'], ['%d']);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[vpnserver] Saving ping avg: ' . $avg_ms . ', last checked: ' . $last_checked);
        error_log('[vpnserver] DB update result: ' . print_r($wpdb->last_error, true));
    }
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
    error_log('[vpnserver] DB update result: ' . print_r($wpdb->last_error, true));
    }
}
endif;

if (!function_exists('vpnpm_checkhost_rate_limit_sleep')):
function vpnpm_checkhost_rate_limit_sleep() {
    // Basic backoff to be polite
    usleep(250000); // 250ms between calls
}
endif;
