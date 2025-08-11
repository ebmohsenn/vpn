<?php
// Convert Gregorian timestamp to Jalali (Persian) date. Returns yyyy/mm/dd HH:ii in 24h.
if (!function_exists('vpnpm_gregorian_to_jalali_datetime')) {
	function vpnpm_gregorian_to_jalali_datetime($timestamp = null) {
		// Jalali conversion based on Tehran timezone
		// Inputs: Unix timestamp (seconds); if null, uses current time
		try {
			$tz = new \DateTimeZone('Asia/Tehran');
		} catch (\Exception $e) {
			$tz = new \DateTimeZone('UTC');
		}
		if ($timestamp !== null) {
			$dt = new \DateTimeImmutable('@' . (int)$timestamp);
			$dt = $dt->setTimezone($tz);
		} else {
			// Use current server time but represent in Tehran timezone
			$dt = new \DateTimeImmutable('now', $tz);
		}
		$g_y = (int)$dt->format('Y');
		$g_m = (int)$dt->format('n');
		$g_d = (int)$dt->format('j');
		list($j_y, $j_m, $j_d) = vpnpm_g2j($g_y, $g_m, $g_d);
		$h = $dt->format('H');
		$i = $dt->format('i');
		return sprintf('%04d/%02d/%02d %s:%s', $j_y, $j_m, $j_d, $h, $i);
	}
	function vpnpm_g2j($g_y, $g_m, $g_d) {
		$g_days_in_month = [0,31,28,31,30,31,30,31,31,30,31,30,31];
		$j_days_in_month = [0,31,31,31,31,31,31,30,30,30,30,30,29];
		$gy = $g_y-1600; $gm = $g_m-1; $gd = $g_d-1;
		$g_day_no = 365*$gy + (int)(($gy+3)/4) - (int)(($gy+99)/100) + (int)(($gy+399)/400);
		for ($i=0; $i<$gm; ++$i) $g_day_no += $g_days_in_month[$i+1];
		if ($gm>1 && (($g_y%4==0 && $g_y%100!=0) || ($g_y%400==0))) $g_day_no++;
		$g_day_no += $gd;
		$j_day_no = $g_day_no-79;
		$j_np = (int)($j_day_no/12053); $j_day_no %= 12053;
		$jy = 979+33*$j_np+4*(int)($j_day_no/1461); $j_day_no %= 1461;
		if ($j_day_no >= 366) { $jy += (int)(($j_day_no-366)/365); $j_day_no = ($j_day_no-366)%365; }
		for ($i=1; $i<=12 && $j_day_no >= $j_days_in_month[$i]; $i++) { $j_day_no -= $j_days_in_month[$i]; }
		$jm = $i; $jd = $j_day_no+1;
		return [$jy, $jm, $jd];
	}
}
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
	$escape_md = function($text) {
		return preg_replace('/([_\*\[\]()~`>#+\-=|{}.!\-])/', '\\$1', (string)$text);
	};
	$lines[] = '*VPN Status Update*';
	// Time mode from settings: 'jalali' or 'system'
	$timeStr = '';
	if (class_exists('Vpnpm_Settings')) {
		$opts = Vpnpm_Settings::get_settings();
		$mode = isset($opts['telegram_time_mode']) ? (string)$opts['telegram_time_mode'] : 'jalali';
		if ($mode === 'system') {
			// Site timezone per WP settings
			$timeStr = date_i18n('Y-m-d H:i');
		} else {
			$timeStr = vpnpm_gregorian_to_jalali_datetime();
		}
	} else {
		$timeStr = vpnpm_gregorian_to_jalali_datetime();
	}
	$lines[] = '_Time: ' . $escape_md($timeStr) . '_';
	$lines[] = '';
	// Partition and sort: active with ping ascending first, then offline/others
	$active = [];
	$offline = [];
	foreach ($servers as $srv) {
		$status = isset($srv['status']) ? strtolower((string)$srv['status']) : '';
		$pingVal = isset($srv['ping']) && $srv['ping'] !== null && $srv['ping'] !== '' ? (int)$srv['ping'] : null;
		if ($status === 'active' && $pingVal !== null) {
			$srv['__pingInt'] = $pingVal;
			$active[] = $srv;
		} else {
			$offline[] = $srv;
		}
	}
	usort($active, function($a, $b){
		return ($a['__pingInt'] ?? PHP_INT_MAX) <=> ($b['__pingInt'] ?? PHP_INT_MAX);
	});
	$ordered = array_merge($active, $offline);

	foreach ($ordered as $srv) {
		$status = isset($srv['status']) ? strtolower((string)$srv['status']) : '';
		$emoji = $status === 'active' ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x94\xB4"; // green or red circle
		$name = isset($srv['name']) ? $escape_md((string)$srv['name']) : '';
		$pingVal = isset($srv['ping']) && $srv['ping'] !== null && $srv['ping'] !== '' ? (string)$srv['ping'] : 'N/A';
		$chPingVal = isset($srv['ch_ping']) && $srv['ch_ping'] !== null && $srv['ch_ping'] !== '' ? (string)$srv['ch_ping'] : 'N/A';
		$type = isset($srv['type']) ? $escape_md((string)$srv['type']) : '';
		$loc = isset($srv['location']) ? $escape_md((string)$srv['location']) : '';
		$pingChoice = 'server';
		if (class_exists('Vpnpm_Settings')) {
			$opts = Vpnpm_Settings::get_settings();
			$pingChoice = isset($opts['telegram_ping_source']) ? (string)$opts['telegram_ping_source'] : 'server';
		}
		$pingPart = '';
		if ($pingChoice === 'both') {
			$pingPart = 'Ping S: ' . $escape_md($pingVal) . ' ms \| CH: ' . $escape_md($chPingVal) . ' ms';
		} elseif ($pingChoice === 'checkhost') {
			$pingPart = 'Ping: ' . $escape_md($chPingVal) . ' ms';
		} else {
			$pingPart = 'Ping: ' . $escape_md($pingVal) . ' ms';
		}
		// Format: Name: \| Ping ... \| type \| location
		$line = sprintf('%s %s: \| %s \| %s \| %s', $emoji, $name, $pingPart, $type, ($loc !== '' ? $loc : ''));
		$lines[] = $line;
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