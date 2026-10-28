/**
 * Checkout TeZapiekanki (inc/checkout.php).
 *
 * Nic tu nie zastępuje logiki WooCommerce ani wtyczek przewoźnika: kafelki to etykiety oryginalnych pól wysyłki,
 * przycisk paczkomatu uruchamia oryginalny przycisk mapy (Apaczka albo InPost PL), kupon idzie do wbudowanego endpointu apply_coupon.
 */
(function ($) {
  'use strict';

  if (typeof wc_checkout_params === 'undefined') {
    return;
  }

  /* ---------- Metody wysyłki: natychmiastowe zaznaczenie kafelka (resztę robi WooCommerce) ---------- */

  $(document).on('click', '.tzp-method', function () {
    $(this).addClass('is-checked').siblings('.tzp-method').removeClass('is-checked');
  });

  /* ---------- Paczkomat: mapę i zapis punktu obsługuje wtyczka przewoźnika ---------- */

  // Wybrany punkt czytamy z ukrytych pól, które wtyczka dopisuje na końcu form.checkout. Tylko te pola przetrwają
  // odświeżenie podsumowania (update_checkout podmienia tabelę razem z oryginalnym "Wybrany paczkomat")
  // i to one trafiają do zamówienia: PHP bierze ostatnie pole o danej nazwie, więc my też czytamy ostatnie.
  function lastFieldValue(name) {
    var $fields = $('form.checkout').find('input[name="' + name + '"]');
    return $fields.length ? $.trim($fields.last().val() || '') : '';
  }

  function joinParts(parts, separator) {
    return $.grep(parts, function (part) { return !!part; }).join(separator);
  }

  // data-provider z inc/checkout.php (tzp_checkout_parcel_provider). buttons: oryginalne przyciski mapy, pierwszy istniejący.
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
    // InPost PL (iLabs): przycisk w podsumowaniu albo, w trybie "przycisk mapy przez JS", pod metodą wysyłki.
    inpost: {
      buttons: ['#easypack_show_geowidget', '#easypack_js_type_geowidget'],
      selected: function () {
        var code = lastFieldValue('parcel_machine_id');
        if (!code) {
          return null;
        }
        var point = { code: code, address: '', desc: lastFieldValue('parcel_machine_desc') };
        // Adres wtyczka trzyma tylko w localStorage: "KOD<br>ulica<br>kod miasto<br>opis" albo "ulica<br>kod miasto".
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

  // Zmienia DOM tylko przy zmianie stanu: obserwator formularza wywołuje tę funkcję także po jej własnych zmianach.
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

    // Numer paczkomatu także w podsumowaniu, pod metodą wysyłki.
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

  // Wtyczki zmieniają wybór w swoich callbackach mapy (dopisują pola do formularza, podmieniają tekst w tabeli).
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

  /* ---------- Kod rabatowy nad "Łącznie" ---------- */

  // Tabela podsumowania jest podmieniana po każdym update_checkout, więc pamiętamy stan pola.
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

  // Enter w polu kodu nie może wysłać całego zamówienia.
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

  /* ---------- Faktura na firmę: pola firmy i NIP pod checkboxem ---------- */

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

  /* ---------- Telefon: podsumowanie zwinięte w pasek z kwotą ---------- */

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
    // Pierwsza kwota w "Łącznie" to suma; dalej może być kwota VAT ("zawiera 2,80 zł VAT").
    var total = $.trim($('.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount').first().text());
    $('.tzp-summary-toggle__total').text(total);
  }

  /* ---------- Start i odświeżenia checkoutu ---------- */

  function refresh() {
    restoreCoupon();
    updateSummaryTotal();
    syncParcel();
    // InPost PL odtwarza punkt z localStorage we własnym handlerze updated_checkout, więc jeszcze raz chwilę później.
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
