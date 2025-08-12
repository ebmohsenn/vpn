<?php
use HOVPNM\Core\Servers;
use HOVPNM\Core\ColumnsRegistry;
if (!defined('ABSPATH')) { exit; }
$servers = Servers::all();
$columns = ColumnsRegistry::$cols;
$visible = get_option('hovpnm_visible_columns', array_keys($columns));
if (!is_array($visible)) { $visible = array_keys($columns); }
$actions = ColumnsRegistry::$actions;
?>
<div class="wrap">
  <h1><?php echo esc_html__('HO VPN Manager', 'hovpnm'); ?></h1>
  <div id="hovpnm-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:100000;">
    <div style="background:#fff; width:640px; max-width:95%; margin:5% auto; padding:16px; border-radius:6px; position:relative;">
      <button type="button" class="button hovpnm-modal-close" style="position:absolute; right:12px; top:12px;">&times;</button>
      <h2 style="margin-top:0; padding-right:48px;"><?php esc_html_e('Edit Server','hovpnm'); ?></h2>
      <form id="hovpnm-edit-form">
        <input type="hidden" name="id" />
        <table class="form-table"><tbody>
          <tr>
            <th><label><?php esc_html_e('Name','hovpnm'); ?></label></th>
            <td><input type="text" class="regular-text" name="file_name" required></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Remote Host','hovpnm'); ?></label></th>
            <td><input type="text" class="regular-text" name="remote_host" required></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Port','hovpnm'); ?></label></th>
            <td><input type="number" name="port"></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Protocol','hovpnm'); ?></label></th>
            <td>
              <select name="protocol">
                <option value="">--</option>
                <option value="udp">UDP</option>
                <option value="tcp">TCP</option>
              </select>
            </td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Cipher','hovpnm'); ?></label></th>
            <td><input type="text" class="regular-text" name="cipher"></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Type','hovpnm'); ?></label></th>
            <td>
              <select name="type">
                <option value="standard"><?php esc_html_e('Standard','hovpnm'); ?></option>
                <option value="premium"><?php esc_html_e('Premium','hovpnm'); ?></option>
                <option value="free"><?php esc_html_e('Free','hovpnm'); ?></option>
              </select>
            </td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Location','hovpnm'); ?></label></th>
            <td><input type="text" class="regular-text" name="location"></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Notes','hovpnm'); ?></label></th>
            <td><textarea class="large-text" rows="3" name="notes"></textarea></td>
          </tr>
        </tbody></table>
        <p>
          <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes','hovpnm'); ?></button>
          <button type="button" class="button hovpnm-modal-close"><?php esc_html_e('Cancel','hovpnm'); ?></button>
        </p>
      </form>
  </div>
  <table class="widefat fixed striped hovpnm-table">
    <thead>
      <tr>
        <th><?php esc_html_e('Name','hovpnm'); ?></th>
  <?php foreach ($columns as $id => $col): if (!in_array($id, $visible, true)) continue; ?>
  <th><?php echo esc_html($col['label']); ?></th>
        <?php endforeach; ?>
        <th><?php esc_html_e('Actions','hovpnm'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($servers): foreach ($servers as $s): ?>
      <tr>
        <td><?php echo esc_html(pathinfo($s->file_name, PATHINFO_FILENAME)); ?></td>
  <?php foreach ($columns as $id => $col): if (!in_array($id, $visible, true)) continue; ?>
  <td data-col="<?php echo esc_attr($id); ?>"><?php if (is_callable($col['cb'])) { echo wp_kses_post(call_user_func($col['cb'], $s)); } ?></td>
        <?php endforeach; ?>
        <td>
          <a href="#" class="button button-secondary hovpnm-edit-btn" data-id="<?php echo (int)$s->id; ?>" data-name="<?php echo esc_attr($s->file_name); ?>" data-remote="<?php echo esc_attr($s->remote_host); ?>" data-port="<?php echo esc_attr($s->port); ?>" data-protocol="<?php echo esc_attr($s->protocol); ?>" data-cipher="<?php echo esc_attr($s->cipher); ?>" data-type="<?php echo esc_attr($s->type); ?>" data-location="<?php echo esc_attr($s->location); ?>" data-notes="<?php echo esc_attr((string)($s->notes ?? '')); ?>"><?php esc_html_e('Edit','hovpnm'); ?></a>
          <?php foreach ($actions as $act): ?>
            <?php if (is_callable($act['cb'])): ?>
              <a href="#" class="button" data-id="<?php echo (int)$s->id; ?>" data-action="<?php echo esc_attr($act['id'] ?? sanitize_title($act['label'])); ?>" title="<?php echo esc_attr($act['title'] ?? $act['label']); ?>">
                <?php echo !empty($act['icon']) ? wp_kses_post($act['icon']) : esc_html($act['label']); ?>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="99"><?php esc_html_e('No servers found.', 'hovpnm'); ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
