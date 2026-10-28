/**
 * Shipping bar: close until the end of the day.
 * The popup is opened by the theme (wd_popup with a .tzp-bar-link selector trigger attached by the plugin); here only
 * closing and GTM events.
 */
(function () {
  'use strict';

  var bar = document.querySelector('.tzp-bar');
  if (!bar) {
    return;
  }

  var key = bar.getAttribute('data-key') || 'tzp_bar_closed';
  var tz = bar.getAttribute('data-tz');

  // Same date as in the <head> script (Shipping_Bar::head_script), including the fallback.
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

    // Collapse the height instead of a content jump.
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
