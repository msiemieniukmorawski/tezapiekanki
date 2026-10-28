<?php
/**
 * "Info praktyczne" block on the product page: 4 icons with captions below the "Dodaj do koszyka" button.
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/create-product-info-block.php --user=<login administratora>
 *
 * What it does (via WordPress and Elementor APIs, no SQL):
 * 1. Uploads the icons from assets/icons/*.png to the media library (once, recognised by the _tzp_icon meta).
 * 2. Creates or overwrites the Woodmart HTML Block (cms_block) "Info praktyczne" (meta _tzp_block = product-info):
 *    a container with 4 wd_infobox widgets. The client edits it later in Elementor (HTML Blocks menu).
 * 3. Inserts a Shortcode widget [html_block id="…"] into the product page layouts (6480 zapiekanki, 6533 bundles)
 *    right below the "Dodaj do koszyka" widget, with element cache disabled (block changes show immediately). A layout
 *    that already contains this shortcode is left unchanged.
 *
 * Icons: SVG sources in assets/icons/*.svg, 128x128 PNGs rendered from them (WordPress does not accept SVG in the
 * media library without an extra plugin).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

if ( ! class_exists( '\Elementor\Plugin' ) || ! post_type_exists( 'cms_block' ) ) {
	WP_CLI::error( 'Wymagany Elementor i Woodmart (typ wpisu cms_block).' );
}

if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_posts' ) ) {
	WP_CLI::error( 'Uruchom z --user=<administrator>.' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Layout => category of the product used as context when saving (Elementor then renders widgets to plain text,
// and product page widgets, e.g. the gallery, require the global $product).
$tzp_layouts = array(
	6480 => 'zapiekanki',
	6533 => 'zestawy-zapiekanek',
);

// Bold title with a short description below (decided 2026-10-19: "6 dni od dostawy" alone was unclear; since 2026-10-26, 7 days in the fridge).
$tzp_items = array(
	array( 'icon' => 'info-6-dni.png', 'title' => '7 dni świeżości', 'text' => 'w lodówce, liczone od dostawy' ),
	array( 'icon' => 'info-lodowka.png', 'title' => 'Trzymaj w lodówce', 'text' => 'zaraz po odbiorze paczki' ),
	array( 'icon' => 'info-zamrazanie.png', 'title' => 'Możesz zamrozić', 'text' => 'najlepiej od razu po odbiorze' ),
	array( 'icon' => 'info-termobox.png', 'title' => 'Termobox', 'text' => 'z wkładami chłodzącymi' ),
);

$tzp_id = static function () {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
};

$tzp_px = static function ( $size ) {
	return array(
		'unit'  => 'px',
		'size'  => $size,
		'sizes' => array(),
	);
};

/*
 * 1. Icons.
 */
$tzp_icon = static function ( $file ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_tzp_icon', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = TZP_DIR . 'assets/icons/' . $file;
	$tmp    = wp_tempnam( $file );
	copy( $source, $tmp );

	$id = media_handle_sideload(
		array(
			'name'     => 'tzp-' . $file,
			'tmp_name' => $tmp,
		),
		0,
		'Ikona: ' . $file
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( 'Ikona ' . $file . ': ' . $id->get_error_message() );
	}

	update_post_meta( $id, '_tzp_icon', $file );
	update_post_meta( $id, '_wp_attachment_image_alt', '' );

	return (int) $id;
};

/*
 * 2. HTML Block.
 */
$infoboxes = array();

