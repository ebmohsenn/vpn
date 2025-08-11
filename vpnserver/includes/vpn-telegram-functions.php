<?php
/**
 * Telegram notification helpers for VPN Server Manager.
 *
 * Reads credentials from constants:
 * - VPNSERVER_TELEGRAM_BOT_TOKEN
 * - VPNSERVER_TELEGRAM_CHAT_ID
 *
 * Define them in wp-config.php or in the plugin bootstrap before using this.
 */

defined('ABSPATH') || exit;

if (!function_exists('vpnpm_send_telegram_message')):
/**
 * Send a message to Telegram via Bot API.
 * - Uses wp_remote_post
 * - Splits messages longer than 4096 characters into chunks
 * - Returns true if all chunks succeed, false otherwise
 *
 * @param string $message
 * @return bool
 */
function vpnpm_send_telegram_message($message) {
	$token = defined('VPNSERVER_TELEGRAM_BOT_TOKEN') ? trim((string) VPNSERVER_TELEGRAM_BOT_TOKEN) : '';
	$chat  = defined('VPNSERVER_TELEGRAM_CHAT_ID') ? trim((string) VPNSERVER_TELEGRAM_CHAT_ID) : '';
	if ($token === '' || $chat === '') {
		return false; // Not configured
	}

	$text = (string) $message;
	$maxLen = 4096; // Telegram max message length
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
	$ok = true;
	foreach ($chunks as $chunk) {
		$resp = wp_remote_post($endpoint, [
			'timeout' => 10,
			'blocking' => true,
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8' ],
			'body' => [
				'chat_id' => $chat,
				'text' => $chunk,
				'disable_web_page_preview' => true,
			],
		]);
		if (is_wp_error($resp)) {
			$ok = false;
			error_log('[vpnserver] Telegram send error: ' . $resp->get_error_message());
			break;
		}
		$code = wp_remote_retrieve_response_code($resp);
		if ($code < 200 || $code >= 300) {
			$ok = false;
			error_log('[vpnserver] Telegram HTTP ' . $code . ' response: ' . wp_remote_retrieve_body($resp));
			break;
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
