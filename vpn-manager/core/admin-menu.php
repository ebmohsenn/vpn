<?php
namespace HOVPNM\Core;
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function() {
    add_menu_page(
        __('HO VPN Manager','hovpnm'),
        __('HO VPN Manager','hovpnm'),
        'manage_options',
        'hovpnm',
        __NAMESPACE__ . '\\render_dashboard',
        'dashicons-shield',
        30
    );
    add_submenu_page('hovpnm', __('Extensions','hovpnm'), __('Extensions','hovpnm'), 'manage_options', 'hovpnm-extensions', __NAMESPACE__ . '\\render_extensions');
    add_submenu_page('hovpnm', __('Add Server','hovpnm'), __('Add Server','hovpnm'), 'manage_options', 'hovpnm-add-server', __NAMESPACE__ . '\\render_add_server');
    add_submenu_page('hovpnm', __('Settings','hovpnm'), __('Settings','hovpnm'), 'manage_options', 'hovpnm-settings', __NAMESPACE__ . '\\render_settings');
    add_submenu_page('hovpnm', __('Auto-Ping Scheduler','hovpnm'), __('Auto-Ping Scheduler','hovpnm'), 'manage_options', 'hovpnm-scheduler', __NAMESPACE__ . '\\render_scheduler');
    add_submenu_page('hovpnm', __('Deleted Servers','hovpnm'), __('Deleted Servers','hovpnm'), 'manage_options', 'hovpnm-deleted', __NAMESPACE__ . '\\render_deleted');
});

// Enqueue admin CSS on our pages
add_action('admin_enqueue_scripts', function($hook){
    if ($hook === 'toplevel_page_hovpnm' || strpos($hook, 'hovpnm-') !== false) {
        wp_enqueue_style('hovpnm-admin', HOVPNM_PLUGIN_URL . 'core/assets/css/admin.css', [], HOVPNM_VERSION);
    if ($hook === 'toplevel_page_hovpnm') {
            wp_enqueue_script('hovpnm-dashboard', HOVPNM_PLUGIN_URL . 'core/assets/js/dashboard.js', ['jquery'], HOVPNM_VERSION, true);
            wp_localize_script('hovpnm-dashboard', 'HOVPNM_DASH', [
                'apiBase' => rest_url('hovpnm/v1/servers/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'msgNoChange' => __('No changes or update failed.','hovpnm'),
        'msgFail' => __('Update failed.','hovpnm'),
        'msgPingAll' => __('Ping All','hovpnm'),
        'msgPingingAll' => __('Pinging All...','hovpnm'),
        'msgNoPingBtns' => __('No ping actions available on this page.','hovpnm'),
            ]);
        }
    }
});

function render_dashboard() {
    do_action('vpnpm_before_dashboard_render');
    // Notices
    if (!empty($_GET['hovpnm_notice'])) {
        $msg = sanitize_text_field(wp_unslash($_GET['hovpnm_notice']));
        echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
    }
    echo '<p>'
        . '<a href="' . esc_url(admin_url('admin.php?page=hovpnm-add-server')) . '" class="button button-primary">' . esc_html__('Add Server','hovpnm') . '</a> '
        . '<button type="button" class="button hovpnm-ping-all">' . esc_html__('Ping All','hovpnm') . '</button>'
        . '</p>';
    include __DIR__ . '/templates/dashboard.php';
    do_action('vpnpm_after_dashboard_render');
}

function render_extensions() {
    $ext_dir = trailingslashit(dirname(__DIR__)) . 'extensions';
    $all = [];
    if (is_dir($ext_dir)) {
        foreach (scandir($ext_dir) as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $path = $ext_dir . '/' . $folder;
            if (!is_dir($path)) continue;
            $main = $path . '/' . $folder . '.php';
            if (!file_exists($main)) continue;
            $meta = [
                'name' => ucwords(str_replace(['-','_'],' ', $folder)),
                'desc' => 'No description provided.',
            ];
            $readme = $path . '/readme.txt';
            if (file_exists($readme)) {
                $meta['desc'] = esc_html(trim(file_get_contents($readme)));
            }
            $all[$folder] = $meta;
        }
    }
    $active = get_option('vpnpm_active_extensions', []);
    if (!is_array($active)) $active = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('hovpnm_ext_toggle')) {
        $new_active = isset($_POST['active']) && is_array($_POST['active']) ? array_values(array_map('sanitize_text_field', $_POST['active'])) : [];
        update_option('vpnpm_active_extensions', $new_active);
        $active = $new_active;
        echo '<div class="updated"><p>' . esc_html__('Extensions updated.', 'hovpnm') . '</p></div>';
    }
    echo '<div class="wrap"><h1>' . esc_html__('Extensions','hovpnm') . '</h1>';
    echo '<form method="post">';
    wp_nonce_field('hovpnm_ext_toggle');
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Extension','hovpnm') . '</th><th>' . esc_html__('Description','hovpnm') . '</th><th>' . esc_html__('Enabled','hovpnm') . '</th></tr></thead><tbody>';
    foreach ($all as $slug => $meta) {
        $checked = in_array($slug, $active, true) ? 'checked' : '';
        echo '<tr>'
            . '<td><strong>' . esc_html($meta['name']) . '</strong></td>'
            . '<td>' . esc_html($meta['desc']) . '</td>'
            . '<td><label><input type="checkbox" name="active[]" value="' . esc_attr($slug) . '" ' . $checked . '> ' . esc_html__('Enable','hovpnm') . '</label></td>'
            . '</tr>';
    }
    echo '</tbody></table><p><button class="button button-primary" type="submit">' . esc_html__('Save Changes','hovpnm') . '</button></p>';
    echo '</form></div>';
}

