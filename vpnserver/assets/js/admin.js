(function($) {
  $(function() {
    // Settings: Send Test Telegram Notification
    $(document).on('click', '#vpnpm-settings-telegram-test', function() {
      const $btn = $(this);
      const $msg = $('#vpnpm-settings-msg');
      const original = $btn.text();
      $btn.prop('disabled', true).text('Sending...');
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_send_telegram_test', _ajax_nonce: vpnpmAjax.nonce }
      }).done(function(resp){
        if (resp && resp.success) {
          $msg.text(resp.data && resp.data.message ? resp.data.message : 'Message sent.').removeClass('notice-error').addClass('notice notice-success');
        } else {
          const m = resp && resp.data && resp.data.message ? resp.data.message : 'Send failed.';
          $msg.text(m).removeClass('notice-success').addClass('notice notice-error');
        }
      }).fail(function(){
        $msg.text('Send failed.').removeClass('notice-success').addClass('notice notice-error');
      }).always(function(){
        setTimeout(function(){ $btn.prop('disabled', false).text(original); }, 1200);
      });
    });
    // Telegram Test button
    $(document).on('click', '#vpnpm-telegram-test', function() {
      const $btn = $(this);
      const orig = $btn.text();
      $btn.prop('disabled', true).text('Sending...');
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_send_telegram_test', _ajax_nonce: vpnpmAjax.nonce }
      }).done(function(resp){
        if (resp && resp.success) {
          $btn.text('Sent ✓');
        } else {
          alert((resp && resp.data && resp.data.message) || 'Telegram test failed.');
          $btn.text(orig);
        }
      }).fail(function(){
        alert('Telegram test failed.');
        $btn.text(orig);
      }).always(function(){
        setTimeout(function(){ $btn.prop('disabled', false).text(orig); }, 1500);
      });
    });
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

  const $ping = $card.find('.vpnpm-ping-server');
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

    // More Ping modal handlers
    function openMorePing(id) {
      const $modal = $('#vpnpm-moreping-modal');
      $modal.removeAttr('inert').attr('aria-hidden','false').removeAttr('hidden');
      // Focus the close button for immediate keyboard access
      setTimeout(function(){
        var $close = $modal.find('.vpnpm-modal-close[data-close="moreping"]').first();
        if ($close.length) { $close.trigger('focus'); }
      }, 0);
      $('#vpnpm-moreping-loading').show();
      $('#vpnpm-moreping-error').hide().text('');
      $('#vpnpm-moreping-content').hide();
      $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_get_checkhost_details', _ajax_nonce: vpnpmAjax.nonce, id: id }
      }).done(function(resp){
        $('#vpnpm-moreping-loading').hide();
        if (resp && resp.success && resp.data) {
          const d = resp.data;
          $('#vpnpm-moreping-server').text(d.server || '');
          $('#vpnpm-moreping-updated').text(d.updated || 'N/A');
          const $nodes = $('#vpnpm-moreping-nodes').empty();
          if (Array.isArray(d.nodes) && d.nodes.length) {
            d.nodes.forEach(function(n){
              const avg = n.avg != null ? n.avg + ' ms' : 'N/A';
              const min = n.min != null ? n.min + ' ms' : 'N/A';
              const max = n.max != null ? n.max + ' ms' : 'N/A';
              const loss = n.loss != null ? n.loss + '%' : 'N/A';
              const statusClass = (n.avg != null && n.avg <= 120) ? 'badge-green' : (n.avg != null && n.avg <= 250) ? 'badge-blue' : 'badge-red';
              const $row = $('<div class="vpnpm-node-row" style="display:flex; gap:10px; align-items:center; padding:4px 0;" />');
              const friendly = n.label || n.node || '';
              $row.append('<span class="badge '+statusClass+'" title="packet loss: '+loss+'">'+friendly+'</span>');
              $row.append('<span>avg: '+avg+'</span>');
              $row.append('<span>min: '+min+'</span>');
              $row.append('<span>max: '+max+'</span>');
              $row.append('<span>loss: '+loss+'</span>');
              $nodes.append($row);
            });
          } else {
            $nodes.append('<p>No node data.</p>');
          }
          $('#vpnpm-moreping-content').show();
        } else {
          const m = resp && resp.data && resp.data.message ? resp.data.message : 'Failed to load details.';
          $('#vpnpm-moreping-error').text(m).show();
        }
      }).fail(function(){
        $('#vpnpm-moreping-loading').hide();
        $('#vpnpm-moreping-error').text('Failed to load details.').show();
      });
    }

    $(document).on('click', '[data-role="vpnpm-more-ping"]', function(){
      openMorePing($(this).data('id'));
    });

    function closeMorePingModal() {
      const $modal = $('#vpnpm-moreping-modal');
      // Move focus outside before hiding to avoid aria-hidden on focused element
      const $fallbackFocus = $('#vpnpm-add-server-btn');
      if ($fallbackFocus.length) { $fallbackFocus.trigger('focus'); }
      else { $('body').attr('tabindex','-1').trigger('focus'); }
      // Now safely hide modal
      $modal.attr('aria-hidden','true').attr('hidden','hidden').attr('inert','');
    }

    $(document).on('click', '.vpnpm-modal-close[data-close="moreping"], #vpnpm-moreping-modal .vpnpm-modal-backdrop[data-close="moreping"]', function(){
      closeMorePingModal();
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

    // Helper: close edit modal with safe focus handling
    function closeEditModal() {
      const $modal = $('#vpnpm-edit-modal');
      // Move focus to a safe element outside the modal before hiding
      const $fallbackFocus = $('#vpnpm-add-server-btn');
      if ($fallbackFocus.length) { $fallbackFocus.trigger('focus'); }
      else { $('body').attr('tabindex','-1').trigger('focus'); }
      // Hide modal and mark as hidden for AT
      $modal.attr('aria-hidden', 'true').attr('hidden', 'hidden').attr('inert','');
    }

    // Close edit modal (buttons/backdrop)
    $(document).on('click', '.vpnpm-modal-close[data-close="edit"], #vpnpm-edit-modal .vpnpm-modal-backdrop[data-close="edit"]', function() {
      closeEditModal();
    });

    // Open edit modal
    function openEditModal(id) {
      const $modal = $('#vpnpm-edit-modal');
      $modal.removeAttr('inert').attr('aria-hidden', 'false').removeAttr('hidden');
      $('#vpnpm-edit-form')[0].reset();
      $('#vpnpm-edit-id').val(id);
      // Focus first field when opening
      setTimeout(function(){ $('#vpnpm-edit-remote').trigger('focus'); }, 0);
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
          $('#vpnpm-edit-location').val(d.location || '');
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
        label: $('#vpnpm-edit-label').val(),
  type: $('#vpnpm-edit-type').val(),
  location: $('#vpnpm-edit-location').val()
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
          var newType = (d && d.type) ? d.type : (payload.type || 'standard');
          $card.find('p:contains("Type:")').html('Type: <span class="vpnpm-type ' + (newType === 'premium' ? 'type-premium' : 'type-standard') + '">' + (newType.charAt(0).toUpperCase() + newType.slice(1)) + '</span>');
          $card.find('.vpnpm-ping').text(d.ping !== null && d.ping !== undefined ? d.ping + ' ms' : 'N/A');
          if (d.location !== undefined) {
            const $loc = $card.find('.vpnpm-location');
            if ($loc.length) $loc.text(d.location || 'Unknown');
          }
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
        const arr = resp && resp.success && resp.data && Array.isArray(resp.data.servers) ? resp.data.servers : null;
        if (arr) {
          arr.forEach(function(server) {
            lastCheckedTimes[server.id] = server.last_checked;
            const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + server.id + '"]').closest('.vpnpm-card');
            if (!$card.length) return;

            const $status = $card.find('.vpnpm-status');
            $status.text(server.status.charAt(0).toUpperCase() + server.status.slice(1));
            $status.removeClass('status-active status-offline status-unknown')
                   .addClass('status-' + server.status);

            const $ping = $card.find('.vpnpm-ping-server');
            const oldPing = parseInt($ping.text(), 10);
            if (server.ping !== null && oldPing !== server.ping) {
              $ping.text(server.ping + ' ms').addClass('ping-changed');
              setTimeout(function() { $ping.removeClass('ping-changed'); }, 5000);
            }

            const $ch = $card.find('.vpnpm-ping-ch');
            if ($ch.length && server.ch_ping !== undefined) {
              const oldCh = parseInt($ch.text(), 10);
              if (server.ch_ping !== null && oldCh !== server.ch_ping) {
                $ch.text(server.ch_ping + ' ms').addClass('ping-changed');
                setTimeout(function() { $ch.removeClass('ping-changed'); }, 5000);
              }
            }

            const $lastChecked = $card.find('.vpnpm-last-checked');
            if ($lastChecked.length) {
              const relative = timeAgo(new Date(server.last_checked));
              $lastChecked.text('Last checked: ' + relative);
              $lastChecked.attr('title', new Date(server.last_checked).toLocaleString());
            }
          });
        } else {
          console.warn('No servers array in response', resp);
        }
      });
    }, 30000);

    setInterval(updateRelativeTimes, 30000);
  });
})(jQuery);