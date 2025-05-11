jQuery( document ).ready(function($) {
  "use strict";

  $(document).on('click', '.__A__Report_Inline_Edit', function() {
    var vl = $(this).text();
    if ("1"!== $(this).data('editing')) {
      $(this).data('editing', "1");
      $(this).html('<input class="__A__Report_Inline_Edit_Input" data-t="'+$(this).data('t')+'" data-day="'+$(this).data('day')+'" value="'+vl+'" type="text"><button class="__A__Report_Inline_Edit_OK" data-day="'+$(this).data('day')+'">OK</button>');
    }
  });

  $(document).on('click',  '.__A__Report_Inline_Edit_OK', function() {
    var vl = $('.__A__Report_Inline_Edit_Input[data-day="'+$(this).attr('data-day')+'"]');

    EnergyPlusAjax();

    jQuery.post( EnergyPlusGlobal.ajax_url, {
      _wpnonce:         jQuery('input[name=_wpnonce]').val(),
      _asnonce:         EnergyPlusGlobal._asnonce,
      _wp_http_referer: jQuery('input[name=_wp_http_referer]').val(),
      action:           "energyplus_ajax",
      segment:          'reports',
      do:               'manual',
      t:              vl.attr('data-t'),
      day:            vl.attr('data-day'),
      val:            vl.val()
    }, function(r) {
      EnergyPlusAjax('success', EnergyPlusGlobal.i18n.done);
      $('.__A__Report_Inline_Edit[data-day="'+vl.attr('data-day')+'"]').html(vl.val());
      $('.__A__Report_Inline_Edit[data-day="'+vl.attr('data-day')+'"]').data('editing', "0");
    }, 'json');
  });
});
