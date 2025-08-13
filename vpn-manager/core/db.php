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
            type varchar(20) DEFAULT 'standard',
            location varchar(191) DEFAULT NULL,
            notes text NULL,
            ping_server_avg int(11) DEFAULT NULL,
            ping_server_last_checked datetime DEFAULT NULL,
            checkhost_ping_avg int(11) DEFAULT NULL,
            checkhost_ping_json longtext NULL,
            checkhost_last_checked datetime DEFAULT NULL,
            checkhost_last_error text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY remote_host (remote_host),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql);

        // Ping history table
        $hist_table = $wpdb->prefix . 'vpn_ping_history';
        $sql_hist = "CREATE TABLE {$hist_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            server_id bigint(20) unsigned NOT NULL,
            source varchar(32) NOT NULL,
            ping_value int(11) DEFAULT NULL,
            location varchar(191) DEFAULT NULL,
            status varchar(20) DEFAULT 'unknown',
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY server_id (server_id),
            KEY source (source)
        ) {$charset_collate};";
        dbDelta($sql_hist);

        // Server tags table
        $tags_table = $wpdb->prefix . 'vpn_server_tags';
        $sql_tags = "CREATE TABLE {$tags_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            server_id bigint(20) unsigned NOT NULL,
            tag varchar(100) NOT NULL,
            PRIMARY KEY (id),
            KEY server_id (server_id),
            KEY tag (tag)
        ) {$charset_collate};";
        dbDelta($sql_tags);

        // Deleted profiles archive table
        $del_table = $wpdb->prefix . 'vpn_profiles_deleted';
        $sql_del = "CREATE TABLE {$del_table} (
            id bigint(20) unsigned NOT NULL,
            file_name varchar(255) NOT NULL,
            remote_host varchar(255) NOT NULL,
            port int(11) DEFAULT NULL,
            protocol varchar(20) DEFAULT NULL,
            cipher varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'unknown',
            type varchar(20) DEFAULT 'standard',
            location varchar(191) DEFAULT NULL,
            notes text NULL,
            ping_server_avg int(11) DEFAULT NULL,
            ping_server_last_checked datetime DEFAULT NULL,
            checkhost_ping_avg int(11) DEFAULT NULL,
            checkhost_ping_json longtext NULL,
            checkhost_last_checked datetime DEFAULT NULL,
            checkhost_last_error text NULL,
            created_at datetime DEFAULT NULL,
            deleted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY remote_host (remote_host),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql_del);
    }
}
