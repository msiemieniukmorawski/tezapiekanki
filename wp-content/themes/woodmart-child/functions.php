<?php
/**
 * Woodmart Child - TeZapiekanki.
 *
 * Shop code that used to live in the Woodmart theme's functions.php
 * and would have been lost on a theme update.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Child theme stylesheet, loaded after Woodmart's styles.
 * Older shop CSS is still in Customizer → Additional CSS.
 */
function woodmart_child_enqueue_styles() {
	$file = get_stylesheet_directory() . '/style.css';

	// Version = file modification time, so browsers and LiteSpeed don't keep stale CSS after an update.
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
