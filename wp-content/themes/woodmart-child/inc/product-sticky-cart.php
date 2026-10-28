<?php
/**
 * Sticky "Add to cart" bar shown while scrolling the product page.
 *
 * The bar is Woodmart's native one (options single_sticky_add_to_cart and mobile_single_sticky_add_to_cart, rendered
 * by woodmart_sticky_single_add_to_cart() at the end of the page, independently of the Elementor layout). On mobile it keeps
 * the native look (quantity + button, decided 2026-10-18). This file only fixes bundles (WPC Bundles, type woosb),
 * for which the bar button did nothing: js/product-sticky-cart.js.
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