foreach ( $tzp_items as $item ) {
	$icon_id = $tzp_icon( $item['icon'] );

	$infoboxes[] = array(
		'id'         => $tzp_id(),
		'elType'     => 'widget',
		'widgetType' => 'wd_infobox',
		'settings'   => array(
			'icon_type'                      => 'icon',
			'image'                          => array(
				'id'  => $icon_id,
				'url' => wp_get_attachment_url( $icon_id ),
			),
			'image_size'                     => 'custom',
			'image_custom_dimension'         => array(
				'width'  => '34',
				'height' => '34',
			),
			'info_box_inline'                => 'yes',
			'alignment'                      => 'left',
			'image_alignment'                => 'left',
			'image_vertical_alignment'       => 'middle',
			'woodmart_color_scheme'          => 'dark',
			'title'                          => $item['title'],
			'content'                        => '<p>' . esc_html( $item['text'] ) . '</p>',
			// Without empty values the widget inserts a sample subtitle and a "Read more" button, and a "#" link turns the
			// whole tile into a link to the top of the page.
			'subtitle'                       => '',
			'btn_text'                       => '',
			'link'                           => array(
				'url'               => '',
				'is_external'       => '',
				'nofollow'          => '',
				'custom_attributes' => '',
			),
			'custom_text_color'              => '#666666',
			'content_typography_typography'  => 'custom',
			'content_typography_font_size'   => $tzp_px( 13 ),
			'content_typography_line_height' => array(
				'unit'  => 'em',
				'size'  => 1.35,
				'sizes' => array(),
			),
			'title_size'                     => 'small',
			'title_typography_typography'    => 'custom',
			'title_typography_font_size'     => $tzp_px( 14 ),
			'title_typography_font_weight'   => '600',
			'title_typography_line_height'   => array(
				'unit'  => 'em',
				'size'  => 1.3,
				'sizes' => array(),
			),
			'_element_width'                 => 'initial',
			'_element_custom_width'          => array(
				'unit'  => '%',
				'size'  => 47,
				'sizes' => array(),
			),
			'_element_custom_width_tablet'   => array(
				'unit'  => '%',
				'size'  => 47,
				'sizes' => array(),
			),
			'_element_custom_width_mobile'   => array(
				'unit'  => '%',
				'size'  => 46,
				'sizes' => array(),
			),
		),
		'elements'   => array(),
	);
}

$block_elements = array(
	array(
		'id'       => $tzp_id(),
		'elType'   => 'container',
		'settings' => array(
			'content_width'  => 'full',
			'flex_direction' => 'row',
			'flex_wrap'      => 'wrap',
			'flex_gap'       => array(
				'unit'     => 'px',
				'size'     => 10,
				'column'   => '16',
				'row'      => '10',
				'isLinked' => false,
			),
			// Tighter spacing inside the infoboxes (icon-text, title-description): woodmart-child/style.css, "Info praktyczne" section.
			// On a container the CSS class setting is "css_classes" (on widgets "_css_classes").
			'css_classes'    => 'tzp-info',
			'padding'        => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '0',
				'bottom'   => '0',
				'left'     => '0',
				'isLinked' => true,
			),
		),
		'elements' => $infoboxes,
		'isInner'  => false,
	),
);

$documents = \Elementor\Plugin::$instance->documents;

