<?php
defined('ABSPATH') || exit;

function vpnpm_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $profiles = vpnpm_get_all_profiles();
    $msg = isset($_GET['vpnpm_msg']) ? sanitize_text_field($_GET['vpnpm_msg']) : '';
    ?>
    <div class="wrap">
        <?php if ($msg): ?>
            <div class="notice notice-<?php echo ($msg === 'added') ? 'success' : 'error'; ?> is-dismissible">
                <p>
                    <?php
                    switch ($msg) {
                        case 'added': echo esc_html__('Server added successfully.', 'vpnpm'); break;
                        case 'upload_error': echo esc_html__('Upload error. Please try again.', 'vpnpm'); break;
                        case 'invalid_type': echo esc_html__('Invalid file type. Please upload a .ovpn file.', 'vpnpm'); break;
                        case 'parse_error': echo esc_html__('Failed to parse .ovpn file.', 'vpnpm'); break;
                        case 'db_error': echo esc_html__('Database error while saving.', 'vpnpm'); break;
                        case 'store_error': echo esc_html__('Could not store config file.', 'vpnpm'); break;
                        default: echo esc_html__('An error occurred.', 'vpnpm'); break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="vpn-header">
            <h1>VPN Manager</h1>
            <div>
                <input type="text" class="vpn-search" id="vpnpm-search" placeholder="Search servers...">
                <button class="vpn-btn vpn-btn-primary" id="vpnpm-add-server-btn">+ Add Server</button>
            </div>
        </div>

        <div class="vpn-container" id="vpnpm-grid">
            <?php if (!empty($profiles)) : ?>
                <?php foreach ($profiles as $server): 
                    $name = esc_html(pathinfo($server->file_name, PATHINFO_FILENAME));
                    $host = esc_html($server->remote_host);
                    $port = (int)($server->port ?: 1194);
                    $proto = $server->protocol ? esc_html(strtoupper($server->protocol)) : 'N/A';
                    $status_raw = strtolower($server->status ?: 'unknown');
                    $status_text = ucfirst($status_raw);
                    $status_class = $status_raw === 'active' ? 'status-active' : ($status_raw === 'down' ? 'status-offline' : 'status-unknown');
                    $download_url = wp_nonce_url(
                        admin_url('admin-ajax.php?action=vpnpm_download_config&id=' . (int)$server->id),
                        'vpnpm-nonce'
                    );
                    $search_haystack = strtolower($name . ' ' . $host . ' ' . $port . ' ' . $proto . ' ' . $status_text . ' ' . ($server->notes ?: ''));
                ?>
                <div class="vpn-card" data-search="<?php echo esc_attr($search_haystack); ?>">
                    <h3><?php echo $name; ?></h3>
                    <p>Host: <?php echo $host; ?></p>
                    <p>Port: <?php echo $port; ?></p>
                    <p>Protocol: <?php echo $proto; ?></p>
                    <p>Status:
                        <span class="vpnpm-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                        <?php if (!empty($server->last_checked)): ?>
                            <small class="vpnpm-last-checked"><?php echo esc_html(sprintf('Last checked: %s', $server->last_checked)); ?></small>
                        <?php endif; ?>
                    </p>
                    <div>
                        <button class="vpn-btn vpn-btn-secondary vpnpm-test-btn" data-id="<?php echo (int)$server->id; ?>">Test</button>
                        <a class="vpn-btn vpn-btn-primary" href="<?php echo esc_url($download_url); ?>">Download Config</a>
                        <button class="vpn-btn vpn-btn-danger vpnpm-delete-btn" data-id="<?php echo (int)$server->id; ?>">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No VPN profiles found. Add one to get started.</p>
            <?php endif; ?>
        </div>

        <!-- Add Server Modal -->
        <div class="vpnpm-modal" id="vpnpm-modal" aria-hidden="true">
            <div class="vpnpm-modal-backdrop" id="vpnpm-modal-close"></div>
            <div class="vpnpm-modal-content" role="dialog" aria-modal="true" aria-labelledby="vpnpm-modal-title">
                <div class="vpnpm-modal-header">
                    <h2 id="vpnpm-modal-title">Add Server</h2>
                    <button type="button" class="vpnpm-modal-close" id="vpnpm-modal-close-btn">&times;</button>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="vpnpm-upload-form">
                    <input type="hidden" name="action" value="vpnpm_add_server" />
                    <?php wp_nonce_field('vpnpm-upload'); ?>
                    <div class="vpnpm-form-row">
                        <label for="ovpn_file">OpenVPN Profile (.ovpn)</label>
                        <input type="file" id="ovpn_file" name="ovpn_file" accept=".ovpn" required />
                    </div>
                    <div class="vpnpm-form-row">
                        <label for="notes"><?php esc_html_e('Notes (optional)', 'vpnpm'); ?></label>
                        <textarea id="notes" name="notes" rows="3" placeholder="<?php esc_attr_e('Add optional notes...', 'vpnpm'); ?>"></textarea>
                    </div>
                    <div class="vpnpm-modal-actions">
                        <button type="button" class="button vpnpm-btn-secondary" id="vpnpm-cancel"><?php esc_html_e('Cancel', 'vpnpm'); ?></button>
                        <button type="submit" class="button button-primary vpnpm-btn-primary"><?php esc_html_e('Upload', 'vpnpm'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
} // end vpnpm_admin_page
