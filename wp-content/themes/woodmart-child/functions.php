<?php
/**
 * Woodmart Child - TeZapiekanki.
 *
 * Kod sklepu, który wcześniej siedział w functions.php motywu Woodmart
 * i zniknąłby przy aktualizacji motywu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Styl motywu potomnego, ładowany po stylach Woodmarta.
 * Starszy CSS sklepu jest nadal w Customizer → Dodatkowy CSS.
 */
function woodmart_child_enqueue_styles() {
	$file = get_stylesheet_directory() . '/style.css';

	// Wersja = data zmiany pliku, żeby po aktualizacji przeglądarki i LiteSpeed nie trzymały starego CSS.
	wp_register_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), (string) filemtime( $file ) );
	wp_style_add_data( 'child-style', 'path', $file );

	wp_enqueue_style( 'child-style' );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );

require_once get_stylesheet_directory() . '/inc/product-summary.php';
require_once get_stylesheet_directory() . '/inc/checkout.php';
require_once get_stylesheet_directory() . '/inc/product-gallery.php';
require_once get_stylesheet_directory() . '/inc/product-sticky-cart.php';
require_once get_stylesheet_directory() . '/inc/woodmart-popups.php';
