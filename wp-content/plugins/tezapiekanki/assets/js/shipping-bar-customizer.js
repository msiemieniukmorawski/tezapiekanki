/**
 * Podgląd na żywo tekstów belki w Wygląd → Dostosuj (pozostałe ustawienia odświeżają podgląd).
 */
(function (api) {
  'use strict';

  api('tzp_shipping_bar[text]', function (setting) {
    setting.bind(function (value) {
      document.querySelectorAll('.tzp-bar__text').forEach(function (el) {
        el.textContent = value;
      });
    });
  });

  api('tzp_shipping_bar[link_text]', function (setting) {
    setting.bind(function (value) {
      document.querySelectorAll('.tzp-bar__cta').forEach(function (el) {
        var text = el.querySelector('.tzp-bar__cta-text');
        if (text) {
          text.textContent = value;
        }
        el.hidden = !value.trim();
      });
    });
  });
})(wp.customize);
