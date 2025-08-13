(function($){
  $(document).on('click', '.button[data-action=ping]', function(e){
    e.preventDefault();
    var btn=$(this), id=btn.data('id');
    btn.prop('disabled',true).text(HOVPNM_CP.msgPinging);
    $.post(HOVPNM_CP.ajaxUrl, { action:'hovpnm_core_ping', id:id, _ajax_nonce:HOVPNM_CP.nonce })
      .done(function(res){
        btn.prop('disabled',false).text(HOVPNM_CP.msgPing);
        if(!res || !res.success) return;
        var row=btn.closest('tr');
        var v=res.data.ping;
        var cell=row.find('td[data-col="ping"]');
        if(cell.length){ cell.text((v!==null && $.isNumeric(v))?(parseInt(v,10)+' ms'):'N/A').addClass('hovpnm-flash'); setTimeout(function(){cell.removeClass('hovpnm-flash');}, 3000); }
        if(res.data.status){
          var stCell=row.find('td[data-col="status"]');
          var isUp=(res.data.status||'').toLowerCase()==='active';
          if(stCell.length){ stCell.html('<span style="'+(isUp?'color:green;':'color:#a00;')+'">'+(isUp?HOVPNM_CP.msgActive:HOVPNM_CP.msgDown)+'</span>').addClass('hovpnm-flash'); setTimeout(function(){stCell.removeClass('hovpnm-flash');}, 3000); }
        }
      })
      .fail(function(){ btn.prop('disabled',false).text(HOVPNM_CP.msgPing); });
  });
})(jQuery);
