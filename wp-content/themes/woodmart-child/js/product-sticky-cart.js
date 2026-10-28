/**
 * Woodmart's sticky "Add to cart" bar on the bundle product page (WPC Bundles, type woosb), inc/product-sticky-cart.php.
 *
 * Woodmart gives bundles a bar link that scrolls to the variations form. Bundles don't have one, so clicking
 * did nothing. Here: until the bundle is complete, the "Wybierz smaki" button scrolls to the flavour list,
 * and once complete, "Dodaj do koszyka" clicks the original form button (WPC validation and Woodmart's AJAX
 * work unchanged). The state comes from the woosb-disabled class that WPC Bundles sets on the form button.
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

    // Woodmart's handler on this button ends without effect (no variations form); this one does the actual action.
    $sticky.on('click', function (e) {
      e.preventDefault();

      if (isReady()) {
        mainButton.click();
        return;
      }

      if ($list.length) {
        // Woodmart's header only sticks when scrolling up, so always subtract its height.
        var header = document.querySelector('.whb-main-header');
        var offset = header ? header.offsetHeight : 0;

        // 50 px extra, so the "Skomponuj swój zestaw" heading above the list is visible too.
        window.scrollTo({ top: Math.max(0, $list.offset().top - offset - 50), behavior: 'smooth' });
      }
    });
  });
})(jQuery);