function render_settings() {
    if (!current_user_can('manage_options')) return;
    $all_cols = \HOVPNM\Core\ColumnsRegistry::$cols;
    $visible = get_option('hovpnm_visible_columns', array_keys($all_cols));
    if (!is_array($visible)) $visible = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('hovpnm_settings_columns')) {
        $new_visible = isset($_POST['visible_cols']) && is_array($_POST['visible_cols']) ? array_values(array_map('sanitize_text_field', $_POST['visible_cols'])) : [];
        update_option('hovpnm_visible_columns', $new_visible);
        $visible = $new_visible;
        echo '<div class="updated"><p>' . esc_html__('Settings saved.','hovpnm') . '</p></div>';
    }
    echo '<div class="wrap"><h1>' . esc_html__('Settings','hovpnm') . '</h1>';
    echo '<h2 class="title">' . esc_html__('Column Visibility','hovpnm') . '</h2>';
    echo '<form method="post">';
    wp_nonce_field('hovpnm_settings_columns');
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Column','hovpnm') . '</th><th>' . esc_html__('Visible','hovpnm') . '</th></tr></thead><tbody>';
    foreach ($all_cols as $id => $meta) {
        $checked = in_array($id, $visible, true) ? 'checked' : '';
        echo '<tr>'
            . '<td><strong>' . esc_html($meta['label']) . '</strong> <code>' . esc_html($id) . '</code></td>'
            . '<td><label><input type="checkbox" name="visible_cols[]" value="' . esc_attr($id) . '" ' . $checked . '> ' . esc_html__('Show','hovpnm') . '</label></td>'
            . '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Changes','hovpnm') . '</button></p>';
    echo '</form></div>';
}

