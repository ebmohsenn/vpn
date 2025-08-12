<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

class DB {
    public static function table_name() {
        global $wpdb; return $wpdb->prefix . 'vpn_profiles';
    }

    public static function activate() {
        self::migrate();
    }

    public static function deactivate() {
        // No-op; keep data on deactivate
    }

    public static function migrate() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            remote_host varchar(255) NOT NULL,
            port int(11) DEFAULT NULL,
            protocol varchar(20) DEFAULT NULL,
            cipher varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'unknown',
            ping int(11) DEFAULT NULL,
            type varchar(20) DEFAULT 'standard',
            label varchar(20) DEFAULT 'standard',
            location varchar(191) DEFAULT NULL,
            notes text NULL,
            last_checked datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY remote_host (remote_host),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql);
    }
}
