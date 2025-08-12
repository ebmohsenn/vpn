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
  <div id="hovpnm-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:100000;">
    <div style="background:#fff; width:640px; max-width:95%; margin:5% auto; padding:16px; border-radius:6px;">
      <h2 style="margin-top:0;"><?php esc_html_e('Edit Server','hovpnm'); ?></h2>
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
            <td><input type="text" class="regular-text" name="type"></td>
          </tr>
          <tr>
            <th><label><?php esc_html_e('Label','hovpnm'); ?></label></th>
            <td><input type="text" class="regular-text" name="label"></td>
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
  </div>
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
          <a href="#" class="button button-secondary hovpnm-edit-btn" data-id="<?php echo (int)$s->id; ?>" data-name="<?php echo esc_attr($s->file_name); ?>" data-remote="<?php echo esc_attr($s->remote_host); ?>" data-port="<?php echo esc_attr($s->port); ?>" data-protocol="<?php echo esc_attr($s->protocol); ?>" data-cipher="<?php echo esc_attr($s->cipher); ?>" data-type="<?php echo esc_attr($s->type); ?>" data-label="<?php echo esc_attr($s->label); ?>" data-location="<?php echo esc_attr($s->location); ?>" data-notes="<?php echo esc_attr((string)($s->notes ?? '')); ?>"><?php esc_html_e('Edit','hovpnm'); ?></a>
          <?php foreach ($actions as $act): ?>
            <?php if (is_callable($act['cb'])): ?>
              <a href="#" class="button" data-id="<?php echo (int)$s->id; ?>" data-action="<?php echo esc_attr(sanitize_title($act['label'])); ?>"><?php echo esc_html($act['label']); ?></a>
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
<script>
(function($){
  function closeModal(){ $('#hovpnm-edit-modal').hide(); }
  function openModal(){ $('#hovpnm-edit-modal').show(); }
  $(document).on('click', '.hovpnm-edit-btn', function(e){
    e.preventDefault(); var b=$(this), m=$('#hovpnm-edit-modal');
    m.find('[name=id]').val(b.data('id'));
    m.find('[name=file_name]').val(b.data('name'));
    m.find('[name=remote_host]').val(b.data('remote'));
    m.find('[name=port]').val(b.data('port'));
    m.find('[name=protocol]').val(b.data('protocol'));
    m.find('[name=cipher]').val(b.data('cipher'));
    m.find('[name=type]').val(b.data('type'));
    m.find('[name=label]').val(b.data('label'));
    m.find('[name=location]').val(b.data('location'));
    m.find('[name=notes]').val(b.data('notes'));
    openModal();
  });
  $(document).on('click', '.hovpnm-modal-close', function(e){ e.preventDefault(); closeModal(); });
  $('#hovpnm-edit-form').on('submit', function(e){
    e.preventDefault(); var f=$(this), id=f.find('[name=id]').val();
    var payload={};
    ['file_name','remote_host','port','protocol','cipher','type','label','location','notes'].forEach(function(k){ payload[k]=f.find('[name='+k+']').val(); });
    if(payload.port==='') delete payload.port;
    var url='<?php echo esc_js( rest_url('hovpnm/v1/servers/') ); ?>'+id;
    $.ajax({
      url: url,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>'); }
    }).done(function(res){
      if(!res || !res.updated){ alert('<?php echo esc_js(__('No changes or update failed.','hovpnm')); ?>'); return; }
      // Update the row inline
      var s=res.server, row=$('a.hovpnm-edit-btn[data-id='+id+']').closest('tr');
      row.find('td').eq(0).text((s.file_name||'').replace(/\.[^/.]+$/, ''));
      // For dynamic columns, simplest is to reload page; for now minimal inline refresh of location/ping/status if present
      // If needed, we can trigger a small fetch to re-render columns. For now, just close modal.
      closeModal();
      // Soft refresh: reload after short delay to re-render columns accurately
      setTimeout(function(){ location.reload(); }, 300);
    }).fail(function(){ alert('<?php echo esc_js(__('Update failed.','hovpnm')); ?>'); });
  });
})(jQuery);
</script>
