<?php
/**
 * Telegram notification helpers for VPN Server Manager.
 *
 * Reads credentials from plugin settings saved by Vpnpm_Settings (vpnpm_settings option):
 * - telegram_bot_token
 * - telegram_chat_ids (comma-separated)
 */

defined('ABSPATH') || exit;

if (!function_exists('vpnpm_send_telegram_message')):
/**
 * Send a message to Telegram via Bot API.
 * - Uses wp_remote_post
 * - Splits messages longer than 4096 characters into chunks
 * - Supports multiple chat IDs (comma-separated)
 * - Returns true if all messages succeed, false otherwise
 *
 * @param string $message
 * @param string|null $chatIds Optional override for chat ID(s); comma-separated string
 * @param string $parse_mode Optional Telegram parse mode (Markdown, HTML), default empty (plain text)
 * @return bool
 */
function vpnpm_send_telegram_message($message, $chatIds = null, $parse_mode = '', &$error = null) {
       if (!class_exists('Vpnpm_Settings')) {
	       $error = 'Settings class not found.';
	       return false;
       }
       $opts = Vpnpm_Settings::get_settings();
       $token = isset($opts['telegram_bot_token']) ? trim((string) $opts['telegram_bot_token']) : '';
       $storedChats = isset($opts['telegram_chat_ids']) ? trim((string) $opts['telegram_chat_ids']) : '';
       $chats = $chatIds !== null ? trim((string) $chatIds) : $storedChats;
       if ($token === '' || $chats === '') {
	       $error = 'Telegram bot token or chat ID not configured.';
	       return false; // Not configured
       }

       $chatIdArray = array_map('trim', explode(',', $chats));

       $text = (string) $message;
       $maxLen = 4096; // Telegram max message length

       $ok = true;

       foreach ($chatIdArray as $chat) {
	       // Split message into chunks
	       $chunks = [];
	       if (strlen($text) <= $maxLen) {
		       $chunks = [$text];
	       } else {
		       $lines = preg_split("/\r?\n/", $text);
		       $buf = '';
		       foreach ($lines as $line) {
			       $candidate = $buf === '' ? $line : ($buf . "\n" . $line);
			       if (strlen($candidate) > $maxLen) {
				       if ($buf !== '') { $chunks[] = $buf; }
				       $buf = $line;
				       if (strlen($buf) > $maxLen) {
					       while (strlen($buf) > $maxLen) {
						       $chunks[] = substr($buf, 0, $maxLen);
						       $buf = substr($buf, $maxLen);
					       }
				       }
			       } else {
				       $buf = $candidate;
			       }
		       }
		       if ($buf !== '') { $chunks[] = $buf; }
	       }

	       $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';

	       foreach ($chunks as $chunk) {
		       $args = [
			       'timeout'  => 10,
			       'blocking' => true,
			       'headers'  => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
			       'body'     => [
				       'chat_id'                  => $chat,
				       'text'                     => $chunk,
				       'disable_web_page_preview' => true,
			       ],
		       ];

		       if ($parse_mode !== '') {
			       $args['body']['parse_mode'] = $parse_mode;
		       }

		       $resp = wp_remote_post($endpoint, $args);

		       if (is_wp_error($resp)) {
			       $ok = false;
			       $error = 'Telegram send error: ' . $resp->get_error_message();
			       error_log('[vpnserver] ' . $error);
			       break 2; // exit both loops on error
		       }
		       $code = wp_remote_retrieve_response_code($resp);
		       if ($code < 200 || $code >= 300) {
			       $ok = false;
			       $body = wp_remote_retrieve_body($resp);
			       $error = 'Telegram HTTP ' . $code . ' response: ' . $body;
			       error_log('[vpnserver] ' . $error);
			       break 2;
		       }
	       }
       }

       return $ok;
}
endif;

// Back-compat wrapper in case older code calls this name directly
if (!function_exists('send_telegram_message')):
function send_telegram_message($message) {
	return vpnpm_send_telegram_message($message);
}
endif;

/**
 * Format a MarkdownV2 styled VPN status message for Telegram.
 *
 * @param array $servers Each server is an associative array with keys:
 *   - name: string
 *   - status: string ('active' or 'down')
 *   - ping: int|string
 *   - type: string
 * @return string MarkdownV2 formatted status message
 */
function vpnpm_format_vpn_status_message_stylish(array $servers): string {
	$lines = [];
	$lines[] = '*VPN Status Update*';
	$escape_md = function($text) {
		// Escape all Telegram MarkdownV2 special chars, including '-'
		return preg_replace('/([_\*\[\]()~`>#+\-=|{}.!\-])/', '\\$1', (string)$text);
	};
	$date = date('Y-m-d H:i');
	$lines[] = '_As of ' . $escape_md($date) . '_';
	$lines[] = '';
		$escape_md = function($text) {
			// Escape all Telegram MarkdownV2 special chars, including '-'
			return preg_replace('/([_\*\[\]()~`>#+\-=|{}.!\\-])/', '\\\\$1', $text);
		};
		foreach ($servers as $srv) {
			$name = isset($srv['name']) ? $escape_md((string)$srv['name']) : '';
			$status = isset($srv['status']) ? strtolower((string)$srv['status']) : '';
			$emoji = $status === 'active' ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x94\xB4"; // green or red circle
			$ping = isset($srv['ping']) ? $srv['ping'] : '';
			$type = isset($srv['type']) ? $escape_md((string)$srv['type']) : '';
			$status_str = ($status === 'active' ? '*Online*' : '*Down*');
			$block = "{$emoji} *{$name}* {$status_str}\n";
			$block .= "Ping: `{$ping}` ms\n";
			$block .= "Type: _{$type}_";
			$lines[] = $block;
			$lines[] = '';
		}
		return trim(implode("\n", $lines));
}

// Example usage:
// $servers = [
//   ['name' => 'Server 1', 'status' => 'active', 'ping' => 34, 'type' => 'WireGuard'],
//   ['name' => 'Server 2', 'status' => 'down', 'ping' => 0, 'type' => 'OpenVPN'],
// ];
// $msg = vpnpm_format_vpn_status_message_stylish($servers);
// vpnpm_send_telegram_message($msg, null, 'MarkdownV2');