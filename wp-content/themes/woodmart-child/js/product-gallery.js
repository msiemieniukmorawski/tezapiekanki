/**
 * Product gallery (inc/product-gallery.php).
 *
 * - Desktop and tablet: with more than 6 images, the large one + 4 tiles are visible (CSS hides the rest right away);
 *   the "Zobacz więcej zdjęć" button below the grid expands the rest downwards and collapses it back.
 * - Mobile: "1 / 8" counter in the carousel. The carousel is created by Woodmart (js/scripts/wc/productImagesGallery.js)
 *   on window load and only where it is enabled (here: mobile). The Swiper instance is in element.swiper.
 */
(function () {
  'use strict';

  var MAX_ALL = 6; // up to this many images, all are shown
  var SHOWN = 5; // with more: large + 4 tiles (in CSS :nth-child(n+6))
  var GRID_QUERY = '(min-width: 769px)';

  function galleries() {
    return document.querySelectorAll('.tzp-gallery .woocommerce-product-gallery__wrapper');
  }

  /* ---------- Desktop and tablet: "Zobacz więcej zdjęć" below the grid ---------- */

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Smooth grid height transition between the two states (expand downwards, collapse upwards).
  function animateHeight(el, from, to, done) {
    if (reducedMotion || from === to) {
      done();
      return;
    }

    el.style.overflow = 'hidden';
    el.style.height = from + 'px';
    el.getBoundingClientRect(); // forces a reflow before the transition starts
    el.style.transition = 'height 0.35s ease';
    el.style.height = to + 'px';

    var finished = false;
    var finish = function () {
      if (finished) {
        return;
      }
      finished = true;
      el.style.transition = '';
      el.style.height = '';
      el.style.overflow = '';
      done();
    };

    el.addEventListener('transitionend', function handler(e) {
      if (e.target === el && e.propertyName === 'height') {
        el.removeEventListener('transitionend', handler);
        finish();
      }
    });
    setTimeout(finish, 600); // in case transitionend never fires
  }

  function setupToggle(gallery) {
    var wrapper = gallery.closest('.woocommerce-product-gallery');
    var container = gallery.closest('.wd-gallery-images');
    var items = gallery.querySelectorAll('.wd-carousel-item');

    if (!wrapper || !container || items.length <= MAX_ALL || wrapper.querySelector('.tzp-gallery__toggle')) {
      return;
    }

    var button = document.createElement('button');
    var label = document.createElement('span');
    var icon = document.createElement('span');

    if (!gallery.id) {
      gallery.id = 'tzp-gallery-' + Math.random().toString(36).slice(2, 8);
    }

    button.type = 'button';
    button.className = 'tzp-gallery__toggle';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', gallery.id);
    icon.className = 'tzp-gallery__toggle-icon';
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(label);
    button.appendChild(icon);

    var setLabel = function (expanded) {
      label.textContent = expanded ? 'Zwiń zdjęcia' : 'Zobacz więcej zdjęć';
      button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    setLabel(false);

    button.addEventListener('click', function () {
      var expanded = wrapper.classList.contains('tzp-gallery--expanded');
      var from = container.offsetHeight;

      if (!expanded) {
        wrapper.classList.add('tzp-gallery--expanded');
        setLabel(true);
        animateHeight(container, from, container.scrollHeight, function () {});
        return;
      }

      // Collapsing: height down to the bottom edge of the last visible tile, then hide the rest.
      var to = Math.round(items[SHOWN - 1].getBoundingClientRect().bottom - container.getBoundingClientRect().top);
      setLabel(false);
      animateHeight(container, from, to, function () {
        wrapper.classList.remove('tzp-gallery--expanded');
        // The button moved up: if it went off screen, scroll back to it.
        if (button.getBoundingClientRect().top < 0) {
          button.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
        }
      });
    });

    container.insertAdjacentElement('afterend', button);
  }

  /* ---------- Mobile: counter ---------- */

  function setupCounter(gallery) {
    var swiper = gallery.swiper;
    var container = gallery.closest('.wd-gallery-images');
    var total = gallery.querySelectorAll('.wd-carousel-item').length;

    if (!swiper || !container || total < 2 || container.querySelector('.tzp-gallery__counter')) {
      return;
    }

    // Swiper announces slides to screen readers itself (a11y); the counter is visual only.
    var counter = document.createElement('div');
    counter.className = 'tzp-gallery__counter';
    counter.setAttribute('aria-hidden', 'true');

    var update = function () {
      counter.textContent = (swiper.realIndex + 1) + ' / ' + total;
    };

    update();
    swiper.on('slideChange', update);
    container.appendChild(counter);
  }

  function initCounters(attempt) {
    var waiting = false;

    galleries().forEach(function (gallery) {
      if (gallery.swiper) {
        setupCounter(gallery);
      } else if (gallery.closest('.wd-carousel-container:not(.wd-off-sm)') && !window.matchMedia(GRID_QUERY).matches) {
        waiting = true;
      }
    });

    // With "auto height" Woodmart also waits for images to load, so retry a few times briefly.
    if (waiting && attempt < 10) {
      setTimeout(function () {
        initCounters(attempt + 1);
      }, 200);
    }
  }

  function initMore() {
    if (window.matchMedia(GRID_QUERY).matches) {
      galleries().forEach(setupToggle);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMore);
  } else {
    initMore();
  }

  window.addEventListener('load', function () {
    setTimeout(function () {
      initCounters(0);
    }, 0);
  });
})();
