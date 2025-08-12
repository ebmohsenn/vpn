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
});

function render_dashboard() {
    do_action('vpnpm_before_dashboard_render');
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
