<?php
/**
 * Shortens the FAQ in product descriptions to flavour-specific questions; general questions live on the "Najczęstsze pytania" page.
 *
 * Usage (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/shorten-product-faq.php           (preview, saves nothing)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/shorten-product-faq.php apply     (save, with a backup of descriptions)
 *
 * Kept: questions about freshness after delivery, shipping fresh, freezing, parties (usually 4). Removed:
 * - "Czy mogę dodać do zapiekanki dodatkowe składniki?" (the shop has no such option, the answer promised add-ons),
 * - "Jak wygląda proces pakowania i transportu?", "Czy mogę zamówić różne smaki…?" (general, they are on the FAQ page).
 * "1. " numbering in questions is dropped (the collapsible list does not need it), "Zapiekanka Szefa – na ostro" → "na ostro".
 * Applies to all products with an FAQ section (including hidden bundle copies). Backup of descriptions before saving:
 * wp-content/uploads/tzp-backup/products-faq-<date>.json.
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );
$tzp_keep  = '/(śwież|mroż|mroz|imprez|spotkan)/iu';
$tzp_drop  = '/(dodatkowe składniki|pakowania i transportu|różne smaki)/iu';

$tzp_shorten = static function ( $html, &$log ) use ( $tzp_keep, $tzp_drop ) {
	if ( ! preg_match( '/<h2[^>]*>(?:(?!<\/h2>).)*?(?:FAQ|pytania)(?:(?!<\/h2>).)*<\/h2>/isu', $html, $heading, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$start   = $heading[0][1] + strlen( $heading[0][0] );
	$rest    = substr( $html, $start );
	$end     = preg_match( '/<h2[\s>]/i', $rest, $next, PREG_OFFSET_CAPTURE ) ? $next[0][1] : strlen( $rest );
	$section = substr( $rest, 0, $end );
	$parts   = preg_split( '/(<h3[^>]*>.*?<\/h3>)/isu', $section, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out     = $parts[0];

	for ( $i = 1, $count = count( $parts ); $i < $count; $i += 2 ) {
		$question = trim( wp_strip_all_tags( $parts[ $i ] ) );
		$answer   = $parts[ $i + 1 ] ?? '';
		$keep     = preg_match( $tzp_keep, $question ) && ! preg_match( $tzp_drop, $question );

		$question = preg_replace( '/^\d+\.\s*/u', '', $question );
		$log[]    = ( $keep ? '  + ' : '  - ' ) . $question;

		if ( $keep ) {
			$out .= '<h3>' . esc_html( $question ) . '</h3>' . $answer;
		}
	}

	$new = substr( $html, 0, $start ) . $out . substr( $rest, $end );

	// Old flavour name in the "na ostro" FAQ.
	return preg_replace( '/(zapiekank\p{L}*) Szefa\s*[–-]\s*na ostro/iu', '$1 na ostro', $new );
};

$tzp_ids = wc_get_products(
	array(
		'limit'  => -1,
		'status' => array( 'publish', 'private', 'draft' ),
		'return' => 'ids',
	)
);

$tzp_backup  = array();
$tzp_changed = 0;

foreach ( $tzp_ids as $id ) {
	$product = wc_get_product( $id );
	$log     = array();
	$new     = $tzp_shorten( $product->get_description(), $log );

	if ( null === $new || $new === $product->get_description() ) {
		continue;
	}

	$tzp_changed++;
	WP_CLI::log( sprintf( '%6d %s%s', $id, $product->get_name(), $product->is_visible() ? '' : ' (ukryty)' ) );
	WP_CLI::log( implode( "\n", $log ) );

	if ( $tzp_apply ) {
		$tzp_backup[ $id ] = $product->get_description();
		$product->set_description( $new );
		$product->save();
	}
}

if ( $tzp_apply && $tzp_backup ) {
	$dir = wp_upload_dir()['basedir'] . '/tzp-backup';
	wp_mkdir_p( $dir );
	$file = $dir . '/products-faq-' . gmdate( 'Ymd-His' ) . '.json';
	file_put_contents( $file, wp_json_encode( $tzp_backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	do_action( 'litespeed_purge_all' );
	WP_CLI::success( $tzp_changed . ' opisów zapisanych. Kopia: ' . $file );
} else {
	WP_CLI::success( $tzp_changed . ' opisów do zmiany (podgląd). Zapis: dodaj argument "apply".' );
}
