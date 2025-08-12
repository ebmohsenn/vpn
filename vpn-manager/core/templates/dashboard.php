<?php
use HOVPNM\Core\Servers;
use HOVPNM\Core\ColumnsRegistry;
if (!defined('ABSPATH')) { exit; }
$servers = Servers::all();
$columns = ColumnsRegistry::$cols;
$actions = ColumnsRegistry::$actions;
?>
<div class="wrap">
  <h1><?php echo esc_html__('HO VPN Manager', 'hovpnm'); ?></h1>
  <table class="widefat fixed striped hovpnm-table">
    <thead>
      <tr>
        <th><?php esc_html_e('Name','hovpnm'); ?></th>
        <?php foreach ($columns as $id => $col): ?>
        <th><?php echo esc_html($col['label']); ?></th>
        <?php endforeach; ?>
        <th><?php esc_html_e('Actions','hovpnm'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($servers): foreach ($servers as $s): ?>
      <tr>
        <td><?php echo esc_html(pathinfo($s->file_name, PATHINFO_FILENAME)); ?></td>
        <?php foreach ($columns as $id => $col): ?>
        <td><?php if (is_callable($col['cb'])) { echo wp_kses_post(call_user_func($col['cb'], $s)); } ?></td>
        <?php endforeach; ?>
        <td>
          <a href="<?php echo esc_url(admin_url('admin.php?page=hovpnm-edit-server&id=' . (int)$s->id)); ?>" class="button button-secondary"><?php esc_html_e('Edit','hovpnm'); ?></a>
          <?php foreach ($actions as $act): ?>
            <?php if (is_callable($act['cb'])): ?>
              <a href="#" class="button" data-id="<?php echo (int)$s->id; ?>"><?php echo esc_html($act['label']); ?></a>
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
