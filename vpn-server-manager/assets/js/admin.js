(function($) {
  $(function() {
    // Search filter
    $(document).on('input', '#vpnpm-search', function() {
      const q = $(this).val().toString().toLowerCase().trim();
  $('#vpnpm-grid .vpnpm-card').each(function() {
        const hay = ($(this).data('search') || '').toString();
        $(this).toggle(hay.indexOf(q) !== -1);
      });
    });

    // Open/Close modal
    $(document).on('click', '#vpnpm-add-server-btn', function() {
      $('#vpnpm-modal').attr('aria-hidden', 'false');
    });
    $(document).on('click', '#vpnpm-modal-close, #vpnpm-modal-close-btn, #vpnpm-cancel', function() {
      $('#vpnpm-modal').attr('aria-hidden', 'true');
    });

    // Test single server
    function testServer(id, $card) {
      const $status = $card.find('.vpnpm-status');
      $status.text(vpnpmAjax.strings.testing);

      return $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_test_server', _ajax_nonce: vpnpmAjax.nonce, id: id }
      }).done(function(resp) {
        const $s = $status.removeClass('badge-green badge-red badge-gray badge-blue').addClass('badge');
        if (resp && resp.success && (resp.data.status || '').toLowerCase() === 'active') {
          $s.addClass('badge-green').text('Active');
        } else if (resp && resp.success && (resp.data.status || '').toLowerCase() === 'down') {
          $s.addClass('badge-red').text('Down');
        } else {
          $s.addClass('badge-gray').text('Unknown');
        }
        const last = resp && resp.success ? (resp.data.last_checked || '') : '';
        let $last = $card.find('.vpnpm-last-checked');
        if (!$last.length) $last = $('<small class="vpnpm-last-checked" />').appendTo($status.parent());
        if (last) $last.text('Last checked: ' + last);
      }).fail(function() {
        $status.removeClass('badge-green').addClass('badge badge-red').text('Down');
      });
    }

    $(document).on('click', '.vpnpm-test-btn', function() {
      const $card = $(this).closest('.vpnpm-card');
      const id = $(this).data('id');
      testServer(id, $card);
    });

    // Test all servers sequentially
    $(document).on('click', '#vpnpm-test-all', function() {
      const $cards = $('#vpnpm-grid .vpnpm-card:visible');
      let i = 0;
      const next = function() {
        if (i >= $cards.length) return;
        const $c = $($cards[i++]);
        const id = $c.find('.vpnpm-test-btn').data('id');
        testServer(id, $c).always(function() { setTimeout(next, 150); });
      };
      next();
    });

    // Delete profile
    $(document).on('click', '.vpnpm-delete-btn', function() {
      const $btn = $(this);
      const $card = $btn.closest('.vpnpm-card');
      const id = $btn.data('id');
      if (!window.confirm(vpnpmAjax.strings.confirmDelete)) return;

      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_delete_profile', _ajax_nonce: vpnpmAjax.nonce, id: id }
      }).done(function(resp) {
        if (resp && resp.success) {
          $card.fadeOut(150, function() { $(this).remove(); });
        } else {
          alert((resp && resp.data && resp.data.message) || 'Delete failed.');
        }
      }).fail(function() {
        alert('Delete failed.');
      });
    });

    // Open edit modal and load data
    function openEditModal(id) {
      const $modal = $('#vpnpm-edit-modal');
      $modal.attr('aria-hidden', 'false');
      $('#vpnpm-edit-form')[0].reset();
      $('#vpnpm-edit-id').val(id);
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'GET',
        dataType: 'json',
        data: { action: 'vpnpm_get_profile', _ajax_nonce: vpnpmAjax.nonce, id: id }
      }).done(function(resp) {
        if (resp && resp.success) {
          const d = resp.data;
          $('#vpnpm-edit-remote').val(d.remote_host || '');
          $('#vpnpm-edit-port').val(d.port || '');
          $('#vpnpm-edit-protocol').val((d.protocol || '').toLowerCase());
          $('#vpnpm-edit-cipher').val(d.cipher || '');
          $('#vpnpm-edit-status').val((d.status || 'unknown').toLowerCase());
          $('#vpnpm-edit-notes').val(d.notes || '');
        } else {
          alert((resp && resp.data && resp.data.message) || 'Failed to load profile.');
        }
      }).fail(function() {
        alert('Failed to load profile.');
      });
    }

    $(document).on('click', '.vpnpm-edit-btn', function() {
      const id = $(this).data('id');
      openEditModal(id);
    });

    // Close edit modal
    $(document).on('click', '.vpnpm-modal-close[data-close="edit"], #vpnpm-edit-modal .vpnpm-modal-backdrop[data-close="edit"]', function() {
      $('#vpnpm-edit-modal').attr('aria-hidden', 'true');
    });

    // Save edit form
    $(document).on('submit', '#vpnpm-edit-form', function(e) {
      e.preventDefault();
      const id = $('#vpnpm-edit-id').val();
      const payload = {
        action: 'vpnpm_update_profile',
        _ajax_nonce: vpnpmAjax.nonce,
        id: id,
        remote_host: $('#vpnpm-edit-remote').val(),
        port: $('#vpnpm-edit-port').val(),
        protocol: $('#vpnpm-edit-protocol').val(),
        cipher: $('#vpnpm-edit-cipher').val(),
        status: $('#vpnpm-edit-status').val(),
        notes: $('#vpnpm-edit-notes').val()
      };
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: payload
      }).done(function(resp) {
        if (resp && resp.success) {
          // Update card UI
          const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + id + '"]').closest('.vpnpm-card');
          const d = resp.data;
          $card.find('p:contains("Host:")').html('Host: ' + (d.remote_host || ''));
          $card.find('p:contains("Port:")').html('Port: ' + (d.port || ''));
          $card.find('p:contains("Protocol:")').html('Protocol: ' + ((d.protocol || '').toUpperCase() || 'N/A'));
          const statusMap = {active:'status-active', down:'status-offline', unknown:'status-unknown'};
          const s = (d.status || 'unknown').toLowerCase();
          $card.find('.vpnpm-status').removeClass('status-active status-offline status-unknown').addClass(statusMap[s] || 'status-unknown').text(s.charAt(0).toUpperCase() + s.slice(1));
          // Update search haystack data attribute
          const name = $card.find('h3').text();
          const hay = (name + ' ' + (d.remote_host||'') + ' ' + (d.port||'') + ' ' + ((d.protocol||'').toUpperCase()) + ' ' + (s) + ' ' + (d.notes || '')).toLowerCase();
          $card.attr('data-search', hay);
          $('#vpnpm-edit-modal').attr('aria-hidden', 'true');
        } else {
          alert((resp && resp.data && resp.data.message) || 'Update failed.');
        }
      }).fail(function() {
        alert('Update failed.');
      });
    });
  });
})(jQuery);
