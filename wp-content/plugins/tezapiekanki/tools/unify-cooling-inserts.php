<?php
/**
 * Ujednolicenie nazwy wkładów w opisach produktów: "wkłady żelowe" → "wkłady chłodzące" (tak mówią strona główna,
 * "Sposoby dostawy", popup i blok "Info praktyczne").
 *
 * Uruchomienie (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-cooling-inserts.php           (podgląd, nic nie zapisuje)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-cooling-inserts.php apply     (zapis)
 *
 * Zmienia opis i krótki opis wszystkich produktów (także ukrytych kopii do zestawów) przez API WooCommerce.
 * Odmiana: żelowe/żelowych/żelowymi/żelowym/żelowy/żelowa/żelową → chłodzące/chłodzących/…, wielka litera zostaje.
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

// Końcówki przymiotnika są takie same: żelow-ymi → chłodząc-ymi, żelow-e → chłodząc-e itd.
$tzp_replace = static function ( $text, &$count ) {
	return preg_replace_callback(
		'/(ż|Ż)elow(ymi|ych|ym|e|y|a|ą)(?!\p{L})/u',
		static function ( $m ) use ( &$count ) {
			$count++;
			return ( 'Ż' === $m[1] ? 'Chłodząc' : 'chłodząc' ) . $m[2];
		},
		$text
	);
};

$ids   = wc_get_products(
	array(
		'limit'  => -1,
		'status' => array( 'publish', 'private', 'draft' ),
		'return' => 'ids',
	)
);
$total = 0;

foreach ( $ids as $id ) {
	$product = wc_get_product( $id );
	$count   = 0;

	$description = $tzp_replace( $product->get_description(), $count );
	$short       = $tzp_replace( $product->get_short_description(), $count );

	if ( ! $count ) {
		continue;
	}

	$total += $count;
	WP_CLI::log( sprintf( '%6d %-32s %2d zmian%s', $id, $product->get_name(), $count, $product->is_visible() ? '' : '  (ukryty)' ) );

	if ( $tzp_apply ) {
		$product->set_description( $description );
		$product->set_short_description( $short );
		$product->save();
	}
}

if ( $tzp_apply ) {
	do_action( 'litespeed_purge_all' );
	WP_CLI::success( $total . ' zamian zapisanych.' );
} else {
	WP_CLI::success( $total . ' zamian do zrobienia (podgląd). Zapis: dodaj argument "apply".' );
}
