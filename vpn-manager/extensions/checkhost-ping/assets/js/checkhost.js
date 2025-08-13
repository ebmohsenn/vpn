(function($){
  // Dedicated button id for Check-Host extension
  $(document).on('click', '.button[data-action=checkhost-ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    btn.prop('disabled',true).text(HOVPNM_CH.msgPinging);
    $.post(HOVPNM_CH.ajaxUrl, { action:'hovpnm_ch_ping', id:id, force:1, _ajax_nonce:HOVPNM_CH.nonce }, function(res){
      btn.prop('disabled',false).text(HOVPNM_CH.msgPing);
      if(!res || !res.success){ alert(HOVPNM_CH.msgPingFailed); return; }
      var ping=parseInt(res.data.ping,10);
      var row=btn.closest('tr');
      var cell=row.find('td[data-col="ch_ping"]');
      if(cell.length){
        cell.text(ping+' ms').addClass('hovpnm-flash');
        setTimeout(function(){ cell.removeClass('hovpnm-flash'); }, 3000);
      }
      // Update status cell
      var stCell=row.find('td[data-col="status"]');
      if(stCell.length){
        var isUp = (res.data.status||'').toLowerCase()==='active';
        var label = isUp ? HOVPNM_CH.msgActive : HOVPNM_CH.msgDown;
        stCell.html('<span style="'+(isUp?'color:green;':'color:#a00;')+'">'+label+'</span>').addClass('hovpnm-flash');
        setTimeout(function(){ stCell.removeClass('hovpnm-flash'); }, 3000);
      }
    });
  });

  $(document).on('click', '.button[data-action=more-ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    var m=$('#hovpnm-edit-modal');
    if(!m.length){ return; }
    m.find('h2').text(HOVPNM_CH.msgMorePing);
    m.find('form').hide();
    if(!m.find('.hovpnm-ping-view').length){ m.find('> div').append('<div class="hovpnm-ping-view" style="margin-top:10px;"><div class="hovpnm-history-wrap"><table class="widefat striped hovpnm-history"><thead><tr><th data-k="timestamp">Date & Time</th><th data-k="ping_value">Ping Value</th><th data-k="status">Status</th><th data-k="location">Location</th></tr></thead><tbody></tbody></table></div><div class="hovpnm-graph-wrap" style="margin-top:12px;"><canvas id="hovpnm-history-chart" height="120"></canvas></div></div>'); }
    if(!m.find('.hovpnm-modal-close').length){ m.find('> div').prepend('<button type="button" class="button hovpnm-modal-close" style="position:absolute; right:12px; top:12px;">&times;</button>'); }
    m.show();
    // Load history
    $.get(HOVPNM_CH.ajaxUrl, { action: HOVPNM_CH.historyAction, id: id, _ajax_nonce: HOVPNM_CH.nonce }, function(res){
      if(!res || !res.success){ return; }
      var items = res.data.items || [];
      var tbody = m.find('table.hovpnm-history tbody');
      tbody.empty();
      items.forEach(function(r){
        var tr = $('<tr/>');
        tr.append($('<td/>').text(r.timestamp));
        tr.append($('<td/>').text((r.ping_value!=null?r.ping_value:'') + (r.ping_value!=null?' ms':'')));
        tr.append($('<td/>').text(r.status||''));
        tr.append($('<td/>').text(r.location||''));
        tbody.append(tr);
      });
      // Simple sortable headers
      m.find('table.hovpnm-history thead th').off('click').on('click', function(){
        var k = $(this).data('k');
        var rows = tbody.find('tr').get();
        rows.sort(function(a,b){
          var ta = $(a).find('td').eq($(this).index()).text();
          var tb = $(b).find('td').eq($(this).index()).text();
          if($.isNumeric(ta) && $.isNumeric(tb)) return parseFloat(ta)-parseFloat(tb);
          return ta.localeCompare(tb);
        }.bind(this));
        $.each(rows, function(_, r){ tbody.append(r); });
      });
      // Chart.js placeholder: if historical-graphs extension enqueues Chart, render
      if(window.Chart){
        try {
          var ctx = document.getElementById('hovpnm-history-chart');
          if (ctx) {
            var labels = items.slice().reverse().map(function(r){ return r.timestamp; });
            var data = items.slice().reverse().map(function(r){ return r.ping_value; });
            new Chart(ctx, { type: 'line', data: { labels: labels, datasets: [{ label: 'Ping (ms)', data: data, borderColor: '#2271b1', tension: 0.2 }] }, options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } } });
          }
        } catch(e) { /* noop */ }
      } else {
        m.find('.hovpnm-graph-wrap').hide();
      }
    });
  });

  $(document).on('click', '.hovpnm-modal-close', function(){
    var m=$('#hovpnm-edit-modal');
    m.hide(); m.find('form').show(); m.find('.hovpnm-ping-view').remove(); m.find('h2').text(HOVPNM_CH.msgEditTitle);
  });
})(jQuery);
