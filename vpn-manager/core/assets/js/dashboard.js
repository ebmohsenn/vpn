(function($){
  function closeModal(){ $('#hovpnm-edit-modal').hide(); }
  function openModal(){ $('#hovpnm-edit-modal').show(); }

  $(document).on('click', '.hovpnm-edit-btn', function(e){
    e.preventDefault(); var b=$(this), m=$('#hovpnm-edit-modal');
    m.find('[name=id]').val(b.data('id'));
    m.find('[name=file_name]').val(b.data('name'));
    m.find('[name=remote_host]').val(b.data('remote'));
    m.find('[name=port]').val(b.data('port'));
    m.find('[name=protocol]').val(b.data('protocol'));
    m.find('[name=cipher]').val(b.data('cipher'));
    m.find('[name=type]').val(b.data('type'));
    m.find('[name=label]').val(b.data('label'));
    m.find('[name=location]').val(b.data('location'));
    m.find('[name=notes]').val(b.data('notes'));
    openModal();
  });

  $(document).on('click', '.hovpnm-modal-close', function(e){ e.preventDefault(); closeModal(); });

  $(document).on('submit', '#hovpnm-edit-form', function(e){
    e.preventDefault(); var f=$(this), id=f.find('[name=id]').val();
    var payload={}; ['file_name','remote_host','port','protocol','cipher','type','label','location','notes'].forEach(function(k){ payload[k]=f.find('[name='+k+']').val(); });
    if(payload.port==='') delete payload.port;
    var url=HOVPNM_DASH.apiBase+id;
    $.ajax({ url:url, method:'POST', contentType:'application/json', data: JSON.stringify(payload), beforeSend:function(xhr){ xhr.setRequestHeader('X-WP-Nonce', HOVPNM_DASH.nonce); } })
      .done(function(res){
        if(!res || !res.updated){ alert(HOVPNM_DASH.msgNoChange); return; }
        closeModal(); setTimeout(function(){ location.reload(); }, 300);
      })
      .fail(function(){ alert(HOVPNM_DASH.msgFail); });
  });
})(jQuery);
