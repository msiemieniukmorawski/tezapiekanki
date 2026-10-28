/**
 * Pasek "Dodaj do koszyka" Woodmarta na karcie zestawu (WPC Bundles, typ woosb), inc/product-sticky-cart.php.
 *
 * Woodmart daje zestawom w pasku link, który przewija do formularza wariantów. Zestaw go nie ma, więc kliknięcie
 * nic nie robiło. Tutaj: dopóki zestaw nie jest skompletowany, przycisk "Wybierz smaki" przewija do listy smaków,
 * a po skompletowaniu "Dodaj do koszyka" klika oryginalny przycisk formularza (walidacja WPC i AJAX Woodmarta
 * działają bez zmian). Stan bierzemy z klasy woosb-disabled, którą WPC Bundles ustawia na przycisku formularza.
 */
(function ($) {
  'use strict';

  $(function () {
    var $sticky = $('.wd-sticky-btn-cart.wd-product-type-woosb .wd-sticky-add-to-cart');
    var mainButton = $('form.cart').not('.wd-sticky-btn form.cart').find('.single_add_to_cart_button').get(0);
    var $list = $('.woosb-wrap').first();

    if (!$sticky.length || !mainButton) {
      return;
    }

    var addText = $.trim(mainButton.textContent) || 'Dodaj do koszyka';

    function isReady() {
      return !mainButton.classList.contains('woosb-disabled');
    }

    function update() {
      $sticky.text(isReady() ? addText : 'Wybierz smaki');
    }

    update();
    $(document).on('woosb_check_ready', update);

    // Handler Woodmarta na tym przycisku kończy się bez efektu (brak formularza wariantów), ten robi właściwą akcję.
    $sticky.on('click', function (e) {
      e.preventDefault();

      if (isReady()) {
        mainButton.click();
        return;
      }

      if ($list.length) {
        // Nagłówek Woodmarta przykleja się dopiero przy przewijaniu w górę, więc zawsze odejmujemy jego wysokość.
        var header = document.querySelector('.whb-main-header');
        var offset = header ? header.offsetHeight : 0;

        // 50 px zapasu, żeby było widać też nagłówek "Skomponuj swój zestaw" nad listą.
        window.scrollTo({ top: Math.max(0, $list.offset().top - offset - 50), behavior: 'smooth' });
      }
    });
  });
})(jQuery);
