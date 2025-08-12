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
		<?php if (function_exists('vpnpm_checkhost_is_blocked') && vpnpm_checkhost_is_blocked()): ?>
			<div class="notice notice-warning" style="margin:15px 0;">
				<p>
					<?php echo esc_html__('Check-Host is temporarily disabled due to a recent 403 (cooldown active).', 'vpnserver'); ?>
					<button type="button" class="button button-secondary" id="vpnpm-clear-ch-cooldown" style="margin-left:10px;">
						<?php echo esc_html__('Clear Cooldown', 'vpnserver'); ?>
					</button>
				</p>
			</div>
			<script>
			(function($){
				$('#vpnpm-clear-ch-cooldown').on('click', function(){
					var $btn = $(this);
					$btn.prop('disabled', true).text('Clearing...');
					$.post(vpnpmAjax.ajaxurl, { action: 'vpnpm_clear_checkhost_cooldown', _ajax_nonce: vpnpmAjax.nonce })
					 .done(function(resp){
						alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Cooldown cleared.');
						location.reload();
					 })
					 .fail(function(){
						alert('Failed to clear cooldown.');
					 })
					 .always(function(){
						$btn.prop('disabled', false).text('<?php echo esc_js(__('Clear Cooldown', 'vpnserver')); ?>');
					 });
				});
			})(jQuery);
			</script>
		<?php endif; ?>
		<?php if ($msg): ?>
			<div class="notice notice-<?php echo ($msg === 'success') ? 'success' : 'error'; ?> is-dismissible">
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
				<?php 
// Sort profiles by ping value and status before rendering
usort($profiles, function($a, $b) {
    $statusOrder = ['active' => 1, 'unknown' => 2, 'down' => 3];

    $statusA = strtolower($a->status ?? 'unknown');
    $statusB = strtolower($b->status ?? 'unknown');

    // Sort by status first
    if ($statusOrder[$statusA] !== $statusOrder[$statusB]) {
        return $statusOrder[$statusA] - $statusOrder[$statusB];
    }

    // Then sort by ping value (lowest to highest)
    $pingA = $a->ping !== null ? (int)$a->ping : PHP_INT_MAX;
    $pingB = $b->ping !== null ? (int)$b->ping : PHP_INT_MAX;

    return $pingA - $pingB;
});
				foreach ($profiles as $server): 
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
$server_notes_safe = property_exists($server, 'notes') && $server->notes !== null ? $server->notes : '';
$server_location_safe = property_exists($server, 'location') && $server->location !== null ? $server->location : '';
$search_haystack = strtolower($name . ' ' . $host . ' ' . $port . ' ' . $proto . ' ' . $status_text . ' ' . $server_notes_safe . ' ' . $server_location_safe);
$notes = property_exists($server, 'notes') && $server->notes !== null ? esc_html($server->notes) : esc_html__('No notes available', 'vpnserver');

$last_checked = $server->last_checked ? strtotime($server->last_checked) : null;
if ($last_checked) {
    $last_checked_human = human_time_diff($last_checked, current_time('timestamp')) . ' ago';
} else {
    $last_checked_human = esc_html__('N/A', 'vpnserver');
}