function render_scheduler() {
    if (!current_user_can('manage_options')) return;
    $intervals = [
        'five_minutes' => __('Every 5 minutes','hovpnm'),
        'fifteen_minutes' => __('Every 15 minutes','hovpnm'),
        'thirty_minutes' => __('Every 30 minutes','hovpnm'),
        'hourly' => __('Hourly','hovpnm'),
        'six_hours' => __('Every 6 hours','hovpnm'),
        'twice_daily' => __('Twice Daily','hovpnm'),
        'daily' => __('Daily','hovpnm'),
    ];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('hovpnm_settings_scheduler')) {
        $interval = sanitize_text_field($_POST['hovpnm_sched_interval'] ?? 'hourly');
        $sources = isset($_POST['hovpnm_sched_sources']) && is_array($_POST['hovpnm_sched_sources']) ? array_values(array_map('sanitize_text_field', $_POST['hovpnm_sched_sources'])) : [];
        update_option('hovpnm_sched_interval', $interval);
        update_option('hovpnm_sched_sources', $sources);
        echo '<div class="updated"><p>' . esc_html__('Scheduler settings saved.','hovpnm') . '</p></div>';
    }
    $cur_int = get_option('hovpnm_sched_interval', 'hourly');
    $cur_src = get_option('hovpnm_sched_sources', ['server','checkhost']);
    echo '<div class="wrap"><h1>' . esc_html__('Auto-Ping Scheduler','hovpnm') . '</h1>';
    echo '<form method="post">'; wp_nonce_field('hovpnm_settings_scheduler');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>' . esc_html__('Interval','hovpnm') . '</th><td><select name="hovpnm_sched_interval">';
    foreach ($intervals as $k => $label) {
        $sel = selected($cur_int, $k, false);
        echo '<option value="' . esc_attr($k) . '" ' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>' . esc_html__('Sources','hovpnm') . '</th><td>'
        . '<label><input type="checkbox" name="hovpnm_sched_sources[]" value="server" ' . (in_array('server',$cur_src,true)?'checked':'') . '> ' . esc_html__('Server','hovpnm') . '</label> '
        . '<label><input type="checkbox" name="hovpnm_sched_sources[]" value="checkhost" ' . (in_array('checkhost',$cur_src,true)?'checked':'') . '> ' . esc_html__('Check-Host','hovpnm') . '</label>'
        . '</td></tr>';
    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Scheduler','hovpnm') . '</button></p>';
    echo '</form></div>';
}

// Render Add Server page
function render_add_server() {
    if (!current_user_can('manage_options')) return;
    include __DIR__ . '/templates/add-server.php';
}