$block = get_posts(
	array(
		'post_type'      => 'cms_block',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_tzp_block', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => 'product-info', // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);

if ( $block ) {
	$block_id = (int) $block[0];
	WP_CLI::log( 'Nadpisuję HTML Block ' . $block_id . '.' );
} else {
	$block_id = wp_insert_post(
		array(
			'post_type'   => 'cms_block',
			'post_title'  => 'Info praktyczne (karta produktu)',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => 'wp-post',
				'_tzp_block'               => 'product-info',
			),
		),
		true
	);

	if ( is_wp_error( $block_id ) ) {
		WP_CLI::error( 'HTML Block: ' . $block_id->get_error_message() );
	}
}

$documents->get( $block_id, false )->save( array( 'elements' => $block_elements ) );

/*
 * 3. Product page layouts.
 */
$shortcode = sprintf( '[html_block id="%d"]', $block_id );

$widget = array(
	'id'         => $tzp_id(),
	'elType'     => 'widget',
	'widgetType' => 'shortcode',
	'settings'   => array(
		'shortcode'      => $shortcode,
		// "yes" = element rendered on every view, bypassing the Elementor element cache (24 h).
		'_element_cache' => 'yes',
		'_css_classes'   => 'tzp-product-info',
	),
	'elements'   => array(),
);

$contains = static function ( $elements ) use ( &$contains, $block_id ) {
	foreach ( $elements as $el ) {
		if ( isset( $el['settings']['shortcode'] ) && preg_match( '/html_block\s+id="?' . $block_id . '"?/', $el['settings']['shortcode'] ) ) {
			return true;
		}
		if ( ! empty( $el['elements'] ) && $contains( $el['elements'] ) ) {
			return true;
		}
	}
	return false;
};

/**
 * Inserts the block below the "Dodaj do koszyka" widget. When its container lays elements out in a row (e.g. price next
 * to the button on mobile), the block goes into a new container below that container, with the same spacing.
 */
$insert = static function ( array &$elements ) use ( &$insert, $widget, $tzp_id ) {
	foreach ( $elements as $i => &$el ) {
		if ( empty( $el['elements'] ) ) {
			continue;
		}

		foreach ( $el['elements'] as $j => $child ) {
			if ( 'wd_single_product_add_to_cart' !== ( $child['widgetType'] ?? '' ) ) {
				continue;
			}

			$s     = $el['settings'] ?? array();
			$is_row = 'row' === ( $s['flex_direction'] ?? '' ) || 'row' === ( $s['flex_direction_tablet'] ?? '' ) || 'row' === ( $s['flex_direction_mobile'] ?? '' );

			if ( ! $is_row ) {
				array_splice( $el['elements'], $j + 1, 0, array( $widget ) );
				return 'widget';
			}

			$box = array(
				'id'       => $tzp_id(),
				'elType'   => 'container',
				'settings' => array( 'content_width' => 'full' ),
				'elements' => array( $widget ),
				'isInner'  => true,
			);
			foreach ( $s as $key => $value ) {
				if ( preg_match( '/^(padding|margin)(_tablet|_mobile)?$/', $key ) ) {
					$box['settings'][ $key ] = $value;
				}
			}

			array_splice( $elements, $i + 1, 0, array( $box ) );
			return 'container';
		}

		$done = $insert( $el['elements'] );
		if ( $done ) {
			return $done;
		}
	}

	return false;
};

$tzp_product_context = static function ( $category ) {
	$ids = wc_get_products(
		array(
			'category' => array( $category ),
			'status'   => 'publish',
			'limit'    => 1,
			'return'   => 'ids',
		)
	);

	if ( ! $ids ) {
		return false;
	}

	$GLOBALS['post'] = get_post( $ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $GLOBALS['post'] );
	$GLOBALS['product'] = wc_get_product( $ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride

	return true;
};

foreach ( $tzp_layouts as $layout_id => $category ) {
	$document = $documents->get( $layout_id, false );

	if ( ! $document ) {
		WP_CLI::warning( 'Brak layoutu ' . $layout_id . '.' );
		continue;
	}

	$elements = $document->get_elements_data();

	if ( $contains( $elements ) ) {
		WP_CLI::log( 'Layout ' . $layout_id . ': blok już jest, bez zmian.' );
		continue;
	}

	$how = $insert( $elements );

	if ( ! $how ) {
		WP_CLI::warning( 'Layout ' . $layout_id . ': brak widgetu "Dodaj do koszyka", pomijam.' );
		continue;
	}

	if ( ! $tzp_product_context( $category ) ) {
		WP_CLI::warning( 'Layout ' . $layout_id . ': brak produktu z kategorii ' . $category . ' do zapisu, pomijam.' );
		continue;
	}

	$document->save( array( 'elements' => $elements ) );
	wp_reset_postdata();
	WP_CLI::log( 'Layout ' . $layout_id . ': wstawiony (' . ( 'container' === $how ? 'nowy kontener pod przyciskiem' : 'widget pod przyciskiem' ) . ').' );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );

WP_CLI::success( 'HTML Block ' . $block_id . ' "Info praktyczne" gotowy, shortcode: ' . $shortcode );
