<?php
defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '../includes/db-functions.php';
require_once plugin_dir_path(__FILE__) . '../includes/parser.php';
require_once plugin_dir_path(__FILE__) . '../includes/ping.php';

function vpnpm_render_admin_page() {
    // Handle file upload first, before any output
    if (
        isset($_FILES['vpnpm_ovpn_file']) &&
        check_admin_referer('vpnpm_upload', 'vpnpm_nonce')
    ) {
        $file = $_FILES['vpnpm_ovpn_file'];
        $content = file_get_contents($file['tmp_name']);
        $parsed = vpnpm_parse_ovpn_content($content);

        if ($parsed) {
            echo '<div class="notice notice-success"><p>File uploaded successfully. Parsed data:</p><pre>';
            var_dump($parsed);
            echo '</pre></div>';

            vpnpm_insert_profile([
                'file_name' => sanitize_file_name($file['name']),
                'remote_host' => $parsed['remote_host'],
                'port' => $parsed['port'],
                'protocol' => $parsed['proto'] ?? $parsed['protocol'], // just in case
            ]);
        } else {
            echo "<div class='notice notice-error'><p>Failed to parse .ovpn file.</p></div>";
        }
    }

    // Now handle edit, delete, ping actions
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        $id = intval($_GET['id']);
        vpnpm_render_edit_form($id);
        return; // stop rendering the main list page when editing
    }
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
        $id = intval($_GET['id']);
        vpnpm_delete_profile($id);
        echo "<div class='notice notice-success'><p>Profile deleted successfully.</p></div>";
    }
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'ping') {
        $id = intval($_GET['id']);
        $profile = vpnpm_get_profile_by_id($id);
        if ($profile) {
            $status = vpnpm_check_server_status($profile->remote_host, $profile->port);
            vpnpm_update_status($profile->id, $status);
            if ($status === 'online') {
                echo "<div class='notice notice-success'><p>Server {$profile->remote_host} is ONLINE.</p></div>";
            } else {
                echo "<div class='notice notice-error'><p>Server {$profile->remote_host} is OFFLINE or unreachable.</p></div>";
            }
        } else {
            echo "<div class='notice notice-error'><p>Profile not found.</p></div>";
        }
    }

    // Now output the rest of the page: upload form, profiles list, etc.
    ?>
    <div class="wrap">
        <h1>VPN Profile Manager</h1>

        <h2>Upload .ovpn Profile</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('vpnpm_upload', 'vpnpm_nonce'); ?>
            <input type="file" name="vpnpm_ovpn_file" accept=".ovpn" required>
            <input type="submit" class="button button-primary" value="Upload">
        </form>

        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('vpnpm_ping_all', 'vpnpm_ping_nonce'); ?>
            <input type="submit" class="button" name="vpnpm_ping_now" value="Check Server Status">
        </form>

        <hr>

        <h2>Uploaded VPN Profiles</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>File Name</th>
                    <th>Remote</th>
                    <th>Port</th>
                    <th>Protocol</th>
                    <th>Status</th>
                    <th>Last Checked</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $profiles = vpnpm_get_all_profiles();
                if ($profiles) {
                    foreach ($profiles as $profile) {
                        echo '<tr>';
                        echo "<td>{$profile->file_name}</td>";
                        echo "<td>{$profile->remote_host}</td>";
                        echo "<td>{$profile->port}</td>";
                        echo "<td>{$profile->protocol}</td>";
                        echo "<td>{$profile->status}</td>";
                        echo "<td>" . ($profile->last_checked ?: '-') . "</td>";
                        $edit_url = admin_url('admin.php?page=vpn-profiles&action=edit&id=' . intval($profile->id));
                        $delete_url = admin_url('admin.php?page=vpn-profiles&action=delete&id=' . intval($profile->id));
                        $ping_url = admin_url('admin.php?page=vpn-profiles&action=ping&id=' . intval($profile->id));

                        echo "<td>
                            <a href='" . esc_url($edit_url) . "' class='button'>Edit</a> 
                            <a href='" . esc_url($delete_url) . "' class='button' onclick='return confirm(\"Are you sure you want to delete this profile?\");'>Delete</a> 
                            <a href='" . esc_url($ping_url) . "' class='button'>Ping</a>
                        </td>";
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No profiles uploaded yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}function vpnpm_render_edit_form($id) {
    $profile = vpnpm_get_profile_by_id($id);
    if (!$profile) {
        echo '<div class="notice notice-error"><p>Profile not found.</p></div>';
        return;
    }

    // Handle form submission
    if (isset($_POST['vpnpm_edit_nonce']) && wp_verify_nonce($_POST['vpnpm_edit_nonce'], 'vpnpm_edit_action')) {
        $updated = [
            'file_name' => sanitize_text_field($_POST['file_name']),
            'remote_host' => sanitize_text_field($_POST['remote_host']),
            'port' => intval($_POST['port']),
            'protocol' => sanitize_text_field($_POST['protocol']),
            'notes' => sanitize_textarea_field($_POST['notes']),
        ];

        vpnpm_update_profile($id, $updated);

        echo '<div class="notice notice-success"><p>Profile updated successfully.</p></div>';
        // Refresh profile data
        $profile = vpnpm_get_profile_by_id($id);
    }

    ?>
    <div class="wrap">
        <h1>Edit VPN Profile</h1>
        <form method="post">
            <?php wp_nonce_field('vpnpm_edit_action', 'vpnpm_edit_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="file_name">File Name</label></th>
                    <td><input type="text" id="file_name" name="file_name" value="<?php echo esc_attr($profile->file_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="remote_host">Remote Host</label></th>
                    <td><input type="text" id="remote_host" name="remote_host" value="<?php echo esc_attr($profile->remote_host); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="port">Port</label></th>
                    <td><input type="number" id="port" name="port" value="<?php echo esc_attr($profile->port); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="protocol">Protocol</label></th>
                    <td>
                        <select id="protocol" name="protocol">
                            <option value="udp" <?php selected($profile->protocol, 'udp'); ?>>UDP</option>
                            <option value="tcp" <?php selected($profile->protocol, 'tcp'); ?>>TCP</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="notes">Notes</label></th>
                    <td><textarea id="notes" name="notes" rows="5" cols="50"><?php echo esc_textarea($profile->notes); ?></textarea></td>
                </tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Changes</button></p>
        </form>
        <p><a href="<?php echo admin_url('admin.php?page=vpn-profiles'); ?>">&laquo; Back to list</a></p>
    </div>
    <?php
}
// Ping all servers
function vpnpm_handle_ping_action() {
    if (
        isset($_POST['vpnpm_ping_now']) &&
        check_admin_referer('vpnpm_ping_all', 'vpnpm_ping_nonce')
    ) {
        $profiles = vpnpm_get_all_profiles();
        foreach ($profiles as $profile) {
            $status = vpnpm_check_server_status($profile->remote_host, $profile->port);
            vpnpm_update_status($profile->id, $status);
        }
        add_action('admin_notices', function () {
            echo "<div class='notice notice-success'><p>Ping results updated.</p></div>";
        });
    }
}
add_action('admin_init', 'vpnpm_handle_ping_action');