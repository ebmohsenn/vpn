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
            'checkhost_nodes'   => '', // comma-separated node codes
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

    public static function field_telegram_chat_ids() {
        $opts = self::get_settings();
        printf(
            '<input type="text" name="%1$s[telegram_chat_ids]" value="%2$s" class="regular-text" placeholder="12345, -100987654321" />'
            . '<p class="description">%3$s</p>',
            esc_attr(self::OPTION),
            esc_attr($opts['telegram_chat_ids']),
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

    public static function field_checkhost_nodes() {
        $opts = self::get_settings();
        $val = isset($opts['checkhost_nodes']) ? (string)$opts['checkhost_nodes'] : '';
                echo '<div id="vpnpm-checkhost-nodes-wrap">';
                echo '<input type="text" class="regular-text" id="vpnpm-checkhost-nodes-input" name="' . esc_attr(self::OPTION) . '[checkhost_nodes]" value="' . esc_attr($val) . '" placeholder="ir1.node.check-host.net, ir2.node.check-host.net" />';
                echo '<p class="description">' . esc_html__('Comma-separated Check-Host node hostnames. Or click Load Nodes to pick from a list.', 'vpnserver') . '</p>';
                echo '<p><button type="button" class="button" id="vpnpm-load-checkhost-nodes">' . esc_html__('Load Nodes', 'vpnserver') . '</button> ';
                echo '<label style="margin-left:8px"><input type="checkbox" id="vpnpm-merge-checkhost-nodes" checked> ' . esc_html__('Merge into current selection (uncheck to replace)', 'vpnserver') . '</label></p>';
                echo '<div id="vpnpm-checkhost-node-list" style="max-height:200px; overflow:auto; border:1px solid #ccd0d4; padding:8px; display:none"></div>';
                echo '</div>';
                echo "<script>(function(){\n".
                "var btn=document.getElementById('vpnpm-load-checkhost-nodes');\n".
                "var list=document.getElementById('vpnpm-checkhost-node-list');\n".
                "var input=document.getElementById('vpnpm-checkhost-nodes-input');\n".
                "if(!btn||!list||!input){return;}\n".
                "function syncInputFromChecks(){\n".
                "  var checks=list.querySelectorAll('input[type=checkbox]:checked');\n".
                "  var selected=Array.prototype.map.call(checks,function(c){return c.value;});\n".
                "  var merge=document.getElementById('vpnpm-merge-checkhost-nodes');\n".
                "  if (merge && merge.checked){\n".
                "    var manual=input.value.split(',').map(function(s){return s.trim();}).filter(Boolean);\n".
                "    var set={}; manual.concat(selected).forEach(function(h){ set[h]=true; });\n".
                "    input.value=Object.keys(set).join(', ');\n".
                "  } else {\n".
                "    input.value=selected.join(', ');\n".
                "  }\n".
                "}\n".
                "btn.addEventListener('click', function(){\n".
                "  btn.disabled=true; btn.textContent='" . esc_js(__('Loading...', 'vpnserver')) . "';\n".
                "  var data=new URLSearchParams();\n".
                "  data.append('action','vpnpm_list_checkhost_nodes');\n".
                "  data.append('_ajax_nonce','" . esc_js(wp_create_nonce('vpnpm-nonce')) . "');\n".
                "  fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString()})\n".
                "    .then(function(r){ return r.json(); })\n".
                "    .then(function(json){\n".
                "      btn.disabled=false; btn.textContent='" . esc_js(__('Reload Nodes', 'vpnserver')) . "';\n".
                "      if(!json||!json.success||!json.data||!Array.isArray(json.data.nodes)){ alert('Failed to load nodes'); return; }\n".
                "      list.innerHTML='';\n".
                "      var selected=input.value.split(',').map(function(s){return s.trim();}).filter(Boolean);\n".
                "      json.data.nodes.forEach(function(row){\n".
                "        var host = (row && row.host) ? String(row.host) : '';\n".
                "        var label = (row && row.label) ? String(row.label) : host;\n".
                "        var lbl=document.createElement('label'); lbl.style.display='block';\n".
                "        var cb=document.createElement('input'); cb.type='checkbox'; cb.value=host; cb.checked = selected.some(function(h){ return h===host; }); cb.addEventListener('change', syncInputFromChecks);\n".
                "        var span=document.createElement('span'); span.textContent=' '+label+' ('+host+')';\n".
                "        lbl.appendChild(cb); lbl.appendChild(span); list.appendChild(lbl);\n".
                "      });\n".
                "      list.style.display='block';\n".
                "    })\n".
                "    .catch(function(){ btn.disabled=false; btn.textContent='" . esc_js(__('Load Nodes', 'vpnserver')) . "'; alert('Failed to load nodes'); });\n".
                "});\n".
                "})();</script>";
    }

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
