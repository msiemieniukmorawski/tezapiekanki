<?php
/**
 * Removes the "X osób ogląda ten produkt" counter from product pages (client's decision, 2026-10-26). Woodmart
 * generated the number at random ("fake data" option, 1-27).
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/remove-visitor-counter.php --user=<login>          (preview)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/remove-visitor-counter.php apply --user=<login>    (save)
 *
 * What it does (via Elementor and WordPress APIs, no SQL):
 * 1. Removes the "Visitor counter" widget (wd_single_product_visitor_counter) from all product page layouts.
 *    The widget shows the counter even with the option disabled, so disabling the option alone is not enough.
 *    A container left empty after the removal is removed too. A copy of _elementor_data is saved before writing
 *    to wp-content/uploads/tzp-backup/.
 * 2. Disables the Woodmart option counter_visitor_enabled (counter below the form in non-Elementor templates and the
 *    AJAX requests counting visits).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	WP_CLI::error( 'Wymagany Elementor.' );
}

if ( ! current_user_can( 'edit_posts' ) ) {
	WP_CLI::error( 'Uruchom z --user=<administrator>.' );
}

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$tzp_remove = static function ( array &$els ) use ( &$tzp_remove ) {
	$count = 0;
	foreach ( array_keys( $els ) as $i ) {
		if ( 'wd_single_product_visitor_counter' === ( $els[ $i ]['widgetType'] ?? '' ) ) {
			unset( $els[ $i ] );
			$count++;
			continue;
		}
		if ( ! empty( $els[ $i ]['elements'] ) ) {
			$removed = $tzp_remove( $els[ $i ]['elements'] );
			$count  += $removed;
			if ( $removed && ! $els[ $i ]['elements'] && 'container' === ( $els[ $i ]['elType'] ?? '' ) ) {
				unset( $els[ $i ] );
			}
		}
	}
	$els = array_values( $els );
	return $count;
};

// On save Elementor renders widgets to plain text, and product page widgets require the global $product.
// Context: a product from the category in the layout conditions, or any product when there is no such condition.
$tzp_product_context = static function ( $layout_id ) {
	$cats = array();
	foreach ( (array) get_post_meta( $layout_id, 'wd_layout_conditions', true ) as $condition ) {
		if ( 'product_cat' === ( $condition['condition_type'] ?? '' ) && 'include' === ( $condition['condition_comparison'] ?? '' ) ) {
			$term = get_term( (int) $condition['condition_query'], 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cats[] = $term->slug;
			}
		}
	}

	$query = array(
		'status' => 'publish',
		'limit'  => 1,
		'return' => 'ids',
	);
	if ( $cats ) {
		$query['category'] = $cats;
	}

	$ids = wc_get_products( $query );
	if ( ! $ids ) {
		return false;
	}

	$GLOBALS['post'] = get_post( $ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $GLOBALS['post'] );
	$GLOBALS['product'] = wc_get_product( $ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	return true;
};

$tzp_layouts = get_posts(
	array(
		'post_type'      => 'woodmart_layout',
		'post_status'    => 'any',
		'meta_key'       => 'wd_layout_type', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => 'single_product', // phpcs:ignore WordPress.DB.SlowDBQuery
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);

$tzp_dir = wp_upload_dir()['basedir'] . '/tzp-backup';
$total   = 0;

foreach ( $tzp_layouts as $layout_id ) {
	$document = \Elementor\Plugin::$instance->documents->get( $layout_id, false );
	if ( ! $document ) {
		continue;
	}

	$elements = $document->get_elements_data();
	$count    = $tzp_remove( $elements );
	$total   += $count;
	WP_CLI::log( sprintf( 'Layout %d %s: %d licznik(ów)', $layout_id, get_the_title( $layout_id ), $count ) );

	if ( ! $tzp_apply || ! $count ) {
		continue;
	}

	if ( ! $tzp_product_context( $layout_id ) ) {
		WP_CLI::warning( 'Layout ' . $layout_id . ': brak produktu do zapisu, pomijam.' );
		continue;
	}

	wp_mkdir_p( $tzp_dir );
	file_put_contents( $tzp_dir . '/elementor-data-' . $layout_id . '-' . gmdate( 'Ymd-His' ) . '.json', get_post_meta( $layout_id, '_elementor_data', true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$document->save( array( 'elements' => $elements ) );
	wp_reset_postdata();
}

$tzp_options = get_option( 'xts-woodmart-options' );
$tzp_enabled = is_array( $tzp_options ) && ! empty( $tzp_options['counter_visitor_enabled'] );
WP_CLI::log( 'Opcja Woodmarta counter_visitor_enabled: ' . ( $tzp_enabled ? 'włączona' : 'wyłączona' ) );

if ( ! $tzp_apply ) {
	WP_CLI::success( ( $total + (int) $tzp_enabled ) . ' zmian do zrobienia (podgląd). Zapis: dodaj argument "apply".' );
	return;
}

if ( $tzp_enabled ) {
	$tzp_options['counter_visitor_enabled'] = '0';
	update_option( 'xts-woodmart-options', $tzp_options );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );
WP_CLI::success( 'Licznik usunięty z ' . $total . ' miejsc, opcja wyłączona.' );
