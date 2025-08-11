<?php
/**
 * Plugin settings page and cron scheduling integration.
 */

defined('ABSPATH') || exit;

if (!class_exists('Vpnpm_Settings')):
class Vpnpm_Settings {
    const OPTION = 'vpnpm_settings';

    public function __construct() {
        // Run after the parent menu is registered to avoid access glitches
        add_action('admin_menu', [$this, 'add_menu'], 30);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [__CLASS__, 'maybe_schedule']);
        add_filter('cron_schedules', [__CLASS__, 'register_cron_schedules']);
    }

    public static function capability() {
        return apply_filters('vpnpm_settings_cap', 'manage_options');
    }

    public static function defaults() {
        return [
            'telegram_bot_token' => '',
            'telegram_chat_ids' => '', // comma-separated list
            'enable_cron'       => 1,
            'enable_telegram'   => 1,
            'cron_interval'     => '10', // minutes: '5','10','15'
            'telegram_time_mode'=> 'jalali', // 'jalali' or 'system'
            'ping_source'       => 'server', // 'server' or 'checkhost'
            'checkhost_nodes'   => '', // deprecated, use Settings > VPN Server Manager Settings for node selection
            'telegram_ping_source' => 'server', // 'server','checkhost','both'
        ];
    }

    public static function get_settings() {
        $opts = get_option(self::OPTION, []);
        $opts = is_array($opts) ? $opts : [];
        return wp_parse_args($opts, self::defaults());
    }

    public function add_menu() {
        $cap = self::capability();
        add_submenu_page(
            'vpmgr',
            __('VPN Manager Settings', 'vpnserver'),
            __('Plugin Settings', 'vpnserver'),
            $cap,
            'vpn-manager-settings',
            [$this, 'render_settings_page']
        );

        // Also expose under Settings to ensure accessibility even if parent menu is filtered
        add_options_page(
            __('VPN Manager Settings', 'vpnserver'),
            __('VPN Manager', 'vpnserver'),
            $cap,
            'vpn-manager-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'vpnpm_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
                'default'           => self::defaults(),
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'vpnpm_settings_section',
            __('Notifications & Cron', 'vpnserver'),
            function() {
                echo '<p>' . esc_html__('Configure Telegram notifications and automatic pinging.', 'vpnserver') . '</p>';
            },
            'vpnpm_settings_page'
        );

        add_settings_field(
            'telegram_bot_token',
            __('Telegram Bot Token', 'vpnserver'),
            [__CLASS__, 'field_telegram_bot_token'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'telegram_chat_ids',
            __('Telegram Chat IDs', 'vpnserver'),
            [__CLASS__, 'field_telegram_chat_ids'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'enable_cron',
            __('Enable Automatic Pinging', 'vpnserver'),
            [__CLASS__, 'field_enable_cron'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'enable_telegram',
            __('Send Telegram on Updates', 'vpnserver'),
            [__CLASS__, 'field_enable_telegram'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'cron_interval',
            __('Cron Interval', 'vpnserver'),
            [__CLASS__, 'field_cron_interval'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'telegram_time_mode',
            __('Telegram Time Format', 'vpnserver'),
            [__CLASS__, 'field_telegram_time_mode'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'ping_source',
            __('Ping Source', 'vpnserver'),
            [__CLASS__, 'field_ping_source'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'checkhost_nodes',
            __('Check-Host Nodes', 'vpnserver'),
            [__CLASS__, 'field_checkhost_nodes'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );

        add_settings_field(
            'telegram_ping_source',
            __('Telegram Ping Source', 'vpnserver'),
            [__CLASS__, 'field_telegram_ping_source'],
            'vpnpm_settings_page',
            'vpnpm_settings_section'
        );
    }

    public static function sanitize_settings($input) {
        $out = self::defaults();
        $in = is_array($input) ? $input : [];

        // Bot token: basic validation (digits:tokenpart)
        $rawToken = isset($in['telegram_bot_token']) ? (string) $in['telegram_bot_token'] : '';
        $rawToken = trim(wp_kses_post($rawToken));
        if ($rawToken !== '' && preg_match('/^[0-9]+:[A-Za-z0-9_\-]{10,}$/', $rawToken)) {
            $out['telegram_bot_token'] = $rawToken;
        } else {
            $out['telegram_bot_token'] = '';
        }

        // Chat IDs: digits and optional leading - for supergroups
        $raw = isset($in['telegram_chat_ids']) ? (string) $in['telegram_chat_ids'] : '';
        $ids = array_filter(array_map('trim', explode(',', $raw)), function($id){
            return $id !== '' && preg_match('/^-?\d+$/', $id);
        });
        $out['telegram_chat_ids'] = implode(', ', $ids);

        $out['enable_cron'] = !empty($in['enable_cron']) ? 1 : 0;
        $out['enable_telegram'] = !empty($in['enable_telegram']) ? 1 : 0;

        $allowed = ['5','10','15'];
        $interval = isset($in['cron_interval']) ? (string) $in['cron_interval'] : '10';
        $out['cron_interval'] = in_array($interval, $allowed, true) ? $interval : '10';

    // Telegram time mode
    $tm = isset($in['telegram_time_mode']) ? (string)$in['telegram_time_mode'] : 'jalali';
    $out['telegram_time_mode'] = in_array($tm, ['jalali','system'], true) ? $tm : 'jalali';

        // Ping source
        $ps = isset($in['ping_source']) ? (string)$in['ping_source'] : 'server';
        $out['ping_source'] = in_array($ps, ['server','checkhost'], true) ? $ps : 'server';

        // Check-Host nodes: comma-separated codes/hostnames; sanitize as simple list
        $rawNodes = isset($in['checkhost_nodes']) ? (string)$in['checkhost_nodes'] : '';
        $nodes = array_filter(array_map(function($s){
            $s = trim($s);
            // allow letters, digits, dash, dot
            if ($s !== '' && preg_match('/^[A-Za-z0-9\.-]+$/', $s)) {
                return $s;
            }
            return '';
        }, explode(',', $rawNodes)));
        $out['checkhost_nodes'] = implode(', ', $nodes);

        // Telegram ping source
        $tps = isset($in['telegram_ping_source']) ? (string)$in['telegram_ping_source'] : 'server';
        $out['telegram_ping_source'] = in_array($tps, ['server','checkhost','both'], true) ? $tps : 'server';

        // Reschedule when settings change
        add_action('updated_option', function($option, $old, $new){
            if ($option === self::OPTION) {
                self::maybe_schedule(true);
            }
        }, 10, 3);

        return $out;
    }

    public static function field_telegram_bot_token() {
        $opts = self::get_settings();
        printf(
            '<input type="text" name="%1$s[telegram_bot_token]" value="%2$s" class="regular-text" placeholder="123456789:ABCDEF..." />'
            . '<p class="description">%3$s</p>',
            esc_attr(self::OPTION),
            esc_attr($opts['telegram_bot_token']),
            esc_html__('Enter your Telegram bot token. Required to send messages.', 'vpnserver')
        );
    }

    public static function field_checkhost_nodes() {
        echo '<p class="description">' . esc_html__('Deprecated: Node selection has moved. Go to Settings ▸ VPN Server Manager Settings to choose from the official list. Manual entry is no longer supported.', 'vpnserver') . '</p>';
        echo '<input type="text" class="regular-text" disabled value="" placeholder="Use the new settings page" />';
    }

    public static function field_telegram_time_mode() {
        $opts = self::get_settings();
        $val = isset($opts['telegram_time_mode']) ? (string)$opts['telegram_time_mode'] : 'jalali';
        echo '<label><input type="radio" name="' . esc_attr(self::OPTION) . '[telegram_time_mode]" value="jalali" ' . checked($val, 'jalali', false) . '> ' . esc_html__('Jalali (Persian calendar)', 'vpnserver') . '</label><br />';
        echo '<label><input type="radio" name="' . esc_attr(self::OPTION) . '[telegram_time_mode]" value="system" ' . checked($val, 'system', false) . '> ' . esc_html__('System timezone (site setting)', 'vpnserver') . '</label>';
        echo '<p class="description">' . esc_html__('Choose how the time is displayed in Telegram messages.', 'vpnserver') . '</p>';
    }

    public static function field_ping_source() {
        $opts = self::get_settings();
        $val = isset($opts['ping_source']) ? (string)$opts['ping_source'] : 'server';
        echo '<label><input type="radio" name="' . esc_attr(self::OPTION) . '[ping_source]" value="server" ' . checked($val, 'server', false) . '> ' . esc_html__('Server Location', 'vpnserver') . '</label><br />';
        echo '<label><input type="radio" name="' . esc_attr(self::OPTION) . '[ping_source]" value="checkhost" ' . checked($val, 'checkhost', false) . '> ' . esc_html__('Check-Host.net', 'vpnserver') . '</label>';
        echo '<p class="description">' . esc_html__('Choose where pings are measured from.', 'vpnserver') . '</p>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var ph=document.querySelector("[name=\"' . esc_js(self::OPTION) . '[ping_source]\"]:checked");function tog(){var s=document.querySelector("#vpnpm-checkhost-nodes-wrap");if(!s)return; s.style.display=(document.querySelector("[name=\"' . esc_js(self::OPTION) . '[ping_source]\"]:checked").value==="checkhost")?"block":"none";}document.querySelectorAll("[name=\"' . esc_js(self::OPTION) . '[ping_source]\"]").forEach(function(r){r.addEventListener("change",tog)});tog();});</script>';
    }

    // Removed old free-text field; see the new settings page

    public static function field_telegram_ping_source() {
        $opts = self::get_settings();
        $val = isset($opts['telegram_ping_source']) ? (string)$opts['telegram_ping_source'] : 'server';
        echo '<select name="' . esc_attr(self::OPTION) . '[telegram_ping_source]">';
        $choices = [
            'server'    => __('Server ping', 'vpnserver'),
            'checkhost' => __('Check-Host ping', 'vpnserver'),
            'both'      => __('Both', 'vpnserver'),
        ];
        foreach ($choices as $k=>$label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val,$k,false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Which ping to include in Telegram notifications.', 'vpnserver') . '</p>';
    }

    public function render_settings_page() {
    if (!current_user_can(self::capability())) return;
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('VPN Manager Settings', 'vpnserver') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('vpnpm_settings_group');
        do_settings_sections('vpnpm_settings_page');
        submit_button();
        echo '</form>';
    // Test Telegram Notification button
    echo '<hr/>';
    echo '<div id="vpnpm-settings-msg" aria-live="polite"></div>';
    echo '<p><button type="button" class="button" id="vpnpm-settings-telegram-test">' . esc_html__('Send Test Telegram Notification', 'vpnserver') . '</button></p>';
        echo '</div>';
    }

    public static function register_cron_schedules($schedules) {
        $schedules['vpnpm_5m']  = ['interval' => 5 * 60,  'display' => __('Every 5 Minutes', 'vpnserver')];
        $schedules['vpnpm_10m'] = ['interval' => 10 * 60, 'display' => __('Every 10 Minutes', 'vpnserver')];
        $schedules['vpnpm_15m'] = ['interval' => 15 * 60, 'display' => __('Every 15 Minutes', 'vpnserver')];
        return $schedules;
    }

    public static function maybe_schedule($force = false) {
        $opts = self::get_settings();
        $enabled = !empty($opts['enable_cron']);
        $interval = isset($opts['cron_interval']) ? (string) $opts['cron_interval'] : '10';
        $recurrences = [ '5' => 'vpnpm_5m', '10' => 'vpnpm_10m', '15' => 'vpnpm_15m' ];
        $recurrence = isset($recurrences[$interval]) ? $recurrences[$interval] : 'vpnpm_10m';

        $ts = wp_next_scheduled('vpnpm_test_all_servers_cron');
        if (!$enabled) {
            if ($ts) {
                wp_unschedule_event($ts, 'vpnpm_test_all_servers_cron');
            }
            return;
        }

        // If scheduled but force reschedule or wrong schedule, unschedule and re-add
        if ($ts && $force) {
            wp_unschedule_event($ts, 'vpnpm_test_all_servers_cron');
            $ts = false;
        }
        if (!$ts) {
            wp_schedule_event(time() + 60, $recurrence, 'vpnpm_test_all_servers_cron');
        }
    }
}
endif;
