<?php
/**
 * Ujednolicenie opisów zapiekanek z kartami produktów od klienta (20.10.2026).
 *
 * Uruchomienie (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php           (podgląd, nic nie zapisuje)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php apply     (zapis)
 *
 * Zmienia opis i krótki opis produktów z kategorii Zapiekanki (także ukrytych kopii do zestawów) przez API WooCommerce:
 * - sery: według kart każda zapiekanka ma goudę, mozzarellę i parmezan, a długie opisy mówiły o "dwóch serach"
 *   (krótkie opisy już wymieniały parmezan),
 * - literówki w roku receptury farszu: "z 1886 roku" (Szwedzka), "z 1887 roku" (z Szynką), wszędzie indziej 1987,
 * - "Spinata Piccante" → "Spianata Piccante" (nazwa salami, Salami),
 * - "zapiekance Szefa" → "zapiekance na ostro" (stara nazwa w opisie ukrytych kopii "na ostro"),
 * - nagłówek H1 na początku opisu → H2 (na karcie produktu H1 to już nazwa produktu, dwa H1 na stronie to błąd SEO).
 *
 * Bez zmian: sosy i dodatki (to saszetki dołączane do paczki, nie ma ich na kartach) i zdania o dodatkach do
 * zamówienia (czekają na odpowiedź klienta, czy da się je zamówić).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$tzp_parmezan = 'Do tego <strong>parmezan</strong>, który wzmacnia smak całości. ';

// Kolejność ma znaczenie (np. lista serów przed zamianą "To duet").
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
