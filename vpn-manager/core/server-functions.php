<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

class Servers {
    public static function all() {
        global $wpdb; $t = DB::table_name();
        return $wpdb->get_results("SELECT * FROM {$t} ORDER BY id ASC");
    }
    public static function get($id) {
        global $wpdb; $t = DB::table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", (int)$id));
    }
    public static function update($id, $data) {
        global $wpdb; $t = DB::table_name();
        return $wpdb->update($t, $data, ['id' => (int)$id]);
    }
    public static function insert($data) {
        global $wpdb; $t = DB::table_name();
        $wpdb->insert($t, $data);
        return (int)$wpdb->insert_id;
    }
    public static function delete($id) {
        global $wpdb; $t = DB::table_name();
        return $wpdb->delete($t, ['id' => (int)$id]);
    }
}