// Handle manual add form submission
add_action('admin_post_hovpnm_add_server', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    check_admin_referer('hovpnm_add_server');
    $allowed_types = ['standard','premium','free'];
    $data = [
        'file_name'   => sanitize_file_name($_POST['file_name'] ?? ''),
        'remote_host' => sanitize_text_field($_POST['remote_host'] ?? ''),
        'port'        => intval($_POST['port'] ?? 0) ?: null,
        'protocol'    => in_array(strtolower($_POST['protocol'] ?? ''), ['udp','tcp'], true) ? strtolower($_POST['protocol']) : null,
        'cipher'      => sanitize_text_field($_POST['cipher'] ?? ''),
        'type'        => in_array($_POST['type'] ?? 'standard', $allowed_types, true) ? $_POST['type'] : 'standard',
        'location'    => sanitize_text_field($_POST['location'] ?? ''),
        'notes'       => wp_kses_post($_POST['notes'] ?? ''),
        'status'      => 'unknown',
    ];
    if (empty($data['file_name']) || empty($data['remote_host'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Please provide at least Name and Remote Host.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    // Ensure unique file_name (case-insensitive)
    if ($data['file_name'] !== '') {
        global $wpdb; $t = \HOVPNM\Core\DB::table_name();
        $base = $data['file_name'];
        $candidate = $base; $i = 1;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE LOWER(file_name)=LOWER(%s)", $candidate));
            if (!$exists) break; $i++; $candidate = preg_match('/-\d+$/', $base) ? preg_replace('/-\d+$/', '-' . $i, $base) : ($base . '-' . $i);
        }
        $data['file_name'] = $candidate;
    }
    $new_id = \HOVPNM\Core\Servers::insert($data);
    // Auto-detect location if not provided
    if (empty($data['location'])) {
        $loc = detect_location_for_host($data['remote_host']);
        if ($loc !== '') { \HOVPNM\Core\Servers::update($new_id, ['location' => $loc]); }
    }
    do_action('vpnpm_server_added', $new_id, $data);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server added.','hovpnm')), admin_url('admin.php?page=hovpnm')));
    exit;
});

// Handle OVPN import
add_action('admin_post_hovpnm_import_ovpn', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    check_admin_referer('hovpnm_import_ovpn');
    if (empty($_FILES['ovpn_file']['name'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('No file selected.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = ['test_form' => false];
    $file = wp_handle_upload($_FILES['ovpn_file'], $overrides);
    if (isset($file['error'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(sprintf(__('Upload error: %s','hovpnm'), $file['error'])), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    $path = $file['file'];
    $content = @file_get_contents($path);
    if ($content === false) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Unable to read uploaded file.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    // Naive parsing for remote, port, proto, cipher
    $remote = '';
    $port = null;
    $proto = null;
    $cipher = '';
    if (preg_match('/^\s*remote\s+([^\s]+)(?:\s+(\d+))?/mi', $content, $m)) {
        $remote = $m[1];
        if (!empty($m[2])) $port = intval($m[2]);
    }
    if (preg_match('/^\s*proto\s+(udp|tcp)/mi', $content, $m)) {
        $proto = strtolower($m[1]);
    }
    if (preg_match('/^\s*cipher\s+([^\s]+)/mi', $content, $m)) {
        $cipher = $m[1];
    }
    $data = [
        'file_name'   => sanitize_file_name(basename($path)),
        'remote_host' => sanitize_text_field($remote),
        'port'        => $port,
        'protocol'    => $proto,
        'cipher'      => sanitize_text_field($cipher),
    'type'        => 'standard',
    'status'      => 'unknown',
    ];
    if (empty($data['file_name']) || empty($data['remote_host'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Could not parse required fields from file.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    // Ensure unique file_name (case-insensitive)
    if ($data['file_name'] !== '') {
        global $wpdb; $t = \HOVPNM\Core\DB::table_name();
        $base = $data['file_name'];
        $candidate = $base; $i = 1;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE LOWER(file_name)=LOWER(%s)", $candidate));
            if (!$exists) break; $i++; $candidate = preg_match('/-\d+$/', $base) ? preg_replace('/-\d+$/', '-' . $i, $base) : ($base . '-' . $i);
        }
        $data['file_name'] = $candidate;
    }
    $new_id = \HOVPNM\Core\Servers::insert($data);
    // Auto-detect location if empty
    if (empty($data['location']) && !empty($data['remote_host'])) {
        $loc = detect_location_for_host($data['remote_host']);
        if ($loc !== '') { \HOVPNM\Core\Servers::update($new_id, ['location' => $loc]); }
    }
    do_action('vpnpm_server_added', $new_id, $data);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server imported from OVPN.','hovpnm')), admin_url('admin.php?page=hovpnm')));
    exit;
});

// Handle multi OVPN import
add_action('admin_post_hovpnm_import_ovpn_multi', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    check_admin_referer('hovpnm_import_ovpn_multi');
    if (empty($_FILES['ovpn_files']['name']) || !is_array($_FILES['ovpn_files']['name'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('No files selected.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    $names = $_FILES['ovpn_files']['name'];
    $tmp_names = $_FILES['ovpn_files']['tmp_name'];
    $errs = $_FILES['ovpn_files']['error'];
    $count = 0; $fail = 0;
    foreach ($names as $idx => $name) {
        $err = isset($errs[$idx]) ? (int)$errs[$idx] : UPLOAD_ERR_NO_FILE;
        $tmp = $tmp_names[$idx] ?? '';
        if ($err !== UPLOAD_ERR_OK || !$tmp || !is_uploaded_file($tmp)) { $fail++; continue; }
        $content = @file_get_contents($tmp);
        if ($content === false) { $fail++; continue; }
        $remote = ''; $port = null; $proto = null; $cipher = '';
        if (preg_match('/^\s*remote\s+([^\s]+)(?:\s+(\d+))?/mi', $content, $m)) {
            $remote = $m[1]; if (!empty($m[2])) $port = intval($m[2]);
        }
        if (preg_match('/^\s*proto\s+(udp|tcp)/mi', $content, $m)) { $proto = strtolower($m[1]); }
        if (preg_match('/^\s*cipher\s+([^\s]+)/mi', $content, $m)) { $cipher = $m[1]; }
        $data = [
            'file_name' => sanitize_file_name(basename($name)),
            'remote_host' => sanitize_text_field($remote),
            'port' => $port,
            'protocol' => $proto,
            'cipher' => sanitize_text_field($cipher),
            'type' => 'standard',
            'status' => 'unknown',
        ];
        if (empty($data['file_name']) || empty($data['remote_host'])) { $fail++; continue; }
        // Ensure unique name
        global $wpdb; $t = \HOVPNM\Core\DB::table_name();
        $base = $data['file_name']; $candidate = $base; $i = 1;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE LOWER(file_name)=LOWER(%s)", $candidate));
            if (!$exists) break; $i++; $candidate = preg_match('/-\d+$/', $base) ? preg_replace('/-\d+$/', '-' . $i, $base) : ($base . '-' . $i);
        }
        $data['file_name'] = $candidate;
        $new_id = \HOVPNM\Core\Servers::insert($data);
        if (empty($data['location']) && !empty($data['remote_host'])) {
            $loc = detect_location_for_host($data['remote_host']);
            if ($loc !== '') { \HOVPNM\Core\Servers::update($new_id, ['location' => $loc]); }
        }
        do_action('vpnpm_server_added', $new_id, $data);
        $count++;
    }
    $msg = sprintf(\__('Imported %d files. %d failed.','hovpnm'), $count, $fail);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode($msg), admin_url('admin.php?page=hovpnm')));
    exit;
});

// Handle CSV import
add_action('admin_post_hovpnm_import_csv', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    check_admin_referer('hovpnm_import_csv');
    if (empty($_FILES['csv_file']['name'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('No file selected.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = ['test_form' => false];
    $file = wp_handle_upload($_FILES['csv_file'], $overrides);
    if (isset($file['error'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(sprintf(__('Upload error: %s','hovpnm'), $file['error'])), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    $path = $file['file'];
    $fh = @fopen($path, 'r');
    if (!$fh) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Unable to read uploaded file.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    $count = 0; $fail = 0;
    $headers = null;
    // Normalize line endings and handle UTF-8 BOM
    stream_filter_append($fh, 'convert.iconv.UTF-8/UTF-8');
    while (($row = fgetcsv($fh)) !== false) {
        // Skip empty lines
        if (count($row) === 1 && trim((string)$row[0]) === '') { continue; }
        if ($headers === null) {
            $headers = array_map(function($h){
                $h = trim((string)$h);
                // remove BOM
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
                return $h;
            }, $row);
            continue;
        }
        // Pad or trim row to header length
        if (count($row) < count($headers)) {
            $row = array_pad($row, count($headers), '');
        } elseif (count($row) > count($headers)) {
            $row = array_slice($row, 0, count($headers));
        }
        $data = @array_combine($headers, array_map('trim', $row));
        if ($data === false) { $fail++; continue; }
        $allowed_types = ['standard','premium','free'];
        $payload = [
            'file_name'   => sanitize_file_name($data['file_name'] ?? ''),
            'remote_host' => sanitize_text_field($data['remote_host'] ?? ''),
            'port'        => isset($data['port']) && $data['port'] !== '' ? intval($data['port']) : null,
            'protocol'    => in_array(strtolower($data['protocol'] ?? ''), ['udp','tcp'], true) ? strtolower($data['protocol']) : null,
            'cipher'      => sanitize_text_field($data['cipher'] ?? ''),
            'type'        => in_array($data['type'] ?? 'standard', $allowed_types, true) ? $data['type'] : 'standard',
            'location'    => sanitize_text_field($data['location'] ?? ''),
            'notes'       => wp_kses_post($data['notes'] ?? ''),
            'status'      => 'unknown',
        ];
        if (empty($payload['file_name']) || empty($payload['remote_host'])) { $fail++; continue; }
        // Ensure unique file_name
        global $wpdb; $t = \HOVPNM\Core\DB::table_name();
        $base = $payload['file_name']; $candidate = $base; $i = 1;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE LOWER(file_name)=LOWER(%s)", $candidate));
            if (!$exists) break; $i++; $candidate = preg_match('/-\d+$/', $base) ? preg_replace('/-\d+$/', '-' . $i, $base) : ($base . '-' . $i);
        }
        $payload['file_name'] = $candidate;
        $new_id = \HOVPNM\Core\Servers::insert($payload);
        if (empty($payload['location']) && !empty($payload['remote_host'])) {
            $loc = detect_location_for_host($payload['remote_host']);
            if ($loc !== '') { \HOVPNM\Core\Servers::update($new_id, ['location' => $loc]); }
        }
        do_action('vpnpm_server_added', $new_id, $payload);
        $count++;
    }
    fclose($fh);
    $msg = sprintf(\__('Imported %d servers from CSV. %d failed.','hovpnm'), $count, $fail);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode($msg), admin_url('admin.php?page=hovpnm')));
    exit;
});

// Handle update server
add_action('admin_post_hovpnm_update_server', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    check_admin_referer('hovpnm_update_server_' . $id);
    if (!$id) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Invalid server ID.','hovpnm')), admin_url('admin.php?page=hovpnm')));
        exit;
    }
    $allowed_types = ['standard','premium','free'];
    $data = [
        'file_name'   => sanitize_file_name($_POST['file_name'] ?? ''),
        'remote_host' => sanitize_text_field($_POST['remote_host'] ?? ''),
        'port'        => isset($_POST['port']) && $_POST['port'] !== '' ? intval($_POST['port']) : null,
        'protocol'    => in_array(strtolower($_POST['protocol'] ?? ''), ['udp','tcp'], true) ? strtolower($_POST['protocol']) : null,
        'cipher'      => sanitize_text_field($_POST['cipher'] ?? ''),
        'type'        => in_array($_POST['type'] ?? 'standard', $allowed_types, true) ? $_POST['type'] : 'standard',
        'location'    => sanitize_text_field($_POST['location'] ?? ''),
        'notes'       => wp_kses_post($_POST['notes'] ?? ''),
    ];
    if (empty($data['file_name']) || empty($data['remote_host'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Please provide at least Name and Remote Host.','hovpnm')), admin_url('admin.php?page=hovpnm-edit-server&id=' . $id)));
        exit;
    }
    \HOVPNM\Core\Servers::update($id, $data);
    do_action('vpnpm_server_updated', $id, $data);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server updated.','hovpnm')), admin_url('admin.php?page=hovpnm')));
    exit;
});

// Handle delete server
add_action('admin_post_hovpnm_delete_server', function(){
    if (!current_user_can('manage_options')) wp_die(__('Forbidden','hovpnm'));
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    check_admin_referer('hovpnm_delete_server_' . $id);
    if (!$id) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Invalid server ID.','hovpnm')), admin_url('admin.php?page=hovpnm')));
        exit;
    }
    global $wpdb; $t = \HOVPNM\Core\DB::table_name();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
    if ($row) {
        $row['deleted_at'] = current_time('mysql');
        $del_table = $wpdb->prefix . 'vpn_profiles_deleted';
        // Ensure id is unique; if exists, bump id copy
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$del_table} WHERE id=%d", $id));
        if ($exists) { $row['id'] = null; unset($row['id']); }
        $wpdb->insert($del_table, $row);
    }
    \HOVPNM\Core\Servers::delete($id);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server deleted.','hovpnm')), admin_url('admin.php?page=hovpnm')));
    exit;
});

function render_deleted() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $del_table = $wpdb->prefix . 'vpn_profiles_deleted';
    // Ensure table exists
    \HOVPNM\Core\DB::migrate();
    $rows = [];
    $sql = "SELECT * FROM {$del_table} ORDER BY deleted_at DESC";
    $rows = $wpdb->get_results($sql);
    echo '<div class="wrap"><h1>' . esc_html__('Deleted Servers','hovpnm') . '</h1>';
    echo '<p>' . esc_html__('Archived for 30 days before permanent removal.','hovpnm') . '</p>';
    echo '<table class="widefat striped"><thead><tr>'
        . '<th>' . esc_html__('ID','hovpnm') . '</th>'
        . '<th>' . esc_html__('Name','hovpnm') . '</th>'
        . '<th>' . esc_html__('Remote Host','hovpnm') . '</th>'
        . '<th>' . esc_html__('Type','hovpnm') . '</th>'
        . '<th>' . esc_html__('Status','hovpnm') . '</th>'
        . '<th>' . esc_html__('Deleted At','hovpnm') . '</th>'
        . '</tr></thead><tbody>';
    if ($rows) {
        foreach ($rows as $r) {
            echo '<tr>'
                . '<td>' . esc_html($r->id) . '</td>'
                . '<td>' . esc_html($r->file_name) . '</td>'
                . '<td>' . esc_html($r->remote_host) . '</td>'
                . '<td>' . esc_html($r->type) . '</td>'
                . '<td>' . esc_html($r->status) . '</td>'
                . '<td>' . esc_html($r->deleted_at) . '</td>'
                . '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">' . esc_html__('No deleted servers.','hovpnm') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
