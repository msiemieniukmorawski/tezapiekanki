<?php
/**
 * Aligns the zapiekanka descriptions with the client's product cards (2026-10-20).
 *
 * Usage (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php           (preview, saves nothing)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php apply     (save)
 *
 * Changes the description and short description of products in the Zapiekanki category (including hidden bundle copies)
 * via the WooCommerce API:
 * - cheeses: according to the cards every zapiekanka has gouda, mozzarella and parmesan, while the long descriptions
 *   mentioned "two cheeses" (the short descriptions already listed parmesan),
 * - typos in the filling recipe year: "z 1886 roku" (Szwedzka), "z 1887 roku" (z Szynką), 1987 everywhere else,
 * - "Spinata Piccante" → "Spianata Piccante" (salami name, Salami),
 * - "zapiekance Szefa" → "zapiekance na ostro" (old name in the description of the hidden "na ostro" copies),
 * - H1 heading at the start of the description → H2 (on the product page H1 is already the product name, two H1s on a page is an SEO error).
 *
 * Unchanged: sauces and extras (sachets added to the parcel, not on the cards) and sentences about order add-ons
 * (waiting for the client to confirm whether they can be ordered).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$tzp_parmezan = 'Do tego <strong>parmezan</strong>, który wzmacnia smak całości. ';

// Order matters (e.g. the cheese list before the "To duet" replacement).
$tzp_rules = array(
	'H1 → H2'                    => array( '#<h1(\s[^>]*)?>(.*?)</h1>#su', '<h2$1>$2</h2>' ),
	'nagłówek sekcji serów'      => array( '#<h3>Ser gouda i mozzarella</h3>#u', '<h3>Sery gouda, mozzarella i parmezan</h3>' ),
	'dwa sery → trzy'            => array( '#Połączenie dwóch serów tworzy#u', 'Połączenie trzech serów tworzy' ),
	'dwa rodzaje sera → trzy'    => array( '#łączymy dwa rodzaje sera#u', 'łączymy trzy rodzaje sera' ),
	'parmezan w zdaniu o serach' => array(
		'#a <strong>mozzarella</strong> zapewnia delikatność i charakterystyczne ciągnięcie\.#u',
		'<strong>mozzarella</strong> zapewnia delikatność i charakterystyczne ciągnięcie, a <strong>parmezan</strong> wzmacnia smak.',
	),
	'parmezan pod listą serów'   => array(
		'#(</ul>)(\s*)(Po upieczeniu sery łączą się|W zestawieniu z wiejskimi dodatkami sery|To duet, który stanowi)#u',
		'$1$2' . $tzp_parmezan . '$3',
	),
	'"To duet" (łączy się)'      => array( '#To duet, który świetnie łączy się z#u', 'Razem świetnie łączą się z' ),
	'"To duet" (łagodzi)'        => array( '#To duet, który łagodzi ostrość dodatków#u', 'Razem łagodzą ostrość dodatków' ),
	'"To duet" (stanowi)'        => array( '#To duet, który stanowi#u', 'Razem stanowią' ),
	'sery we wstępie'            => array(
		'#(?:ciągnące\s+)?sery\s+(?:<strong>)?gouda(?:</strong>)?\s+i\s*(?:<strong>)?\s*mozzarella(?:</strong>)?#u',
		'sery <strong>gouda</strong>, <strong>mozzarella</strong> i <strong>parmezan</strong>',
	),
	'sery we wstępie (Tradycyjna)' => array(
		'#kompozycji serów:\s*<strong>gouda</strong>\s+i\s+<strong>mozzarella</strong>#u',
		'kompozycji serów: <strong>gouda</strong>, <strong>mozzarella</strong> i <strong>parmezan</strong>',
	),
	'sery we wstępie (Mega Serowa)' => array(
		'#trzy wyjątkowe sery:\s*<strong>\s*goudę</strong>,\s*<strong>mozzarellę</strong>\s+i\s+<strong>oscypek</strong>#u',
		'cztery wyjątkowe sery: <strong>goudę</strong>, <strong>mozzarellę</strong>, <strong>parmezan</strong> i <strong>oscypek</strong>',
	),
	'rok receptury'              => array( '#receptury z 18[89][67] roku#u', 'receptury z 1987 roku' ),
	'Spianata Piccante'          => array( '#Spinata Piccante#u', 'Spianata Piccante' ),
	'stara nazwa "Szefa"'        => array( '#zapiekance Szefa#u', 'zapiekance na ostro' ),
);

$tzp_ids = wc_get_products(
	array(
		'limit'    => -1,
		'status'   => array( 'publish', 'private', 'draft' ),
		'category' => array( 'zapiekanki' ),
		'return'   => 'ids',
		'orderby'  => 'ID',
		'order'    => 'ASC',
	)
);

$tzp_total = 0;

foreach ( $tzp_ids as $id ) {
	$product = wc_get_product( $id );
	$fields  = array(
		'description'       => $product->get_description(),
		'short_description' => $product->get_short_description(),
	);
	$found   = array();

	foreach ( $fields as $field => $text ) {
		foreach ( $tzp_rules as $name => $rule ) {
			$count = 0;
			$text  = preg_replace( $rule[0], $rule[1], $text, -1, $count );

			if ( $count ) {
				$found[ $name ] = ( $found[ $name ] ?? 0 ) + $count;
			}
		}
		$fields[ $field ] = $text;
	}

	if ( ! $found ) {
		continue;
	}

	$tzp_total += array_sum( $found );
	WP_CLI::log(
		sprintf(
			'%6d %-24s%s  %s',
			$id,
			$product->get_name(),
			$product->is_visible() ? '' : ' (kopia)',
			implode(
				', ',
				array_map(
					static function ( $name, $count ) {
						return $name . ( $count > 1 ? ' ×' . $count : '' );
					},
					array_keys( $found ),
					$found
				)
			)
		)
	);

	if ( $tzp_apply ) {
		$product->set_description( $fields['description'] );
		$product->set_short_description( $fields['short_description'] );
		$product->save();
	}
}

if ( $tzp_apply ) {
	do_action( 'litespeed_purge_all' );
	WP_CLI::success( $tzp_total . ' zamian zapisanych.' );
} else {
	WP_CLI::success( $tzp_total . ' zamian do zrobienia (podgląd). Zapis: dodaj argument "apply".' );
}
