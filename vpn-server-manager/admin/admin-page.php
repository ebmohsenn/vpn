<?php
defined('ABSPATH') || exit;

function vpnpm_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $profiles = vpnpm_get_all_profiles();
    $download_nonce = wp_create_nonce('vpnpm-nonce');
    $msg = isset($_GET['vpnpm_msg']) ? sanitize_text_field($_GET['vpnpm_msg']) : '';
    ?>
    <div class="wrap vpnpm-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('VPN Manager', 'vpnpm'); ?></h1>

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

        <div class="vpnpm-topbar">
            <input type="text" id="vpnpm-search" class="vpnpm-search" placeholder="<?php esc_attr_e('Search servers...', 'vpnpm'); ?>" />
            <div class="vpnpm-actions">
                <button type="button" class="button vpnpm-btn-secondary" id="vpnpm-test-all"><?php esc_html_e('Test All', 'vpnpm'); ?></button>
                <button type="button" class="button vpnpm-btn-secondary"><?php esc_html_e('Logs', 'vpnpm'); ?></button>
                <button type="button" class="button vpnpm-btn-secondary"><?php esc_html_e('Settings', 'vpnpm'); ?></button>
                <button type="button" class="button vpnpm-btn-secondary"><?php esc_html_e('Customer View', 'vpnpm'); ?></button>
                <button type="button" class="button button-primary vpnpm-btn-primary" id="vpnpm-add-server-btn">+ <?php esc_html_e('Add Server', 'vpnpm'); ?></button>
            </div>
        </div>

        <div class="vpnpm-grid" id="vpnpm-grid">
            <?php if (!empty($profiles)) : ?>
                <?php foreach ($profiles as $p): 
                    $status = esc_html($p->status ?: 'unknown');
                    $status_label = ucfirst($status);
                    $status_class = vpnpm_status_class($status);
                    $proto = $p->protocol ? esc_html(strtoupper($p->protocol)) : 'N/A';
                    $cipher = $p->cipher ? esc_html($p->cipher) : 'N/A';
                    $port = $p->port ? (int)$p->port : 1194;
                    $name = esc_html(pathinfo($p->file_name, PATHINFO_FILENAME));
                    $notes = $p->notes ? esc_html($p->notes) : '';
                    $download_url = wp_nonce_url(
                        admin_url('admin-ajax.php?action=vpnpm_download_config&id=' . (int)$p->id),
                        'vpnpm-nonce'
                    );
                ?>
                <div class="vpnpm-card" data-search="<?php echo esc_attr(strtolower($name . ' ' . $p->remote_host . ' ' . $port . ' ' . ($p->cipher ?: '') . ' ' . ($p->protocol ?: '') . ' ' . $status . ' ' . $notes)); ?>">
                    <div class="vpnpm-card-header">
                        <div class="vpnpm-card-title"><?php echo $name; ?></div>
                        <span class="vpnpm-proto badge badge-blue"><?php echo $proto; ?></span>
                    </div>
                    <div class="vpnpm-card-body">
                        <div class="vpnpm-row"><span class="label"><?php esc_html_e('Host', 'vpnpm'); ?>:</span> <span><?php echo esc_html($p->remote_host); ?></span></div>
                        <div class="vpnpm-row"><span class="label"><?php esc_html_e('Port', 'vpnpm'); ?>:</span> <span><?php echo (int)$port; ?></span></div>
                        <div class="vpnpm-row"><span class="label"><?php esc_html_e('Cipher', 'vpnpm'); ?>:</span> <span><?php echo $cipher; ?></span></div>
                        <div class="vpnpm-row">
                            <span class="label"><?php esc_html_e('Status', 'vpnpm'); ?>:</span>
                            <span class="vpnpm-status <?php echo esc_attr($status_class); ?>" data-id="<?php echo (int)$p->id; ?>"><?php echo esc_html($status_label); ?></span>
                            <?php if (!empty($p->last_checked)): ?>
                                <small class="vpnpm-last-checked"><?php echo esc_html(sprintf(__('Last checked: %s', 'vpnpm'), $p->last_checked)); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($notes): ?>
                            <div class="vpnpm-notes"><?php echo nl2br($notes); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="vpnpm-card-footer">
                        <button class="button vpnpm-btn-secondary vpnpm-test-btn" data-id="<?php echo (int)$p->id; ?>"><?php esc_html_e('Test', 'vpnpm'); ?></button>
                        <a class="button vpnpm-btn-primary" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download Config', 'vpnpm'); ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php esc_html_e('No VPN profiles found. Add one to get started.', 'vpnpm'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Add Server Modal -->
        <div class="vpnpm-modal" id="vpnpm-modal" aria-hidden="true">
            <div class="vpnpm-modal-backdrop" id="vpnpm-modal-close"></div>
            <div class="vpnpm-modal-content" role="dialog" aria-modal="true" aria-labelledby="vpnpm-modal-title">
                <div class="vpnpm-modal-header">
                    <h2 id="vpnpm-modal-title"><?php esc_html_e('Add Server', 'vpnpm'); ?></h2>
                    <button type="button" class="vpnpm-modal-close" id="vpnpm-modal-close-btn">&times;</button>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="vpnpm-upload-form">
                    <input type="hidden" name="action" value="vpnpm_add_server" />
                    <?php wp_nonce_field('vpnpm-upload'); ?>
                    <div class="vpnpm-form-row">
                        <label for="ovpn_file"><?php esc_html_e('OpenVPN Profile (.ovpn)', 'vpnpm'); ?></label>
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
}
