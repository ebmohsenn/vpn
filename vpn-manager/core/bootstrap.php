<?php
namespace HOVPNM\Core;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/server-functions.php';
require_once __DIR__ . '/admin-menu.php';
require_once __DIR__ . '/columns.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/uploads.php';

class Bootstrap {
    public static function load_extensions() {
        $base = dirname(__DIR__);
        $ext_dir = trailingslashit($base) . 'extensions';
        if (!is_dir($ext_dir)) return;
        $active = get_option('vpnpm_active_extensions', []);
        if (!is_array($active)) $active = [];
        foreach (scandir($ext_dir) as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $path = $ext_dir . '/' . $folder;
            if (!is_dir($path)) continue;
            $main = $path . '/' . $folder . '.php';
            if (!file_exists($main)) continue;
            // Only load if active
            if (in_array($folder, $active, true)) {
                include_once $main;
            }
        }
        // Auto-load ping-merge when any ping extension is active
        if (in_array('server-ping', $active, true) || in_array('checkhost-ping', $active, true)) {
            $merge = $ext_dir . '/ping-merge/ping-merge.php';
            if (file_exists($merge)) {
                include_once $merge;
            }
        }
    }
}
