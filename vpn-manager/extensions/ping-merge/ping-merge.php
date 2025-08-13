<?php
namespace HOVPNM\Ext\PingMerge;
if (!defined('ABSPATH')) { exit; }

use function HOVPNM\Core\add_server_action_ex;
use function HOVPNM\Core\remove_server_action;

add_action('init', function(){
    // Replace separate ping buttons with one combined Ping if both extensions active
    $active = get_option('vpnpm_active_extensions', []);
    if (!is_array($active)) $active = [];
    if (in_array('server-ping', $active, true) && in_array('checkhost-ping', $active, true)) {
        remove_server_action('server-ping');
        remove_server_action('checkhost-ping');
        remove_server_action('ping');
    add_server_action_ex('ping', '', __('Ping','hovpnm'), __NAMESPACE__ . '\\action_ping');
    }
});

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_hovpnm') return;
        $script = <<<'JS'
jQuery(function($){
    $(document).on('click', '.button[data-action="ping"]', function(e){
        e.preventDefault();
        var b=$(this), id=b.data('id');
        b.prop('disabled', true);
        var d1=$.Deferred(), d2=$.Deferred();
        var spNonce = (window.HOVPNM_SP && HOVPNM_SP.nonce) ? HOVPNM_SP.nonce : '';
        var chNonce = (window.HOVPNM_CH && HOVPNM_CH.nonce) ? HOVPNM_CH.nonce : '';
        $.post(ajaxurl, { action:'hovpnm_srv_ping', id:id, _ajax_nonce: spNonce }, function(){ d1.resolve(); }).fail(function(){ d1.resolve(); });
        $.post(ajaxurl, { action:'hovpnm_ch_ping', id:id, force:1, _ajax_nonce: chNonce }, function(){ d2.resolve(); }).fail(function(){ d2.resolve(); });
        $.when(d1,d2).always(function(){ b.prop('disabled', false); });
    });
});
JS;
        wp_add_inline_script('jquery', $script);
});
