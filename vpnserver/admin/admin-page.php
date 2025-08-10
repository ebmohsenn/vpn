<?php
defined('ABSPATH') || exit;

if (!function_exists('vpnpm_admin_page')):
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
						case 'added': echo esc_html__('Server added successfully.', 'vpnserver'); break;
						case 'upload_error': echo esc_html__('Upload error. Please try again.', 'vpnserver'); break;
						case 'invalid_type': echo esc_html__('Invalid file type. Please upload a .ovpn file.', 'vpnserver'); break;
						case 'parse_error': echo esc_html__('Failed to parse .ovpn file.', 'vpnserver'); break;
						case 'db_error': echo esc_html__('Database error while saving.', 'vpnserver'); break;
						case 'store_error': echo esc_html__('Could not store config file.', 'vpnserver'); break;
						default: echo esc_html__('An error occurred.', 'vpnserver'); break;
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="vpn-header">
			<h1>VPN Manager</h1>
			<div>
				<button class="vpn-btn vpn-btn-primary" id="vpnpm-add-server-btn">+ Add Server</button>
				<input type="text" class="vpn-search" id="vpnpm-search" placeholder="Search servers..." aria-label="Search servers" />
			</div>
		</div>

		<div class="vpn-container">
			<div class="vpn-grid" id="vpnpm-grid">
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
				<div class="vpn-card vpnpm-card" data-search="<?php echo esc_attr($search_haystack); ?>">
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
						<button class="vpn-btn vpnpm-edit-btn" data-id="<?php echo (int)$server->id; ?>">Edit</button>
						<button class="vpn-btn vpn-btn-danger vpnpm-delete-btn" data-id="<?php echo (int)$server->id; ?>">Delete</button>
					</div>
				</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p>No VPN profiles found. Add one to get started.</p>
			<?php endif; ?>
			</div>
		</div>

		<!-- Add Server Modal -->
		<div class="vpnpm-modal" id="vpnpm-modal" aria-hidden="true" hidden>
			<div class="vpnpm-modal-backdrop" id="vpnpm-modal-close"></div>
			<div class="vpnpm-modal-content" role="dialog" aria-modal="true" aria-labelledby="vpnpm-modal-title">
				<div class="vpnpm-modal-header">
					<h2 id="vpnpm-modal-title">Add Servers</h2>
					<button type="button" class="vpnpm-modal-close" id="vpnpm-modal-close-btn">&times;</button>
				</div>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="vpnpm-upload-form">
					<input type="hidden" name="action" value="vpnpm_add_servers" />
					<?php wp_nonce_field('vpnpm-upload'); ?>
					<div class="vpnpm-form-row">
						<label for="ovpn_files">OpenVPN Profiles (.ovpn)</label>
						<input type="file" id="ovpn_files" name="ovpn_files[]" accept=".ovpn" multiple required />
					</div>
					<div class="vpnpm-modal-actions">
						<button type="button" class="button vpnpm-btn-secondary" id="vpnpm-cancel"><?php esc_html_e('Cancel', 'vpnserver'); ?></button>
						<button type="submit" class="button button-primary vpnpm-btn-primary"><?php esc_html_e('Upload', 'vpnserver'); ?></button>
					</div>
				</form>
			</div>
		</div>

		<!-- Edit Server Modal -->
		<div class="vpnpm-modal" id="vpnpm-edit-modal" aria-hidden="true" hidden>
			<div class="vpnpm-modal-backdrop" data-close="edit"></div>
			<div class="vpnpm-modal-content" role="dialog" aria-modal="true" aria-labelledby="vpnpm-edit-title">
				<div class="vpnpm-modal-header">
					<h2 id="vpnpm-edit-title">Edit Server</h2>
					<button type="button" class="vpnpm-modal-close" data-close="edit">&times;</button>
				</div>
				<form id="vpnpm-edit-form">
					<input type="hidden" name="id" id="vpnpm-edit-id" />
					<div class="vpnpm-form-grid">
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-remote">Remote Host</label>
							<input type="text" id="vpnpm-edit-remote" name="remote_host" required />
						</div>
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-port">Port</label>
							<input type="number" id="vpnpm-edit-port" name="port" min="1" max="65535" />
						</div>
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-protocol">Protocol</label>
							<select id="vpnpm-edit-protocol" name="protocol">
								<option value="">Auto</option>
								<option value="tcp">TCP</option>
								<option value="udp">UDP</option>
							</select>
						</div>
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-cipher">Cipher</label>
							<input type="text" id="vpnpm-edit-cipher" name="cipher" />
						</div>
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-status">Status</label>
							<select id="vpnpm-edit-status" name="status">
								<option value="unknown">Unknown</option>
								<option value="active">Active</option>
								<option value="down">Down</option>
							</select>
						</div>
						<div class="vpnpm-form-row vpnpm-form-wide">
							<label for="vpnpm-edit-notes">Notes</label>
							<textarea id="vpnpm-edit-notes" name="notes" rows="4"></textarea>
						</div>
					</div>
					<div class="vpnpm-modal-actions">
						<button type="button" class="button vpnpm-btn-secondary" data-close="edit"><?php esc_html_e('Cancel', 'vpnserver'); ?></button>
						<button type="submit" class="button button-primary vpnpm-btn-primary"><?php esc_html_e('Save Changes', 'vpnserver'); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Close Edit Modal on Cancel Button Click
        const editModal = document.getElementById('vpnpm-edit-modal');
        const cancelEditButtons = document.querySelectorAll('[data-close="edit"]');

        cancelEditButtons.forEach(button => {
            button.addEventListener('click', function () {
                editModal.setAttribute('aria-hidden', 'true');
                editModal.hidden = true;
            });
        });
    });
</script>
	<?php
}
endif;

// End of admin page
