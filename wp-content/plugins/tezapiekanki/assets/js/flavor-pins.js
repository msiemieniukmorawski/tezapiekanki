/**
 * Flavor pins ([tzp_flavor_pins], includes/class-flavor-pins.php).
 * On phones the list is a single scrollable row: we scroll it so the current flavor is centered,
 * and mark with classes on which side there is more to scroll (edge shading in CSS).
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
