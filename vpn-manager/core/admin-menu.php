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
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=hovpnm-add-server')) . '" class="button button-primary">' . esc_html__('Add Server','hovpnm') . '</a></p>';
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
        'ping'        => null,
        'last_checked'=> null,
    ];
    if (empty($data['file_name']) || empty($data['remote_host'])) {
        wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Please provide at least Name and Remote Host.','hovpnm')), admin_url('admin.php?page=hovpnm-add-server')));
        exit;
    }
    \HOVPNM\Core\Servers::insert($data);
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
    $allowed_types = ['standard','premium','free'];
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
    \HOVPNM\Core\Servers::insert($data);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server imported from OVPN.','hovpnm')), admin_url('admin.php?page=hovpnm')));
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
    \HOVPNM\Core\Servers::delete($id);
    wp_redirect(add_query_arg('hovpnm_notice', rawurlencode(__('Server deleted.','hovpnm')), admin_url('admin.php?page=hovpnm')));
    exit;
});
