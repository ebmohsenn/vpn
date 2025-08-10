<?php
// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Safety: only run in admin and if capability present
if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
    return;
}

global $wpdb;

// Drop custom table
$table = $wpdb->prefix . 'vpn_profiles';
$wpdb->query("DROP TABLE IF EXISTS `{$table}`");

// Delete uploaded .ovpn files and the directory
if (!function_exists('wp_upload_dir')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
$upload = wp_upload_dir();
$dir = trailingslashit($upload['basedir']) . 'vpn-profile-manager/';
if (is_dir($dir)) {
    foreach (glob($dir . '*.ovpn') as $file) {
        @unlink($file);
    }
    // attempt to remove the folder if empty
    @rmdir($dir);
}

// Optionally, clean any options/transients if they were used in future
// delete_option('vpnserver_some_option');
...existing code from uninstall.php...
