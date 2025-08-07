<?php
defined('ABSPATH') || exit;

function vpnpm_check_server_status($host, $port, $timeout = 3) {
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

    if ($connection) {
        fclose($connection);
        return 'online';
    } else {
        return 'offline';
    }
}