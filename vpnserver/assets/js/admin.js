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

        // Update ping display
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

    // Open edit modal and load data
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
          // Ensure notes are updated in the card after saving changes
          $card.find('p:contains("Notes:")').html('Notes: ' + (d.notes || 'No notes available'));
          $('#vpnpm-edit-modal').attr('aria-hidden', 'true').attr('hidden', 'hidden');
        } else {
          alert((resp && resp.data && resp.data.message) || 'Update failed.');
        }
      }).fail(function() {
        alert('Update failed.');
      });
    });

    // Live refresh server statuses every 30 seconds
setInterval(function() {
    $.ajax({
        url: vpnpmAjax.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: { action: 'vpnpm_get_all_status', _ajax_nonce: vpnpmAjax.nonce },
    }).done(function(resp) {
        if (resp && resp.success && Array.isArray(resp.servers)) {
            resp.servers.forEach(function(server) {
                const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + server.id + '"]').closest('.vpnpm-card');
                if (!$card.length) return;

                // Update status
                const $status = $card.find('.vpnpm-status');
                $status.text(server.status.charAt(0).toUpperCase() + server.status.slice(1));
                $status.removeClass('status-active status-offline status-unknown')
                       .addClass('status-' + server.status);

                // Update ping and highlight changes
                const $ping = $card.find('.vpnpm-ping'); // Use 'let' to avoid redeclaration issues
                const newPing = server.ping !== null ? server.ping + ' ms' : 'N/A';
                $ping.text(newPing);

                // Update last checked
                const lastCheckedDate = new Date(server.last_checked);
                const relative = timeAgo(lastCheckedDate);
                $card.find('.vpnpm-last-checked')
                    .text('Last checked: ' + relative)
                    .attr('title', lastCheckedDate.toLocaleString());
            });
        }
    });
}, 30000); // 30 seconds

// Sort servers by status: active on top, down below
function sortServers() {
  const $grid = $('#vpnpm-grid');
  const $cards = $grid.children('.vpnpm-card');

  $cards.sort(function(a, b) {
    const statusA = $(a).find('.vpnpm-status').text().toLowerCase();
    const statusB = $(b).find('.vpnpm-status').text().toLowerCase();

    if (statusA === 'active' && statusB !== 'active') return -1;
    if (statusA !== 'active' && statusB === 'active') return 1;
    if (statusA === 'down' && statusB !== 'down') return 1;
    if (statusA !== 'down' && statusB === 'down') return -1;
    return 0;
  });

  $grid.append($cards); // Re-append sorted cards to the grid
}

// Call sortServers after AJAX updates
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
      const $card = $('.vpnpm-card .vpnpm-test-btn[data-id="' + id + '"]').closest('.vpnpm-card');
      const d = resp.data;
      $card.find('p:contains("Notes:")').html('Notes: ' + (d.notes || 'No notes available'));
      $card.find('.vpnpm-status').text(d.status.charAt(0).toUpperCase() + d.status.slice(1));
      sortServers(); // Sort servers after update
      $('#vpnpm-edit-modal').attr('aria-hidden', 'true').attr('hidden', 'hidden');
    } else {
      alert((resp && resp.data && resp.data.message) || 'Update failed.');
    }
  }).fail(function() {
    alert('Update failed.');
  });
});

// Call sortServers on page load
$(document).ready(function() {
  sortServers();
});
  });
})(jQuery);

function timeAgo(date) {
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);

  if (seconds < 60) return 'just now';
  if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
  if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
  return Math.floor(seconds / 86400) + ' days ago';
}
// Store last_checked timestamps for each server by ID
const lastCheckedTimes = {};

// Update relative times every 30 seconds
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

// Update lastCheckedTimes and UI during AJAX refresh
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

        // Update status
        const $status = $card.find('.vpnpm-status');
        $status.text(server.status.charAt(0).toUpperCase() + server.status.slice(1));
        $status.removeClass('status-active status-offline status-unknown')
               .addClass('status-' + server.status);

        // Update ping and highlight changes
        const $ping = $card.find('.vpnpm-ping');
        const oldPing = parseInt($ping.text(), 10);
        if (server.ping !== null && oldPing !== server.ping) {
          $ping.text(server.ping + ' ms').addClass('ping-changed');
          setTimeout(function() { $ping.removeClass('ping-changed'); }, 5000);
        }

        // Update relative last checked text immediately
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

// Also run relative time updater every 30 seconds to keep time updated without reload
setInterval(updateRelativeTimes, 30000);

// Your existing timeAgo function here...