<?php
namespace HOVPNM\Ext\CheckhostPing;
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_hovpnm_ch_ping', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    check_ajax_referer('hovpnm');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $force = !empty($_POST['force']) ? true : false;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    $cache_key = 'hovpnm_ch_ping_' . $id;
    $opts = get_option(OPT, defaults());
    $ttl = isset($opts['cache_ttl']) ? (int)$opts['cache_ttl'] : 300;
    $value = $force ? false : get_transient($cache_key);
    if ($value === false) {
        // Placeholder: simulate an aggregated value
        $value = rand(40, 180);
        set_transient($cache_key, $value, $ttl);
    }
    wp_send_json_success(['id'=>$id,'ping'=>$value]);
});
