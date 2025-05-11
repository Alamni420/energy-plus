jQuery(document).ready(function() {
  "use strict";

  detectHash(EnergyPlusGlobal._admin_url + "post.php?post=HASH&action=edit");

  jQuery("body").on( "click", '.__A__Item.btnA', function() {

  setTimeout(function() {
    var pauseWarn = false;
    jQuery(".__A__Item.btnA" ).each(function( index ) {
      if (! jQuery( this ).hasClass('collapsed') ) {
        pauseWarn = true;
      }
    });

    if (pauseWarn) {
      jQuery('.__A__Paused').removeClass('d-none');
    } else {
      jQuery('.__A__Paused').addClass('d-none');
    }
  }, 1000);

  });


  window.eiBeepVar = null;
  var eiBeep = function() {
    var beep = false;
    jQuery(".__A__Item.btnA" ).each(function( index ) {
      if (jQuery.inArray( 'wc-'+jQuery( this ).attr('rel-status'), EnergyPlusGlobal.reactors_tweaks_order_notify ) > -1) {
        beep = true;
        jQuery( this ).attr('rel-beeping', 'true');
      } else {
        jQuery( this ).attr('rel-beeping', 'false');
      }
    });

    clearInterval(window.eiBeepVar);

    if (beep) {
      console.log('beeping');
      window.eiBeepVar = setInterval(function() {
        var  audio = new Audio(EnergyPlusGlobal.reactors_tweaks_order_beep);

        audio.preload = 'auto';
        audio.pause();
        audio.muted = true;
        audio.volume = 0.2;
        audio.muted = false;
        audio.play();
      }, 10000);

    }

  };

  function eiRefreshOrders() {

    setTimeout(function() {

      var doNotUpdate = false;
      jQuery(".__A__Item.btnA" ).each(function( index ) {
        if (! jQuery( this ).hasClass('collapsed') ) {
          doNotUpdate = true;
        }
      });

      if (! jQuery('.__A__Searching').hasClass('closed')) {
        doNotUpdate = true;
      }

      eiBeep();

      if (doNotUpdate) {
        eiRefreshOrders();
        return  false;
      }

      jQuery.post( EnergyPlusGlobal.ajax_url, {
        _wpnonce:  jQuery('input[name=_wpnonce]').val(),
        _wp_http_referer:  jQuery('input[name=_wp_http_referer]').val(),
        _asnonce:  EnergyPlusGlobal._asnonce,
        action:  "energyplus_ajax",
        segment:  getUrlParam('segment', ''),
        do:  'search',
        q:  '',
        status:  getUrlParam('status', ''),
        extra:  getUrlParam('status', ''),
        order_refresh: true
      }, function(r) {
        jQuery('.__A__Search_Input').removeClass('loading');
        jQuery(".__A__Container").html(r).addClass('__A__Ajax_Response');
        eiBeep();
      }).fail(function(xhr, status, error) {
        EnergyPlusAjax();
        EnergyPlusAjax('error', 'Connection Error: Please refresh the page');
      });

      eiRefreshOrders();

    }, (parseInt(EnergyPlusGlobal.reactors_tweaks_order_refresh)*1000));
  }

  if (0 < parseInt(EnergyPlusGlobal.reactors_tweaks_order_refresh)) {
    eiBeep();
    eiRefreshOrders();
  }

  jQuery("body").on( "click", '.__A__Ajax_Btn_SP',function(e) {
    e.stopPropagation();
    e.preventDefault();

    if (window.isMobile) {
      if (jQuery('body').hasClass('energyplus-half')) {
        window.location = EnergyPlusGlobal._admin_url + 'admin.php?page=energyplus&segment=frame&in=' + encodeURIComponent(jQuery(this).attr('href')) + "&_asnonce="+ EnergyPlusGlobal._asnonce_notifications;
        return false;
      } else {
        window.location = jQuery(this).attr('href');
        return false;
      }
    }

    if (jQuery(this).attr('data-hash') && jQuery(this).attr('data-hash').length>0) {
      window.location.hash = jQuery(this).attr('data-hash');
    }

    window.trigGlobal.slideReveal("show");
    jQuery("#inbrowser").attr("src", jQuery(this).attr('href'));
    jQuery('#inbrowser').on("load", function() {
      jQuery("#inbrowser--loading").removeClass('d-flex').addClass('d-none');
      jQuery(".__A__Trig_Close").removeClass('d-none');
      jQuery("#inbrowser").show();
    });
  });



  jQuery("body").on( "click", ".__A__Ajax_Button", function(e) {
    e.preventDefault();

    EnergyPlusAjax();

    var t = jQuery('#item_'+jQuery(this).attr('data-id'));
    var status = jQuery(this).data('status').replace(/wc-/, '');
    var text = jQuery(this).data('text');

    jQuery.post( EnergyPlusGlobal.ajax_url, {
      _wpnonce: jQuery('input[name=_wpnonce]').val(),
      _asnonce: EnergyPlusGlobal._asnonce,
      _wp_http_referer: jQuery('input[name=_wp_http_referer]').val(),
      action: "energyplus_ajax",
      segment: 'orders',
      do: jQuery(this).data('do'),
      id: jQuery(this).data('id'),
      status: status
    }, function(r) {
      if (1 === r.status) {
        jQuery.each(r.success, function(i, item) {
          jQuery('#item_' + item).attr('rel-status', status);
          jQuery('.energyplus-orders--item-badge > span', '#item_' + item).removeClass().addClass('orderstatusx text-'+status);
          jQuery('.energyplus-orders--item-badge > span > ', '#item_' + item).removeClass().addClass('bg-custom bg-'+status);
          jQuery('.energyplus-orders--item-badge', '#item_' + item).html('<span class="orderstatusx text-'+status+'"><span class="bg-custom bg-'+status+'" aria-hidden="true"></span><br>'+text+'</span>');
          if ('trash' === status || 'restore' === status || 'deleteforever' === status) {
            jQuery('#item_' + item).hide('slow');
          }  else {
            jQuery('#item_' + item).removeClass('__A__ItemChecked');
          }
        });
        eiBeep();
        EnergyPlusAjax('success', EnergyPlusGlobal.i18n.done);

      } else {
        EnergyPlusAjax('error', r.error);
      }
    }, 'json');

  });

  jQuery("body").on( "click",  ".__A__Bulk_Do",function() {

    EnergyPlusAjax();

    var sThisVal = '',
    sList = "",
    status = jQuery(this).data('status');

    jQuery('.__A__Checkbox').each(function () {
      sThisVal = jQuery(this).attr('data-id');
      if (this.checked) {
        sList += (sList === "" ? sThisVal : "," + sThisVal);
      }
    });

    jQuery.post( EnergyPlusGlobal.ajax_url, {
      _wpnonce: jQuery('input[name=_wpnonce]').val(),
      _asnonce: EnergyPlusGlobal._asnonce,
      _wp_http_referer: jQuery('input[name=_wp_http_referer]').val(),
      action: "energyplus_ajax",
      segment: 'orders',
      do: jQuery(this).data('do'),
      id: sList,
      status: status
    }, function(r) {
      if (1 === r.status) {
        jQuery.each(r.success, function(i, item) {
          jQuery('.energyplus-orders--item-badge > span', '#item_' + item).html(status).removeClass().addClass('badge badge-pill badge-'+status);
          if ('trash' === status || 'restore' === status || 'deleteforever' === status) {
            jQuery('#item_' + item).hide('slow').remove();
          }  else {
            jQuery('#item_' + item).removeClass('__A__ItemChecked');
          }
        });

        sList = '';

        EnergyPlusAjax('success', EnergyPlusGlobal.i18n.done);
      } else {
        EnergyPlusAjax('error', r.error);
      }
    }, 'json');

  });

  jQuery("body").on( "click", ".__A__Checkbox",function() {
    if ( 0 === jQuery(".__A__Checkbox:checked").length )  {
      jQuery(".__A__Bulk").hide();
    } else {
      jQuery(".__A__Bulk").show();
      jQuery(".__A__Item.btnA").addClass('collapsed').attr('aria-expanded', false);
      jQuery(".__A__Item.btnA .collapse").removeClass('show');
      jQuery('.__A__Checkbox_Hidden').show();
    }
  });

  jQuery("body").on( "click", ".__A__CheckAll",function() {
    if (this.checked) {
      jQuery(".__A__Bulk").show();
    } else {
      jQuery(".__A__Bulk").hide();
    }

    jQuery(".__A__Checkbox").addClass('__A__NoHide').prop('checked', this.checked);
    jQuery(".__A__CheckAll").prop('checked', this.checked);
  });

});
