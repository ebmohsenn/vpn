<?php
use HOVPNM\Core\Servers;
if (!defined('ABSPATH')) { exit; }
?>
<div class="wrap">
  <h1><?php echo esc_html__('Add Server', 'hovpnm'); ?></h1>
  <?php if (!empty($_GET['hovpnm_notice'])): ?>
    <div class="notice notice-info"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['hovpnm_notice']))); ?></p></div>
  <?php endif; ?>

  <h2 class="title"><?php echo esc_html__('Manual entry', 'hovpnm'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('hovpnm_add_server'); ?>
    <input type="hidden" name="action" value="hovpnm_add_server" />
    <table class="form-table">
      <tr>
        <th><label for="file_name"><?php esc_html_e('Name', 'hovpnm'); ?></label></th>
        <td><input type="text" class="regular-text" id="file_name" name="file_name" required /></td>
      </tr>
      <tr>
        <th><label for="remote_host"><?php esc_html_e('Remote Host', 'hovpnm'); ?></label></th>
        <td><input type="text" class="regular-text" id="remote_host" name="remote_host" required /></td>
      </tr>
      <tr>
        <th><label for="port"><?php esc_html_e('Port', 'hovpnm'); ?></label></th>
        <td><input type="number" id="port" name="port" /></td>
      </tr>
      <tr>
        <th><label for="protocol"><?php esc_html_e('Protocol', 'hovpnm'); ?></label></th>
        <td>
          <select id="protocol" name="protocol">
            <option value="">--</option>
            <option value="udp">UDP</option>
            <option value="tcp">TCP</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="cipher"><?php esc_html_e('Cipher', 'hovpnm'); ?></label></th>
        <td><input type="text" id="cipher" name="cipher" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="type"><?php esc_html_e('Type', 'hovpnm'); ?></label></th>
        <td>
          <select id="type" name="type">
            <option value="standard"><?php esc_html_e('Standard','hovpnm'); ?></option>
            <option value="premium"><?php esc_html_e('Premium','hovpnm'); ?></option>
            <option value="free"><?php esc_html_e('Free','hovpnm'); ?></option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="location"><?php esc_html_e('Location', 'hovpnm'); ?></label></th>
        <td><input type="text" id="location" name="location" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="notes"><?php esc_html_e('Notes', 'hovpnm'); ?></label></th>
        <td><textarea id="notes" name="notes" class="large-text" rows="3"></textarea></td>
      </tr>
    </table>
    <?php submit_button(__('Add Server','hovpnm')); ?>
  </form>

  <hr/>

  <h2 class="title"><?php echo esc_html__('Import from .ovpn file', 'hovpnm'); ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('hovpnm_import_ovpn'); ?>
    <input type="hidden" name="action" value="hovpnm_import_ovpn" />
    <p>
      <input type="file" name="ovpn_file" accept=".ovpn" />
    </p>
    <?php submit_button(__('Import','hovpnm')); ?>
  </form>
</div>
