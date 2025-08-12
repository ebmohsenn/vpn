(function($){
  $(document).on('click', '.button[data-action=ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    btn.prop('disabled',true).text(HOVPNM_CH.msgPinging);
    $.post(HOVPNM_CH.ajaxUrl, { action:'hovpnm_ch_ping', id:id, _ajax_nonce:HOVPNM_CH.nonce }, function(res){
      btn.prop('disabled',false).text(HOVPNM_CH.msgPing);
      if(!res || !res.success){ alert(HOVPNM_CH.msgPingFailed); return; }
      var ping=parseInt(res.data.ping,10);
      alert(HOVPNM_CH.msgPingLabel+' '+ping+' ms');
      setTimeout(function(){ location.reload(); }, 200);
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
      m.show();
    });
  });

  $(document).on('click', '.hovpnm-modal-close', function(){
    var m=$('#hovpnm-edit-modal');
    m.hide(); m.find('form').show(); m.find('.hovpnm-ping-view').remove(); m.find('h2').text(HOVPNM_CH.msgEditTitle);
  });
})(jQuery);
