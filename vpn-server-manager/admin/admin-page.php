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
    <div class="wrap">
        <div class="vpn-header">
            <h1><?php esc_html_e('VPN Manager', 'vpnpm'); ?></h1>
            <div>
                <input type="text" class="vpn-search" id="vpnpm-search" placeholder="<?php esc_attr_e('Search servers...', 'vpnpm'); ?>">
                <button class="vpn-btn vpn-btn-primary" id="vpnpm-add-server-btn">+ <?php esc_html_e('Add Server', 'vpnpm'); ?></button>
            </div>
        </div>

        <div class="vpn-container" id="vpnpm-grid">
            <?php if (!empty($profiles)) : ?>
                <?php foreach ($profiles as $server): 
                    $name = esc_html($server->file_name);
                    $host = esc_html($server->remote_host);
                    $port = (int)($server->port ?: 1194);
                    $proto = $server->protocol ? esc_html($server->protocol) : 'N/A';
                    $status_raw = strtolower($server->status ?: 'unknown');
                    $status_text = ucfirst($status_raw);
                    $status_class = $status_raw === 'active' ? 'status-active' : ($status_raw === 'down' ? 'status-offline' : 'status-unknown');
                    $download_url = wp_nonce_url(
                        admin_url('admin-ajax.php?action=vpnpm_download_config&id=' . (int)$server->id),
                        'vpnpm-nonce'
                    );
                ?>
                <div class="vpn-card" data-search="<?php echo esc_attr(strtolower($name . ' ' . $host . ' ' . $port . ' ' . $proto . ' ' . $status_text)); ?>">
                    <h3><?php echo $name; ?></h3>
                    <p><?php esc_html_e('Host', 'vpnpm'); ?>: <?php echo $host; ?></p>
                    <p><?php esc_html_e('Port', 'vpnpm'); ?>: <?php echo $port; ?></p>
                    <p><?php esc_html_e('Protocol', 'vpnpm'); ?>: <?php echo $proto; ?></p>
                    <p><?php esc_html_e('Status', 'vpnpm'); ?>:
                        <span class="<?php echo esc_attr($status_class); ?> vpnpm-status"><?php echo esc_html($status_text); ?></span>
                        <?php if (!empty($server->last_checked)): ?>
                            <small class="vpnpm-last-checked"><?php echo esc_html(sprintf(__('Last checked: %s', 'vpnpm'), $server->last_checked)); ?></small>
                        <?php endif; ?>
                    </p>
                    <div>
                        <button class="vpn-btn vpn-btn-secondary vpnpm-test-btn" data-id="<?php echo (int)$server->id; ?>"><?php esc_html_e('Test', 'vpnpm'); ?></button>
                        <a class="vpn-btn vpn-btn-primary" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download Config', 'vpnpm'); ?></a>
                        <button class="vpn-btn vpn-btn-danger vpnpm-delete-btn" data-id="<?php echo (int)$server->id; ?>"><?php esc_html_e('Delete', 'vpnpm'); ?></button>
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
