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
            'telegram_chat_ids' => '', // comma-separated list
            'enable_cron'       => 1,
            'enable_telegram'   => 1,
            'cron_interval'     => '10', // minutes: '5','10','15'
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
            'vpn-manager',
            __('VPN Manager Settings', 'vpnserver'),
            __('Settings', 'vpnserver'),
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
    }

    public static function sanitize_settings($input) {
        $out = self::defaults();
        $in = is_array($input) ? $input : [];

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

        // Reschedule when settings change
        add_action('updated_option', function($option, $old, $new){
            if ($option === self::OPTION) {
                self::maybe_schedule(true);
            }
        }, 10, 3);

        return $out;
    }

    public static function field_telegram_chat_ids() {
        $opts = self::get_settings();
        printf(
            '<textarea name="%1$s[telegram_chat_ids]" rows="3" cols="50" class="large-text" placeholder="e.g. 12345, -100987654321">%2$s</textarea><p class="description">%3$s</p>',
            esc_attr(self::OPTION),
            esc_textarea($opts['telegram_chat_ids']),
            esc_html__('Comma-separated list of Telegram chat IDs (user, group, or channel).', 'vpnserver')
        );
    }

    public static function field_enable_cron() {
        $opts = self::get_settings();
        printf(
            '<label><input type="checkbox" name="%1$s[enable_cron]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPTION),
            checked(1, (int) $opts['enable_cron'], false),
            esc_html__('Run background pinging on a schedule.', 'vpnserver')
        );
    }

    public static function field_enable_telegram() {
        $opts = self::get_settings();
        printf(
            '<label><input type="checkbox" name="%1$s[enable_telegram]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPTION),
            checked(1, (int) $opts['enable_telegram'], false),
            esc_html__('Send Telegram notifications after pings.', 'vpnserver')
        );
    }

    public static function field_cron_interval() {
        $opts = self::get_settings();
        $val = (string) $opts['cron_interval'];
        echo '<select name="' . esc_attr(self::OPTION) . '[cron_interval]">';
        $choices = [
            '5'  => __('Every 5 minutes', 'vpnserver'),
            '10' => __('Every 10 minutes', 'vpnserver'),
            '15' => __('Every 15 minutes', 'vpnserver'),
        ];
        foreach ($choices as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
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