$type = strtolower($server->type ?? 'standard'); // Default to 'standard'
$location = property_exists($server, 'location') && $server->location !== null ? esc_html($server->location) : esc_html__('Unknown', 'vpnserver');
$type_class = $type === 'premium' ? 'type-premium' : 'type-standard';
				?>
				<div class="vpn-card vpnpm-card" data-search="<?php echo esc_attr($search_haystack); ?>">
					<h3><?php echo $name; ?></h3>
					<p>Host: <?php echo $host; ?></p>
					<p>Port: <?php echo $port; ?></p>
					<p>Protocol: <?php echo $proto; ?></p>
					<p>Status:
						<span class="vpnpm-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
					</p>
					<p>Type: <span class="vpnpm-type <?php echo esc_attr($type_class); ?>"><?php echo ucfirst($type); ?></span></p>
					<p>Location: <span class="vpnpm-location"><?php echo $location; ?></span></p>
					<p>Last checked: <span class="vpnpm-last-checked" title="<?php echo esc_attr($server->last_checked); ?>">
						<?php echo esc_html($last_checked_human); ?>
					</span></p>
					<p>Ping (Server): <span class="vpnpm-ping-server"><?php echo ($server->ping !== null ? esc_html($server->ping) . ' ms' : esc_html__('N/A', 'vpnserver')); ?></span></p>
					<?php if (property_exists($server, 'checkhost_ping_avg')): ?>
					<?php $chErr = property_exists($server, 'checkhost_last_error') ? (string)$server->checkhost_last_error : ''; ?>
					<p>Ping (Check-Host):
						<span class="vpnpm-ping-ch"<?php echo $chErr ? ' title="' . esc_attr($chErr) . '"' : ''; ?>>
							<?php
							if ($server->checkhost_ping_avg !== null) {
								echo esc_html((int)$server->checkhost_ping_avg) . ' ms';
							} elseif (!empty($server->checkhost_last_error)) {
								echo esc_html__('Error: ', 'vpnserver') . esc_html($server->checkhost_last_error);
							} elseif (empty($server->checkhost_ping_json) || !is_array(json_decode($server->checkhost_ping_json, true))) {
								echo esc_html__('No valid data', 'vpnserver');
							} else {
								echo esc_html__('No data', 'vpnserver');
							}
							?>
						</span><?php if ($chErr): ?> <small class="ch-error-flag" style="color:#d63638;">(<?php echo esc_html__('error', 'vpnserver'); ?>)</small><?php endif; ?></p>
					<?php endif; ?>
					<p>Notes: <?php echo $notes; ?></p>
					<div>
						<button class="vpn-btn vpn-btn-secondary vpnpm-test-btn" data-id="<?php echo (int)$server->id; ?>">Test</button>
						<a class="vpn-btn vpn-btn-primary" href="<?php echo esc_url($download_url); ?>">Download Config</a>
						<button class="vpn-btn vpnpm-edit-btn" data-id="<?php echo (int)$server->id; ?>">Edit</button>
						<button class="vpn-btn vpn-btn-danger vpnpm-delete-btn" data-id="<?php echo (int)$server->id; ?>">Delete</button>
						<button class="vpn-btn" data-role="vpnpm-more-ping" data-id="<?php echo (int)$server->id; ?>">More Ping</button>
						<button class="vpn-btn" data-role="vpnpm-toggle-history" data-id="<?php echo (int)$server->id; ?>">Ping History</button>
					</div>
					<div class="vpnpm-history" id="vpnpm-history-<?php echo (int)$server->id; ?>" style="display:none; margin-top:8px;">
						<div class="vpnpm-history-body"></div>
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
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-type">Type</label>
							<select id="vpnpm-edit-type" name="type">
								<option value="standard">Standard</option>
								<option value="premium">Premium</option>
							</select>
						</div>
						<div class="vpnpm-form-row">
							<label for="vpnpm-edit-location">Location</label>
							<input type="text" id="vpnpm-edit-location" name="location" />
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

	<!-- More Ping Modal -->
	<div class="vpnpm-modal" id="vpnpm-moreping-modal" aria-hidden="true" hidden>
		<div class="vpnpm-modal-backdrop" data-close="moreping"></div>
		<div class="vpnpm-modal-content" role="dialog" aria-modal="true" aria-labelledby="vpnpm-moreping-title">
			<div class="vpnpm-modal-header">
				<h2 id="vpnpm-moreping-title">Check-Host Details</h2>
				<button type="button" class="vpnpm-modal-close" data-close="moreping">&times;</button>
			</div>
			<div class="vpnpm-modal-body">
				<div id="vpnpm-moreping-loading" style="padding:8px;">Loading...</div>
				<div id="vpnpm-moreping-error" class="notice notice-error" style="display:none"></div>
				<div id="vpnpm-moreping-content" style="display:none">
					<p><strong id="vpnpm-moreping-server"></strong></p>
					<p>Last update: <span id="vpnpm-moreping-updated"></span></p>
					<div id="vpnpm-moreping-nodes"></div>
				</div>
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
                editModal.setAttribute('inert', ''); // Use inert to prevent interaction
                editModal.hidden = true;
            });
        });

        // Ensure modal is focusable when opened
        document.getElementById('vpnpm-edit-form').addEventListener('submit', function () {
            editModal.removeAttribute('inert');
        });
    });
</script>
	<?php
}
endif;

// End of admin page
