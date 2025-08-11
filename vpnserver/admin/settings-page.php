<?php
defined('ABSPATH') || exit;

// Register settings page under Settings menu
add_action('admin_menu', function() {
    add_options_page(
        __('VPN Server Manager Settings', 'vpnserver'),
        __('VPN Server Manager', 'vpnserver'),
        'manage_options',
        'vpnsm-settings',
        'vpnsm_settings_page'
    );
});

// Render settings page
function vpnsm_settings_page() {
    if (!current_user_can('manage_options')) return;
    $nodes = function_exists('vpnsm_get_checkhost_nodes') ? vpnsm_get_checkhost_nodes() : [];
    $selected = get_option('vpnsm_checkhost_nodes', []);
    if (!is_array($selected)) $selected = [];
    $nonce = wp_create_nonce('vpnsm_refresh_nodes');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('VPN Server Manager Settings', 'vpnserver'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('vpnsm_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Check-Host Nodes', 'vpnserver'); ?></th>
                    <td>
                        <div id="vpnsm-nodes-list">
                            <?php if (!empty($nodes)) : foreach ($nodes as $host => $label): ?>
                                <label style="display:block;margin-bottom:2px;">
                                    <input type="checkbox" name="vpnsm_checkhost_nodes[]" value="<?php echo esc_attr($host); ?>" <?php checked(in_array($host, $selected, true)); ?> />
                                    <?php echo esc_html($label); ?> <code><?php echo esc_html($host); ?></code>
                                </label>
                            <?php endforeach; else: ?>
                                <em><?php esc_html_e('No nodes loaded yet. Click “Load Nodes”.', 'vpnserver'); ?></em>
                            <?php endif; ?>
                        </div>
                        <p>
                            <button type="button" class="button" id="vpnsm-load-nodes-btn" data-nonce="<?php echo esc_attr($nonce); ?>"><?php esc_html_e('Load Nodes', 'vpnserver'); ?></button>
                        </p>
                        <p class="description"><?php esc_html_e('Select from the official Check-Host nodes list. Custom/manual node hostnames are not allowed.', 'vpnserver'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
