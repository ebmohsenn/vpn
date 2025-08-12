<?php
namespace HOVPNM\Ext\PublicStatus;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/settings.php';

add_action('init', function(){
    add_rewrite_rule('^vpn-status/?$', 'index.php?hovpnm_status=1', 'top');
});

add_filter('query_vars', function($q){ $q[] = 'hovpnm_status'; return $q; });

add_action('template_redirect', function(){
    if (get_query_var('hovpnm_status')) {
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>VPN Status</title></head><body><h1>VPN Status</h1><p>Coming soon…</p></body></html>';
        exit;
    }
});
