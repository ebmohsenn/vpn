<?php
namespace HOVPNM\Ext\Graphs;
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_hovpnm_graph_data', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) wp_send_json_error(['message'=>'Invalid id'], 400);
    // Placeholder dataset
    $points = [];
    for ($i=24; $i>=0; $i--) { $points[] = ['t' => time() - $i*3600, 'v' => rand(30, 200)]; }
    wp_send_json_success(['id'=>$id, 'series'=>$points]);
});
