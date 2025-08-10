<?php
/**
 * Uninstall cleanup for vpn-server-manager
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop table
$table = $wpdb->prefix . 'vpn_profiles';
$wpdb->query("DROP TABLE IF EXISTS `$table`");

// Remove uploaded configs directory
$upload = wp_upload_dir();
$dir = trailingslashit($upload['basedir']) . 'vpn-profile-manager/';

if (is_dir($dir)) {
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            @unlink($file->getRealPath());
        }
    }
    @rmdir($dir);
}
