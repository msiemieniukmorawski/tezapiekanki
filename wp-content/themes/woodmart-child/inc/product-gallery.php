<?php
/**
 * Gallery on the product page (Woodmart layout 6480, gallery widget with CSS class "tzp-gallery").
 *
 * The layout comes from Woodmart settings ("Bottom grid": large first image, 2 columns below; carousel on mobile;
 * "popup" lightbox). js/product-gallery.js: "Zobacz więcej zdjęć" / "Zwiń zdjęcia" button below the grid when there
 * are more than 6 images (desktop, tablet) and a "1 / 8" counter (mobile). 4:3 tiles, collapsing to the large image + 4 tiles
 * and the peeking next image are in style.css (section "Product gallery").
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
