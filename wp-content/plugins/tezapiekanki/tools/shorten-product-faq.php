<?php
/**
 * Skraca FAQ w opisach produktów do pytań o konkretny smak; pytania ogólne są na stronie "Najczęstsze pytania".
 *
 * Uruchomienie (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/shorten-product-faq.php           (podgląd, nic nie zapisuje)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/shorten-product-faq.php apply     (zapis, z kopią opisów)
 *
 * Zostają pytania o: świeżość po dostawie, wysyłkę na świeżo, mrożenie, imprezy (zwykle 4). Znikają:
 * - "Czy mogę dodać do zapiekanki dodatkowe składniki?" (sklep nie ma takiej opcji, odpowiedź obiecywała dodatki),
 * - "Jak wygląda proces pakowania i transportu?", "Czy mogę zamówić różne smaki…?" (ogólne, są na stronie FAQ).
 * Numeracja "1. " w pytaniach znika (rozwijana lista jej nie potrzebuje), "Zapiekanka Szefa – na ostro" → "na ostro".
 * Dotyczy wszystkich produktów z sekcją FAQ (także ukrytych kopii do zestawów). Kopia opisów przed zapisem:
 * wp-content/uploads/tzp-backup/products-faq-<data>.json.
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

	// Stara nazwa smaku w FAQ "na ostro".
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
