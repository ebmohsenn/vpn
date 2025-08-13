(function($){
  $(document).on('click', '.button[data-action=server-ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    btn.prop('disabled',true).text(HOVPNM_SP.msgPinging);
    $.post(HOVPNM_SP.ajaxUrl, { action:'hovpnm_srv_ping', id:id, _ajax_nonce:HOVPNM_SP.nonce }, function(res){
      btn.prop('disabled',false).text(HOVPNM_SP.msgPing);
      if(!res || !res.success){ alert(HOVPNM_SP.msgPingFailed); return; }
      var ping=parseInt(res.data.ping,10);
      var row=btn.closest('tr');
      var cell=row.find('td[data-col="srv_ping"]');
      if(cell.length){
        cell.text(ping+' ms').addClass('hovpnm-flash');
        setTimeout(function(){ cell.removeClass('hovpnm-flash'); }, 3000);
      }
      // Update status cell
      var stCell=row.find('td[data-col="status"]');
      if(stCell.length){
        var isUp = (res.data.status||'').toLowerCase()==='active';
        var label = isUp ? HOVPNM_SP.msgActive : HOVPNM_SP.msgDown;
        stCell.html('<span style="'+(isUp?'color:green;':'color:#a00;')+'">'+label+'</span>').addClass('hovpnm-flash');
        setTimeout(function(){ stCell.removeClass('hovpnm-flash'); }, 3000);
      }
    });
  });
})(jQuery);
