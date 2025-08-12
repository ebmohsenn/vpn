(function($){
  $(document).on('click', '.button[data-action=ping]', function(e){
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
    });
  });

  $(document).on('click', '.button[data-action=more-ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    $.post(HOVPNM_CH.ajaxUrl, { action:'hovpnm_ch_ping', id:id, _ajax_nonce:HOVPNM_CH.nonce }, function(res){
      if(!res || !res.success){ alert(HOVPNM_CH.msgPingFailed); return; }
      var ping=parseInt(res.data.ping,10);
  var m=$('#hovpnm-edit-modal');
      if(!m.length){ alert(HOVPNM_CH.msgPingLabel+' '+ping+' ms'); return; }
      m.find('h2').text(HOVPNM_CH.msgMorePing);
      m.find('form').hide();
  if(!m.find('.hovpnm-ping-view').length){ m.find('> div').append('<div class="hovpnm-ping-view" style="margin-top:10px;"></div>'); }
      m.find('.hovpnm-ping-view').html('<p><strong>'+HOVPNM_CH.msgCHPing+'</strong> '+ping+' ms</p>');
  // Ensure there is a close button
  if(!m.find('.hovpnm-modal-close').length){ m.find('> div').prepend('<button type="button" class="button hovpnm-modal-close" style="position:absolute; right:12px; top:12px;">&times;</button>'); }
  m.show();
    });
  });

  $(document).on('click', '.hovpnm-modal-close', function(){
    var m=$('#hovpnm-edit-modal');
    m.hide(); m.find('form').show(); m.find('.hovpnm-ping-view').remove(); m.find('h2').text(HOVPNM_CH.msgEditTitle);
  });
})(jQuery);
