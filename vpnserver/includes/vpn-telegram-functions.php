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
function vpnpm_send_telegram_message($message, $chatIds = null, $parse_mode = '') {
	if (!class_exists('Vpnpm_Settings')) {
		return false;
	}
	$opts = Vpnpm_Settings::get_settings();
	$token = isset($opts['telegram_bot_token']) ? trim((string) $opts['telegram_bot_token']) : '';
	$storedChats = isset($opts['telegram_chat_ids']) ? trim((string) $opts['telegram_chat_ids']) : '';
	$chats = $chatIds !== null ? trim((string) $chatIds) : $storedChats;
	if ($token === '' || $chats === '') {
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
				error_log('[vpnserver] Telegram send error: ' . $resp->get_error_message());
				break 2; // exit both loops on error
			}
			$code = wp_remote_retrieve_response_code($resp);
			if ($code < 200 || $code >= 300) {
				$ok = false;
				error_log('[vpnserver] Telegram HTTP ' . $code . ' response: ' . wp_remote_retrieve_body($resp));
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