<?php
use HOVPNM\Core\Servers;
if (!defined('ABSPATH')) { exit; }
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$server = $id ? Servers::get($id) : null;
?>
<div class="wrap">
  <h1><?php echo esc_html__('Edit Server', 'hovpnm'); ?></h1>
  <?php if (!$server): ?>
    <div class="notice notice-error"><p><?php esc_html_e('Server not found.', 'hovpnm'); ?></p></div>
  <?php else: ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('hovpnm_update_server_' . (int)$server->id); ?>
    <input type="hidden" name="action" value="hovpnm_update_server" />
    <input type="hidden" name="id" value="<?php echo (int)$server->id; ?>" />
    <table class="form-table">
      <tr>
        <th><label for="file_name"><?php esc_html_e('Name', 'hovpnm'); ?></label></th>
        <td><input type="text" class="regular-text" id="file_name" name="file_name" value="<?php echo esc_attr($server->file_name); ?>" required /></td>
      </tr>
      <tr>
        <th><label for="remote_host"><?php esc_html_e('Remote Host', 'hovpnm'); ?></label></th>
        <td><input type="text" class="regular-text" id="remote_host" name="remote_host" value="<?php echo esc_attr($server->remote_host); ?>" required /></td>
      </tr>
      <tr>
        <th><label for="port"><?php esc_html_e('Port', 'hovpnm'); ?></label></th>
        <td><input type="number" id="port" name="port" value="<?php echo esc_attr($server->port); ?>" /></td>
      </tr>
      <tr>
        <th><label for="protocol"><?php esc_html_e('Protocol', 'hovpnm'); ?></label></th>
        <td>
          <select id="protocol" name="protocol">
            <option value="" <?php selected($server->protocol, ''); ?>>--</option>
            <option value="udp" <?php selected($server->protocol, 'udp'); ?>>UDP</option>
            <option value="tcp" <?php selected($server->protocol, 'tcp'); ?>>TCP</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="cipher"><?php esc_html_e('Cipher', 'hovpnm'); ?></label></th>
        <td><input type="text" id="cipher" name="cipher" class="regular-text" value="<?php echo esc_attr($server->cipher); ?>" /></td>
      </tr>
      <tr>
        <th><label for="type"><?php esc_html_e('Type', 'hovpnm'); ?></label></th>
        <td><input type="text" id="type" name="type" class="regular-text" value="<?php echo esc_attr($server->type); ?>" /></td>
      </tr>
      <tr>
        <th><label for="label"><?php esc_html_e('Label', 'hovpnm'); ?></label></th>
        <td><input type="text" id="label" name="label" class="regular-text" value="<?php echo esc_attr($server->label); ?>" /></td>
      </tr>
      <tr>
        <th><label for="location"><?php esc_html_e('Location', 'hovpnm'); ?></label></th>
        <td><input type="text" id="location" name="location" class="regular-text" value="<?php echo esc_attr($server->location); ?>" /></td>
      </tr>
      <tr>
        <th><label for="notes"><?php esc_html_e('Notes', 'hovpnm'); ?></label></th>
        <td><textarea id="notes" name="notes" class="large-text" rows="3"><?php echo esc_textarea($server->notes); ?></textarea></td>
      </tr>
    </table>
    <?php submit_button(__('Save Changes','hovpnm')); ?>
  </form>

  <hr/>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this server?','hovpnm')); ?>');">
    <?php wp_nonce_field('hovpnm_delete_server_' . (int)$server->id); ?>
    <input type="hidden" name="action" value="hovpnm_delete_server" />
    <input type="hidden" name="id" value="<?php echo (int)$server->id; ?>" />
    <?php submit_button(__('Delete Server','hovpnm'), 'delete'); ?>
  </form>
  <?php endif; ?>
</div>
