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
      $('#vpnpm-modal').attr('aria-hidden', 'false').removeAttr('hidden');
    });
    $(document).on('click', '#vpnpm-modal-close, #vpnpm-modal-close-btn, #vpnpm-cancel', function() {
      $('#vpnpm-modal').find(':focus').blur();
      $('#vpnpm-modal').attr('inert', '').attr('hidden', 'hidden');
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

        const $ping = $card.find('.vpnpm-ping');
        const newPing = resp && resp.success ? resp.data.ping : null;
        if (newPing !== null) {
          const oldPing = parseInt($ping.text(), 10);
          if (oldPing !== newPing) {
            $ping.text(newPing + ' ms').addClass('ping-changed');
            setTimeout(function() { $ping.removeClass('ping-changed'); }, 5000);
          }
        }
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

    // Open edit modal
    function openEditModal(id) {
      const $modal = $('#vpnpm-edit-modal');
      $modal.attr('aria-hidden', 'false').removeAttr('hidden');
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
          $('#vpnpm-edit-label').val((d.label || 'standard').toLowerCase());
          $('#vpnpm-edit-type').val((d.type || 'standard').toLowerCase());
        } else {
          alert((resp && resp.data && resp.data.message) || 'Failed to load profile.');
        }
      }).fail(function() {
        alert('Failed to load profile.');
      });
    }

    $(document).on('click', '.vpnpm-edit-btn', function() {
      openEditModal($(this).data('id'));
    });

    // Close edit modal
    $(document).on('click', '.vpnpm-modal-close[data-close="edit"], #vpnpm-edit-modal .vpnpm-modal-backdrop[data-close="edit"]', function() {
      $('#vpnpm-edit-modal').find(':focus').blur();
      $('#vpnpm-edit-modal').attr('aria-hidden', 'true').attr('hidden', 'hidden');
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
        notes: $('#vpnpm-edit-notes').val(),
        label: $('#vpnpm-edit-label').val()
      };
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: payload
      }).done(function(resp) {
        if (resp && resp.success) {
          const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + id + '"]').closest('.vpnpm-card');
          const d = resp.data;
          $card.find('p:contains("Host:")').html('Host: ' + (d.remote_host || ''));
          $card.find('p:contains("Port:")').html('Port: ' + (d.port || ''));
          $card.find('p:contains("Protocol:")').html('Protocol: ' + ((d.protocol || '').toUpperCase() || 'N/A'));
          const statusMap = {active:'status-active', down:'status-offline', unknown:'status-unknown'};
          const s = (d.status || 'unknown').toLowerCase();
          $card.find('.vpnpm-status').removeClass('status-active status-offline status-unknown').addClass(statusMap[s] || 'status-unknown').text(s.charAt(0).toUpperCase() + s.slice(1));
          $card.find('p:contains("Notes:")').html('Notes: ' + (d.notes || 'No notes available'));
          $card.find('p:contains("Label:")').html('Label: <span class="vpnpm-label ' + (d.label === 'premium' ? 'label-premium' : 'label-standard') + '">' + ((d.label && d.label.charAt(0).toUpperCase() + d.label.slice(1)) || 'N/A') + '</span>');
          $card.find('p:contains("Type:")').html('Type: <span class="vpnpm-type ' + (d.type === 'premium' ? 'type-premium' : 'type-standard') + '">' + ((d.type && d.type.charAt(0).toUpperCase() + d.type.slice(1)) || 'N/A') + '</span>');
          $card.find('.vpnpm-ping').text(d.ping !== null && d.ping !== undefined ? d.ping + ' ms' : 'N/A');
          $('#vpnpm-edit-modal').find(':focus').blur();
          $('#vpnpm-edit-modal').attr('aria-hidden', 'true').attr('hidden', 'hidden');
        } else {
          alert((resp && resp.data && resp.data.message) || 'Update failed.');
        }
      }).fail(function() {
        alert('Update failed.');
      });
    });

    // Sorting + refresh logic (moved inside closure)
    function timeAgo(date) {
      const now = new Date();
      const seconds = Math.floor((now - date) / 1000);
      if (seconds < 60) return 'just now';
      if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
      if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
      return Math.floor(seconds / 86400) + ' days ago';
    }

    const lastCheckedTimes = {};
    function updateRelativeTimes() {
      Object.entries(lastCheckedTimes).forEach(([id, lastChecked]) => {
        const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + id + '"]').closest('.vpnpm-card');
        if ($card.length) {
          const $lastChecked = $card.find('.vpnpm-last-checked');
          if ($lastChecked.length) {
            const relative = timeAgo(new Date(lastChecked));
            $lastChecked.text('Last checked: ' + relative);
            $lastChecked.attr('title', new Date(lastChecked).toLocaleString());
          }
        }
      });
    }

    setInterval(function() {
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_get_all_status', _ajax_nonce: vpnpmAjax.nonce },
      }).done(function(resp) {
        if (resp && resp.success) {
          resp.servers.forEach(function(server) {
            lastCheckedTimes[server.id] = server.last_checked;
            const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + server.id + '"]').closest('.vpnpm-card');
            if (!$card.length) return;

            const $status = $card.find('.vpnpm-status');
            $status.text(server.status.charAt(0).toUpperCase() + server.status.slice(1));
            $status.removeClass('status-active status-offline status-unknown')
                   .addClass('status-' + server.status);

            const $ping = $card.find('.vpnpm-ping');
            const oldPing = parseInt($ping.text(), 10);
            if (server.ping !== null && oldPing !== server.ping) {
              $ping.text(server.ping + ' ms').addClass('ping-changed');
              setTimeout(function() { $ping.removeClass('ping-changed'); }, 5000);
            }

            const $lastChecked = $card.find('.vpnpm-last-checked');
            if ($lastChecked.length) {
              const relative = timeAgo(new Date(server.last_checked));
              $lastChecked.text('Last checked: ' + relative);
              $lastChecked.attr('title', new Date(server.last_checked).toLocaleString());
            }
          });
        }
      });
    }, 30000);

    setInterval(updateRelativeTimes, 30000);
  });
})(jQuery);