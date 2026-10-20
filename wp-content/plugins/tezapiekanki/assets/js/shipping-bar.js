/**
 * Belka o wysyłce: zamknięcie do końca dnia.
 * Popup otwiera motyw (wd_popup z wyzwalaczem na selektor .tzp-bar-link, dopinanym przez wtyczkę), tu tylko
 * zamykanie i zdarzenia dla GTM.
 */
(function () {
  'use strict';

  var bar = document.querySelector('.tzp-bar');
  if (!bar) {
    return;
  }

  var key = bar.getAttribute('data-key') || 'tzp_bar_closed';
  var tz = bar.getAttribute('data-tz');

  // Ta sama data co w skrypcie w <head> (Shipping_Bar::head_script), łącznie z wariantem awaryjnym.
  function today() {
    try {
      var o = {};
      new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' })
        .formatToParts(new Date())
        .forEach(function (x) { o[x.type] = x.value; });
      return o.year + '-' + o.month + '-' + o.day;
    } catch (e) {
      return new Date().toDateString();
    }
  }

  function track(action) {
    if (window.dataLayer && typeof window.dataLayer.push === 'function') {
      window.dataLayer.push({ event: 'tzp_shipping_bar', tzp_bar_action: action });
    }
  }

  function hide() {
    document.documentElement.classList.add('tzp-bar-off');
    bar.classList.remove('tzp-bar--closing');
    bar.style.height = '';
  }

  bar.querySelector('.tzp-bar__close').addEventListener('click', function () {
    try {
      localStorage.setItem(key, today());
    } catch (e) {}

    track('close');

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      hide();
      return;
    }

    // Zwinięcie wysokości zamiast skoku treści.
    bar.style.height = bar.offsetHeight + 'px';
    void bar.offsetHeight;
    bar.classList.add('tzp-bar--closing');
    bar.style.height = '0px';
    setTimeout(hide, 260);
  });

  var link = bar.querySelector('a.tzp-bar__link');
  if (link) {
    link.addEventListener('click', function () {
      track(link.classList.contains('tzp-bar-link') ? 'popup' : 'link');
    });
  }
})();
