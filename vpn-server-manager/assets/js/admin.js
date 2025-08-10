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
  });
})(jQuery);
