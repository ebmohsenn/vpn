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
    m.find('[name=location]').val(b.data('location'));
    m.find('[name=notes]').val(b.data('notes'));
    openModal();
  });

  $(document).on('click', '.hovpnm-modal-close', function(e){ e.preventDefault(); closeModal(); });

  $(document).on('submit', '#hovpnm-edit-form', function(e){
    e.preventDefault(); var f=$(this), id=f.find('[name=id]').val();
  var payload={}; ['file_name','remote_host','port','protocol','cipher','type','location','notes'].forEach(function(k){ payload[k]=f.find('[name='+k+']').val(); });
    if(payload.port==='') delete payload.port;
    var url=HOVPNM_DASH.apiBase+id;
    $.ajax({ url:url, method:'POST', contentType:'application/json', data: JSON.stringify(payload), beforeSend:function(xhr){ xhr.setRequestHeader('X-WP-Nonce', HOVPNM_DASH.nonce); } })
      .done(function(res){
        if(!res || !res.updated){ alert(HOVPNM_DASH.msgNoChange); return; }
        closeModal(); setTimeout(function(){ location.reload(); }, 300);
      })
      .fail(function(){ alert(HOVPNM_DASH.msgFail); });
  });

  // Ping All: clicks available ping buttons per row with small delay
  $(document).on('click', '.hovpnm-ping-all', function(e){
    e.preventDefault();
    var btn=$(this);
    var allBtns=[];
    $('.hovpnm-table tbody tr').each(function(){
      var row=$(this);
      var merged = row.find('.button[data-action="ping"]');
      if(merged.length){ allBtns.push(merged.get(0)); return; }
      var srv = row.find('.button[data-action="server-ping"]');
      var ch = row.find('.button[data-action="checkhost-ping"]');
      if(srv.length) allBtns.push(srv.get(0));
      if(ch.length) allBtns.push(ch.get(0));
    });
    if(!allBtns.length){
      alert(HOVPNM_DASH.msgNoPingBtns);
      return;
    }
    btn.prop('disabled', true).text(HOVPNM_DASH.msgPingingAll);
    var i=0;
    function next(){
      if(i>=allBtns.length){ btn.prop('disabled', false).text(HOVPNM_DASH.msgPingAll); return; }
      var b=$(allBtns[i++]);
      // Trigger click only if not disabled
      if(!b.prop('disabled')) { b.trigger('click'); }
      setTimeout(next, 350); // small stagger
    }
    next();
  });
})(jQuery);

(function($){
  $(document).on('submit', 'form[action$="admin-post.php"]:has(input[name=action][value=hovpnm_delete_server])', function(e){
    if(!confirm('Delete this server?')) { e.preventDefault(); }
  });
})(jQuery);
