/**
 * Piny smaków ([tzp_flavor_pins], includes/class-flavor-pins.php).
 * Na telefonie lista jest jednym przewijanym wierszem: przewijamy ją tak, żeby bieżący smak był na środku,
 * i zaznaczamy klasami, z której strony jest jeszcze coś do przewinięcia (cieniowanie krawędzi w CSS).
 */
(function () {
	'use strict';

	function edges(list) {
		var max = list.scrollWidth - list.clientWidth;
		list.classList.toggle('has-more-start', list.scrollLeft > 4);
		list.classList.toggle('has-more-end', list.scrollLeft < max - 4);
	}

	function init() {
		document.querySelectorAll('.tzp-flavors__list').forEach(function (list) {
			if (list.dataset.tzpReady) {
				return;
			}
			list.dataset.tzpReady = '1';

			var current = list.querySelector('.is-current');

			if (current && list.scrollWidth > list.clientWidth) {
				var item = current.parentNode;
				list.scrollLeft = Math.max(0, item.offsetLeft - (list.clientWidth - item.offsetWidth) / 2);
			}

			edges(list);
			list.addEventListener('scroll', function () {
				edges(list);
			}, { passive: true });
			window.addEventListener('resize', function () {
				edges(list);
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
