<?php
namespace HOVPNM\Ext\Telegram;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/settings.php';

add_action('hovpnm_alert', __NAMESPACE__ . '\\send_alert', 10, 2);

function send_alert($title, $message) {
    $opts = get_option(OPT, defaults());
    if (empty($opts['token']) || empty($opts['chat_ids'])) return;
    $chat_ids = array_filter(array_map('trim', explode(',', $opts['chat_ids'])));
    foreach ($chat_ids as $chat) {
        wp_remote_post('https://api.telegram.org/bot' . $opts['token'] . '/sendMessage', [
            'timeout' => 10,
            'body' => [
                'chat_id' => $chat,
                'text' => '[' . sanitize_text_field($title) . "]\n" . wp_strip_all_tags($message),
            ]
        ]);
    }
}
