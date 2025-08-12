<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

function option($key, $default = null) {
    $all = get_option('hovpnm_options', []);
    return isset($all[$key]) ? $all[$key] : $default;
}

function update_option_value($key, $value) {
    $all = get_option('hovpnm_options', []);
    if (!is_array($all)) $all = [];
    $all[$key] = $value;
    update_option('hovpnm_options', $all);
}

function is_extension_active($slug) {
    $active = get_option('vpnpm_active_extensions', []);
    return is_array($active) && in_array($slug, $active, true);
}

function asset_url($rel) {
    return trailingslashit(\plugin_dir_url(dirname(__DIR__))) . 'vpn-manager/core/assets/' . ltrim($rel, '/');
}

/**
 * Resolve a host to location string using ip-api.com with transient caching.
 * @param string $host
 * @return string Location in the format "Country, City" or empty string on failure
 */
function detect_location_for_host($host) {
    $host = trim((string)$host);
    if ($host === '') return '';
    $cache_key = 'hovpnm_geo_' . md5($host);
    $cached = get_transient($cache_key);
    if ($cached !== false) return (string)$cached;

    // Resolve hostname to IP if needed
    $ip = $host;
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $resolved = gethostbyname($host);
        if ($resolved && $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ip = $resolved;
        }
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        set_transient($cache_key, '', HOUR_IN_SECONDS);
        return '';
    }

    $url = 'http://ip-api.com/json/' . rawurlencode($ip);
    $resp = wp_remote_get($url, [
        'timeout' => 5,
        'headers' => [ 'Accept' => 'application/json' ],
    ]);
    if (is_wp_error($resp)) { set_transient($cache_key, '', 30 * MINUTE_IN_SECONDS); return ''; }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) { set_transient($cache_key, '', 30 * MINUTE_IN_SECONDS); return ''; }
    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') { set_transient($cache_key, '', 30 * MINUTE_IN_SECONDS); return ''; }
    $country = trim((string)($data['country'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $loc = $country !== '' ? ($city !== '' ? ($country . ', ' . $city) : $country) : '';
    set_transient($cache_key, $loc, DAY_IN_SECONDS);
    return $loc;
}
