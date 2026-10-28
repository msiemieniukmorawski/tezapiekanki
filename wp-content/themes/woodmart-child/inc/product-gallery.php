<?php
/**
 * Galeria na karcie produktu (layout Woodmarta 6480, widget galerii z klasą CSS "tzp-gallery").
 *
 * Układ robią ustawienia Woodmarta ("Bottom grid": duże pierwsze zdjęcie, pod nim 2 kolumny; karuzela na telefonie;
 * lightbox "popup"). js/product-gallery.js: przycisk "Zobacz więcej zdjęć" / "Zwiń zdjęcia" pod siatką przy więcej
 * niż 6 zdjęciach (desktop, tablet) i licznik "1 / 8" (telefon). Kafelki 4:3, zwinięcie do dużego + 4 kafelków
 * i wystające kolejne zdjęcie są w style.css (sekcja "Galeria produktu").
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tzp_product_gallery_assets' );

function tzp_product_gallery_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$file = get_stylesheet_directory() . '/js/product-gallery.js';
	wp_enqueue_script( 'tzp-product-gallery', get_stylesheet_directory_uri() . '/js/product-gallery.js', array(), (string) filemtime( $file ), true );
}
