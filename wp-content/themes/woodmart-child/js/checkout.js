/**
 * TeZapiekanki checkout (inc/checkout.php).
 *
 * Nothing here replaces WooCommerce or carrier plugin logic: the tiles are labels of the original shipping inputs,
 * the parcel locker button triggers the original map button (Apaczka or InPost PL), the coupon goes to the built-in apply_coupon endpoint.
 */
(function ($) {
  'use strict';

  if (typeof wc_checkout_params === 'undefined') {
    return;
  }

  /* ---------- Shipping methods: mark the tile as checked immediately (WooCommerce does the rest) ---------- */

  $(document).on('click', '.tzp-method', function () {
    $(this).addClass('is-checked').siblings('.tzp-method').removeClass('is-checked');
  });

  /* ---------- Parcel locker: the map and saving the point are handled by the carrier plugin ---------- */

  // The selected point is read from the hidden inputs the plugin appends to the end of form.checkout. Only these inputs survive
  // a summary refresh (update_checkout replaces the table together with the original "Wybrany paczkomat")
  // and they are what gets saved with the order: PHP takes the last input with a given name, so we read the last one too.
  function lastFieldValue(name) {
    var $fields = $('form.checkout').find('input[name="' + name + '"]');
    return $fields.length ? $.trim($fields.last().val() || '') : '';
  }

  function joinParts(parts, separator) {
    return $.grep(parts, function (part) { return !!part; }).join(separator);
  }

  // data-provider from inc/checkout.php (tzp_checkout_parcel_provider). buttons: original map buttons, the first existing one is used.
  var parcelProviders = {
    apaczka: {
      buttons: ['#apaczka_pl_geowidget_classic'],
      selected: function () {
        var code = lastFieldValue('apm_access_point_id');
        if (!code) {
          return null;
        }
        return {
          code: code,
          address: joinParts([lastFieldValue('apm_street'), joinParts([lastFieldValue('apm_postal_code'), lastFieldValue('apm_city')], ' ')], ', '),
          desc: lastFieldValue('apm_name')
        };
      }
    },
    // InPost PL (iLabs): button in the summary or, in "JS map button" mode, below the shipping method.
    inpost: {
      buttons: ['#easypack_show_geowidget', '#easypack_js_type_geowidget'],
      selected: function () {
        var code = lastFieldValue('parcel_machine_id');
        if (!code) {
          return null;
        }
        var point = { code: code, address: '', desc: lastFieldValue('parcel_machine_desc') };
        // The plugin keeps the address only in localStorage: "CODE<br>street<br>postcode city<br>description" or "street<br>postcode city".
        try {
          var stored = JSON.parse(window.localStorage.getItem('EasyPackPointObject') || 'null');
          if (stored && stored.pointName === code && stored.visiblePointData) {
            point.address = $.grep(String(stored.visiblePointData).split(/<br\s*\/?>/i), function (line) {
              line = $.trim(line);
              return line !== '' && line !== code && line !== point.desc;
            }).map($.trim).join(', ');
          }
        } catch (err) {
          point.address = '';
        }
        return point;
      }
    }
  };

  function parcelProvider() {
    return parcelProviders[$('.tzp-parcel').attr('data-provider')] || null;
  }

  $(document).on('click', '.tzp-parcel__button', function (e) {
    e.preventDefault();
    var provider = parcelProvider();
    if (!provider) {
      return;
    }
    for (var i = 0; i < provider.buttons.length; i++) {
      var native = document.querySelector(provider.buttons[i]);
      if (native) {
        native.click();
        return;
      }
    }
  });

  // Touches the DOM only when the state changes: the form observer also calls this function after its own changes.
  function syncParcel() {
    var $box = $('.tzp-parcel');
    var provider = $box.length ? parcelProvider() : null;
    var point = provider ? provider.selected() : null;
    var key = point ? [point.code, point.address, point.desc].join('|') : '';

    if ($box.length && $box.attr('data-selected') !== key) {
      var $selected = $box.find('.tzp-parcel__selected').empty();
      $box.attr('data-selected', key).toggleClass('is-selected', !!point);

      if (point) {
        $selected.append(
          $('<span class="tzp-parcel__label"></span>').text('Wybrany paczkomat'),
          $('<strong class="tzp-parcel__code"></strong>').text(point.code),
          point.address ? $('<span class="tzp-parcel__address"></span>').text(point.address) : null,
          point.desc && point.desc !== point.code && point.desc !== point.address ? $('<span class="tzp-parcel__desc"></span>').text(point.desc) : null
        );
      }
      $selected.prop('hidden', !point);
      $box.find('.tzp-parcel__button').text(point ? 'Zmień paczkomat' : 'Wybierz paczkomat');
    }

    // Parcel locker code also in the summary, below the shipping method.
    var $cell = $('.tzp-checkout-side .woocommerce-shipping-totals td').first();
    var $line = $cell.children('.tzp-summary-parcel');
    var text = point && $box.length ? 'Paczkomat ' + point.code : '';

    if (!text) {
      $line.remove();
    } else if (!$line.length) {
      $cell.append($('<div class="tzp-summary-parcel"></div>').text(text));
    } else if ($line.text() !== text) {
      $line.text(text);
    }
  }

  // The plugins change the selection in their map callbacks (append inputs to the form, replace text in the table).
  function observeParcel() {
    var form = document.querySelector('form.checkout');
    if (!form || form.__tzpObserved || typeof MutationObserver === 'undefined') {
      return;
    }
    form.__tzpObserved = true;

    var queued = false;
    new MutationObserver(function () {
      if (queued) {
        return;
      }
      queued = true;
      setTimeout(function () {
        queued = false;
        syncParcel();
      }, 50);
    }).observe(form, { subtree: true, childList: true, characterData: true });
  }

  /* ---------- Coupon code above "Łącznie" ---------- */

  // The summary table is replaced after every update_checkout, so we remember the field state.
  var coupon = { open: false, value: '', message: '' };

  function applyCoupon() {
    var $input = $('#tzp_coupon_code');
    var code = $.trim($input.val() || '');

    if (!code) {
      $input.trigger('focus');
      return;
    }

    var $box = $('.tzp-coupon__box').addClass('is-loading');

    $.ajax({
      type: 'POST',
      url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
      data: {
        security: wc_checkout_params.apply_coupon_nonce,
        coupon_code: code,
        billing_email: $('#billing_email').val() || ''
      },
      dataType: 'html'
    }).done(function (html) {
      var failed = /woocommerce-error/.test(html);
      coupon = { open: true, value: failed ? code : '', message: html };
      $(document.body).trigger('applied_coupon_in_checkout', [code]);
      $(document.body).trigger('update_checkout', { update_shipping_method: false });
    }).always(function () {
      $box.removeClass('is-loading');
    });
  }

  $(document).on('click', '.tzp-coupon__apply', function (e) {
    e.preventDefault();
    applyCoupon();
  });

  // Enter in the code field must not submit the whole order.
  $(document).on('keydown', '#tzp_coupon_code', function (e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
      e.preventDefault();
      applyCoupon();
    }
  });

  document.addEventListener('toggle', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('tzp-coupon__box')) {
      coupon.open = e.target.open;
    }
  }, true);

  function restoreCoupon() {
    var $box = $('.tzp-coupon__box');
    if (!$box.length) {
      return;
    }
    if (coupon.open) {
      $box.prop('open', true);
    }
    if (coupon.value) {
      $('#tzp_coupon_code').val(coupon.value);
    }
    if (coupon.message) {
      $box.find('.tzp-coupon__msg').html(coupon.message);
      coupon.message = '';
    }
  }

  /* ---------- Company invoice: company name and NIP fields behind a checkbox ---------- */

  function initInvoiceToggle() {
    var $company = $('#billing_nazwa_firmy_field');
    var $nip = $('#billing_numer_nip_field');

    if (!$company.length || $('#tzp_want_invoice').length) {
      return;
    }

    var hasData = $.trim($('#billing_nazwa_firmy').val() || '') !== '' || $.trim($('#billing_numer_nip').val() || '') !== '';
    var $toggle = $('<p class="form-row form-row-wide tzp-invoice-toggle"><label class="checkbox" for="tzp_want_invoice"><input type="checkbox" id="tzp_want_invoice" class="input-checkbox"> <span>Chcę fakturę na firmę</span></label></p>');
    var $fields = $company.add($nip);

    function set(on) {
      $fields.toggleClass('tzp-is-hidden', !on);
      if (!on) {
        $('#billing_nazwa_firmy, #billing_numer_nip').val('');
      }
    }

    $company.before($toggle);
    $toggle.find('input').prop('checked', hasData).on('change', function () {
      set(this.checked);
    });
    set(hasData);
  }

  /* ---------- Mobile: summary collapsed into a bar with the total ---------- */

  function initSummaryToggle() {
    var $side = $('.tzp-checkout-side');
    if (!$side.length || $side.children('.tzp-summary-toggle').length) {
      return;
    }

    var $btn = $('<button type="button" class="tzp-summary-toggle" aria-expanded="false"><span class="tzp-summary-toggle__label">Twoje zamówienie</span><span class="tzp-summary-toggle__total"></span></button>');
    $side.prepend($btn);
    $btn.on('click', function () {
      var open = !$side.hasClass('is-open');
      $side.toggleClass('is-open', open);
      $btn.attr('aria-expanded', open ? 'true' : 'false');
    });
  }

  function updateSummaryTotal() {
    // The first amount in "Łącznie" is the total; the VAT amount may follow ("zawiera 2,80 zł VAT").
    var total = $.trim($('.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount').first().text());
    $('.tzp-summary-toggle__total').text(total);
  }

  /* ---------- Startup and checkout refreshes ---------- */

  function refresh() {
    restoreCoupon();
    updateSummaryTotal();
    syncParcel();
    // InPost PL restores the point from localStorage in its own updated_checkout handler, so run again shortly after.
    setTimeout(syncParcel, 60);
  }

  $(function () {
    initInvoiceToggle();
    initSummaryToggle();
    observeParcel();
    refresh();
  });

  $(document.body).on('updated_checkout', function () {
    observeParcel();
    refresh();
  });
})(jQuery);
