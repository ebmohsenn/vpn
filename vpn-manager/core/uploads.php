<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

// Allow .ovpn uploads for admins
add_filter('upload_mimes', function($mimes){
    if (!current_user_can('manage_options')) return $mimes;
    $mimes['ovpn'] = 'application/x-openvpn-profile';
    $mimes['csv'] = 'text/csv';
    return $mimes;
});

// Extra check to allow .ovpn through filetype validation
add_filter('wp_check_filetype_and_ext', function($types, $file, $filename, $mimes){
    if (!current_user_can('manage_options')) return $types;
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (strtolower($ext) === 'ovpn') {
        $types['ext'] = 'ovpn';
        $types['type'] = 'application/x-openvpn-profile';
        $types['proper_filename'] = $filename;
    }
    return $types;
}, 10, 4);
