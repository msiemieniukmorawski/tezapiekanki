<?php
/**
 * Pasek "Dodaj do koszyka" przy przewijaniu karty produktu.
 *
 * Pasek jest natywny z Woodmarta (opcje single_sticky_add_to_cart i mobile_single_sticky_add_to_cart, renderuje
 * go woodmart_sticky_single_add_to_cart() na końcu strony, niezależnie od layoutu Elementora). Na telefonie zostaje
 * natywny wygląd (ilość + przycisk, decyzja z 18.10). Tu tylko poprawka dla zestawów (WPC Bundles, typ woosb),
 * dla których przycisk w pasku nic nie robił: js/product-sticky-cart.js.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tzp_product_sticky_cart_assets' );

function tzp_product_sticky_cart_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'woodmart_get_opt' ) || ! woodmart_get_opt( 'single_sticky_add_to_cart' ) ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );

	if ( ! $product || ! $product->is_type( 'woosb' ) ) {
		return;
	}

	$file = get_stylesheet_directory() . '/js/product-sticky-cart.js';
	wp_enqueue_script( 'tzp-product-sticky-cart', get_stylesheet_directory_uri() . '/js/product-sticky-cart.js', array( 'jquery' ), (string) filemtime( $file ), true );
}
