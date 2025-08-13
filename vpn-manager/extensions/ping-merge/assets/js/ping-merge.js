(function($){
  $(document).on('click', '.button[data-action="ping"]', function(e){
    e.preventDefault();
    var b=$(this), id=b.data('id');
    b.prop('disabled', true);
    var d1=$.Deferred(), d2=$.Deferred();
  var ajaxUrl = (window.HOVPNM_DASH && HOVPNM_DASH.ajaxUrl) ? HOVPNM_DASH.ajaxUrl : (typeof ajaxurl!=='undefined'?ajaxurl:'');
  var spNonce = (window.HOVPNM_SP && HOVPNM_SP.nonce) ? HOVPNM_SP.nonce : (HOVPNM_DASH && HOVPNM_DASH.ajaxNonce ? HOVPNM_DASH.ajaxNonce : '');
  var chNonce = (window.HOVPNM_CH && HOVPNM_CH.nonce) ? HOVPNM_CH.nonce : (HOVPNM_DASH && HOVPNM_DASH.ajaxNonce ? HOVPNM_DASH.ajaxNonce : '');
  $.post(ajaxUrl, { action:'hovpnm_srv_ping', id:id, _ajax_nonce: spNonce }, function(){ d1.resolve(); }).fail(function(){ d1.resolve(); });
  $.post(ajaxUrl, { action:'hovpnm_ch_ping', id:id, force:1, _ajax_nonce: chNonce }, function(){ d2.resolve(); }).fail(function(){ d2.resolve(); });
    $.when(d1, d2).always(function(){ b.prop('disabled', false); });
  });
})(jQuery);
