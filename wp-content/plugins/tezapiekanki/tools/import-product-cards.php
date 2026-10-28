<?php
/**
 * Karty produktów od klienta (20.10.2026): PDF-y do mediów, wagi i wartości odżywcze na produktach.
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/import-product-cards.php --user=<login>          (podgląd, nic nie zapisuje)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/import-product-cards.php apply --user=<login>    (zapis)
 *
 * Źródło: jeden PDF od klienta z 11 kartami (strona = smak), pocięty na pliki w tools/karty-produktow/.
 * Wartości niżej przepisane z kart (w 100 g produktu przed pieczeniem, waga całej zapiekanki).
 *
 * Co robi (API WordPressa i WooCommerce, nie SQL-em):
 * 1. Wgrywa PDF-y do mediów (raz; rozpoznaje je po meta _tzp_card, nowy plik w tools/ = nowy załącznik).
 * 2. Atrybuty: nowe "Kwasy tłuszczowe nasycone" (pa_kwasy-nasycone) i "Cukry" (pa_cukry), nazwy jak na etykiecie
 *    ("Kalorie" → "Wartość energetyczna", "Tłuszcze" → "Tłuszcz"), term "Mozarella" → "Mozzarella" (adres termu bez zmian).
 * 3. Każdy smak: produkt widoczny i ukryte kopie do zestawów (ta sama nazwa w kategorii Zapiekanki) dostają wagę,
 *    wartości odżywcze, sery z karty i pole "kartaproduktowa" z linkiem do PDF-a (format jak dotąd, czyta je też stary
 *    widget "Pobierz kartę produktu"). "Zapiekanka na ostro" nie miała żadnych atrybutów: dochodzą mięso, składnik
 *    dodatkowy i termin przydatności.
 * 4. Usuwa termy wagi i wartości odżywczych, których nie używa już żaden produkt.
 *
 * Kontrola: energia z makro (9/4/4 kcal, 37/17/17 kJ na 1 g tłuszczu, węglowodanów i białka) różna od karty o więcej
 * niż 5% = ostrzeżenie do wyjaśnienia z klientem (wartości z karty i tak trafiają na stronę).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

if ( ! function_exists( 'wc_get_products' ) ) {
	WP_CLI::error( 'Wymagany WooCommerce.' );
}

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

if ( $tzp_apply && ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'manage_product_terms' ) ) ) {
	WP_CLI::error( 'Uruchom z --user=<administrator>.' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Kolejność kolumn: waga [g], kJ, kcal, tłuszcz, w tym nasycone, węglowodany, w tym cukry, białko, sól [g].
$tzp_cards = array(
	'Zapiekanka Tradycyjna'  => array( 'tradycyjna', 319, 829, 198, '6,7', '4,4', '23,6', '2,1', '11,2', '1,3' ),
	'Zapiekanka Wiejska'     => array( 'wiejska', 449, 799, 191, '8,3', '4,4', '17,0', '1,5', '12,2', '1,4' ),
	'Zapiekanka z boczkiem'  => array( 'z-boczkiem', 366, 977, 234, '11,4', '5,8', '20,7', '2,0', '12,5', '1,4' ),
	'Zapiekanka Góralska'    => array( 'goralska', 405, 943, 225, '10,0', '6,0', '20,7', '3,4', '13,4', '1,4' ),
	'Zapiekanka Mega Serowa' => array( 'mega-serowa', 413, 969, 231, '11,1', '7,0', '18,5', '1,7', '14,8', '1,4' ),
	'Zapiekanka z Szynką'    => array( 'z-szynka', 374, 821, 196, '6,7', '4,1', '20,2', '1,8', '14,1', '1,5' ),
	'Zapiekanka Kebab'       => array( 'kebab', 389, 783, 187, '6,9', '4,0', '19,4', '1,8', '12,2', '1,5' ),
	'Zapiekanka na dziko'    => array( 'na-dziko', 366, 928, 222, '7,9', '5,4', '20,7', '1,9', '13,0', '1,5' ),
	'Zapiekanka Salami'      => array( 'salami', 349, 880, 211, '8,5', '5,0', '21,6', '1,9', '12,2', '1,5' ),
	'Zapiekanka na ostro'    => array( 'na-ostro', 404, 863, 206, '9,5', '4,9', '19,5', '2,2', '10,9', '1,5' ),
	// Na karcie "9.7 g" (kropka), zapisujemy jak pozostałe.
	'Zapiekanka Szwedzka'    => array( 'szwedzka', 379, 818, 196, '7,4', '4,5', '22,7', '3,5', '9,7', '1,2' ),
);

// Sery z kart: wszystkie smaki mają goudę, mozzarellę i parmezan, Góralska i Mega Serowa także oscypek.
$tzp_cheese_extra = array(
	'Zapiekanka Góralska'    => array( 'Oscypek' ),
	'Zapiekanka Mega Serowa' => array( 'Oscypek' ),
);

// Atrybuty, których "na ostro" nie miała wcale (dane z karty i opisu produktu).
$tzp_extra_terms = array(
	'Zapiekanka na ostro' => array(
		'pa_mieso'               => array( 'Chorizo' ),
		'pa_skladnik-dodatkowy'  => array( 'Jalapeño' ),
		'pa_termin-przydatnosci' => array( '7 dni' ),
	),
);

// Atrybuty wagi i wartości odżywczych w kolejności z etykiety. Pozostałe atrybuty produktu idą za nimi bez zmian.
$tzp_nutrition_attrs = array(
	'pa_waga'           => 'Waga',
	'pa_kalorie'        => 'Wartość energetyczna',
	'pa_tluszcze'       => 'Tłuszcz',
	'pa_kwasy-nasycone' => 'Kwasy tłuszczowe nasycone',
	'pa_weglowodany'    => 'Węglowodany',
	'pa_cukry'          => 'Cukry',
	'pa_bialko'         => 'Białko',
	'pa_sol'            => 'Sól',
);

$tzp_log = static function ( $msg ) use ( $tzp_apply ) {
	WP_CLI::log( ( $tzp_apply ? '' : '[podgląd] ' ) . $msg );
};

/*
 * 1. Atrybuty (taksonomie).
 */
foreach ( $tzp_nutrition_attrs as $tax => $label ) {
	$slug = substr( $tax, 3 );
	$id   = wc_attribute_taxonomy_id_by_name( $tax );

	if ( ! $id ) {
		$tzp_log( "Nowy atrybut \"$label\" ($tax)." );

		if ( $tzp_apply ) {
			$id = wc_create_attribute(
				array(
					'name'         => $label,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);

			if ( is_wp_error( $id ) ) {
				WP_CLI::error( "Atrybut $tax: " . $id->get_error_message() );
			}
		}
	} else {
		$attr = wc_get_attribute( $id );

		if ( $attr->name !== $label ) {
			$tzp_log( "Atrybut $tax: nazwa \"{$attr->name}\" → \"$label\"." );

			if ( $tzp_apply ) {
				$res = wc_update_attribute(
					$id,
					array(
						'name'         => $label,
						'slug'         => $slug, // Bez tego WooCommerce zrobiłby slug z nazwy i zmienił taksonomię.
						'type'         => $attr->type,
						'order_by'     => $attr->order_by,
						'has_archives' => $attr->has_archives,
					)
				);

				if ( is_wp_error( $res ) ) {
					WP_CLI::error( "Atrybut $tax: " . $res->get_error_message() );
				}
			}
		}
	}

	// Taksonomia nowego atrybutu rejestruje się dopiero przy następnym żądaniu.
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy(
			$tax,
			array( 'product' ),
			array(
				'hierarchical' => false,
				'show_ui'      => false,
				'query_var'    => false,
				'rewrite'      => false,
			)
		);
	}
}

$mozarella = get_term_by( 'name', 'Mozarella', 'pa_ser' );

if ( $mozarella ) {
	$tzp_log( 'Ser: term "Mozarella" → "Mozzarella".' );

	if ( $tzp_apply ) {
		wp_update_term( $mozarella->term_id, 'pa_ser', array( 'name' => 'Mozzarella' ) );
	}
}

/**
 * ID termu o tej nazwie, a gdy go nie ma, nowy term. Slug z przecinkiem zamienionym na myślnik ("1,5 g" → "1-5-g"),
 * żeby "1,5 g" i "15 g" nie trafiły na ten sam slug. W podglądzie nowy term ma ID 0.
 */
$tzp_term = static function ( $tax, $name ) use ( $tzp_apply ) {
	$term = get_term_by( 'name', $name, $tax );

	if ( $term ) {
		return (int) $term->term_id;
	}

	if ( ! $tzp_apply ) {
		return 0;
	}

	$res = wp_insert_term( $name, $tax, array( 'slug' => sanitize_title( str_replace( ',', '-', $name ) ) ) );

	if ( is_wp_error( $res ) ) {
		if ( 'term_exists' === $res->get_error_code() ) {
			return (int) $res->get_error_data();
		}
		WP_CLI::error( "Term \"$name\" ($tax): " . $res->get_error_message() );
	}

	return (int) $res['term_id'];
};

/*
 * 2. PDF-y.
 */
$tzp_card_attachment = static function ( $slug, $title, $parent ) use ( $tzp_apply, $tzp_log ) {
	$file = 'karta-produktu-zapiekanka-' . $slug . '.pdf';
	$path = TZP_DIR . 'tools/karty-produktow/' . $file;

	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Brak pliku $path." );
	}

	$md5      = md5_file( $path );
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_tzp_card', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	if ( $existing && get_post_meta( $existing[0], '_tzp_card_md5', true ) === $md5 ) {
		return array( (int) $existing[0], wp_get_attachment_url( $existing[0] ) );
	}

	$tzp_log( ( $existing ? 'Nowa wersja PDF-a ' : 'Wgrywam PDF ' ) . $file . '.' );

	if ( ! $tzp_apply ) {
		return array( 0, '(nowy plik ' . $file . ')' );
	}

	$tmp = wp_tempnam( $file );
	copy( $path, $tmp );

	// Bez produktu jako rodzica: WordPress wgrałby plik do folderu z datą produktu (np. uploads/2025/01). Globalny $post
	// też trzeba wyczyścić, bo get_post( 0 ) w media_handle_sideload() zwraca właśnie jego.
	$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$id              = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		0,
		'Karta produktu: ' . $title
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "PDF $file: " . $id->get_error_message() );
	}

	// "Przesłano do" w mediach = widoczny produkt.
	wp_update_post(
		array(
			'ID'          => $id,
			'post_parent' => $parent,
		)
	);

	// Stara wersja zostaje w mediach, ale przestaje być kartą tego smaku.
	if ( $existing ) {
		delete_post_meta( $existing[0], '_tzp_card' );
	}

	update_post_meta( $id, '_tzp_card', $slug );
	update_post_meta( $id, '_tzp_card_md5', $md5 );

	return array( (int) $id, wp_get_attachment_url( $id ) );
};

/*
 * 3. Produkty.
 */
$tzp_num = static function ( $value ) {
	return (float) str_replace( ',', '.', $value );
};

$touched = 0;

foreach ( $tzp_cards as $title => $card ) {
	list( $slug, $weight, $kj, $kcal, $fat, $sat, $carb, $sugar, $protein, $salt ) = $card;

	$ids = wc_get_products(
		array(
			'limit'    => -1,
			'status'   => array( 'publish', 'private', 'draft' ),
			'category' => array( 'zapiekanki' ),
			'return'   => 'ids',
			'orderby'  => 'ID',
			'order'    => 'ASC',
		)
	);
	$ids = array_values(
		array_filter(
			$ids,
			static function ( $id ) use ( $title ) {
				return get_the_title( $id ) === $title;
			}
		)
	);

	if ( ! $ids ) {
		WP_CLI::warning( "Brak produktu \"$title\", pomijam." );
		continue;
	}

	$visible = array_values(
		array_filter(
			$ids,
			static function ( $id ) {
				return 'hidden' !== wc_get_product( $id )->get_catalog_visibility();
			}
		)
	);
	$parent  = $visible ? $visible[0] : $ids[0];

	// Kontrola energii z makro.
	$calc_kcal = 9 * $tzp_num( $fat ) + 4 * $tzp_num( $carb ) + 4 * $tzp_num( $protein );
	$calc_kj   = 37 * $tzp_num( $fat ) + 17 * $tzp_num( $carb ) + 17 * $tzp_num( $protein );

	if ( abs( $calc_kcal - $kcal ) / $kcal > 0.05 || abs( $calc_kj - $kj ) / $kj > 0.05 ) {
		WP_CLI::warning( sprintf( '%s: na karcie %d kJ / %d kcal, z makro wychodzi %d kJ / %d kcal. Do wyjaśnienia z klientem.', $title, $kj, $kcal, round( $calc_kj ), round( $calc_kcal ) ) );
	}

	list( $pdf_id, $pdf_url ) = $tzp_card_attachment( $slug, $title, $parent );

	$values = array(
		'pa_waga'           => $weight . ' g',
		'pa_kalorie'        => $kj . ' kJ / ' . $kcal . ' kcal',
		'pa_tluszcze'       => $fat . ' g',
		'pa_kwasy-nasycone' => $sat . ' g',
		'pa_weglowodany'    => $carb . ' g',
		'pa_cukry'          => $sugar . ' g',
		'pa_bialko'         => $protein . ' g',
		'pa_sol'            => $salt . ' g',
	);

	$set = array();
	foreach ( $values as $tax => $name ) {
		$set[ $tax ] = array( $name );
	}
	$set['pa_ser'] = array_merge( array( 'Gouda', 'Mozzarella', 'Parmezan' ), $tzp_cheese_extra[ $title ] ?? array() );

	foreach ( $tzp_extra_terms[ $title ] ?? array() as $tax => $names ) {
		$set[ $tax ] = $names;
	}

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		$current = $product->get_attributes();
		$changes = array();

		foreach ( $set as $tax => $names ) {
			$old_names = isset( $current[ $tax ] ) ? wp_list_pluck( (array) $current[ $tax ]->get_terms(), 'name' ) : array();
			$old       = $old_names ? implode( ' | ', $old_names ) : '-';
			$new       = implode( ' | ', $names );
			// Porównanie zbiorów (kolejność termów bez znaczenia); w podglądzie term "Mozarella" ma jeszcze starą nazwę.
			$old_set = str_replace( 'Mozarella', 'Mozzarella', $old_names );
			$new_set = $names;
			sort( $old_set );
			sort( $new_set );
			if ( $old_set !== $new_set ) {
				$changes[] = sprintf( '%s: %s → %s', substr( $tax, 3 ), $old, $new );
			}
		}

		$card_html = sprintf( '<a href="%s" target="_blank" rel="noopener">POBIERZ KARTĘ PRODUKTU</a>', esc_url( $pdf_url ) );
		$old_card  = (string) $product->get_meta( 'kartaproduktowa' );
		if ( $old_card !== $card_html ) {
			$changes[] = 'karta: ' . ( preg_match( '/href="([^"]+)"/', $old_card, $m ) ? basename( $m[1] ) : '-' ) . ' → ' . basename( $pdf_url );
		}

		if ( ! $changes ) {
			continue;
		}

		$touched++;
		$tzp_log( sprintf( '#%d %s%s', $id, $title, 'hidden' === $product->get_catalog_visibility() ? ' (kopia do zestawu)' : '' ) );
		foreach ( $changes as $change ) {
			WP_CLI::log( '      ' . $change );
		}

		if ( ! $tzp_apply ) {
			continue;
		}

		// Nowa kolejność: waga i wartości odżywcze jak na etykiecie, potem reszta w dotychczasowej kolejności.
		$ordered = array();
		foreach ( array_keys( $tzp_nutrition_attrs ) as $tax ) {
			$ordered[ $tax ] = null;
		}
		foreach ( $current as $tax => $attr ) {
			$ordered[ $tax ] = $attr;
		}
		foreach ( $set as $tax => $names ) {
			$attr = new WC_Product_Attribute();
			$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
			$attr->set_name( $tax );
			$attr->set_options(
				array_map(
					static function ( $name ) use ( $tzp_term, $tax ) {
						return $tzp_term( $tax, $name );
					},
					$names
				)
			);
			$attr->set_visible( true );
			$attr->set_variation( false );
			$ordered[ $tax ] = $attr;
		}

		$position = 0;
		foreach ( $ordered as $tax => $attr ) {
			if ( ! $attr ) {
				unset( $ordered[ $tax ] );
				continue;
			}
			$attr->set_position( $position++ );
		}

		$product->set_attributes( $ordered );
		$product->update_meta_data( 'kartaproduktowa', $card_html );
		$product->save();
	}
}

/*
 * 4. Nieużywane termy wagi i wartości odżywczych (stare wartości, literówki).
 */
$orphans = 0;
foreach ( array_keys( $tzp_nutrition_attrs ) as $tax ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		continue;
	}

	foreach ( $terms as $term ) {
		$objects = get_objects_in_term( $term->term_id, $tax );

		if ( $objects && ! is_wp_error( $objects ) ) {
			continue;
		}

		// W podglądzie produkty mają jeszcze stare termy, więc ta lista jest pełna dopiero przy zapisie.
		$orphans++;
		if ( $tzp_apply ) {
			wp_delete_term( $term->term_id, $tax );
		}
	}
}

if ( $tzp_apply ) {
	wc_delete_product_transients();
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	do_action( 'litespeed_purge_all' );
	WP_CLI::success( "$touched produktów zapisanych, $orphans nieużywanych termów usuniętych." );
} else {
	WP_CLI::success( "$touched produktów do zmiany, $orphans nieużywanych termów już teraz (po zapisie dojdą stare wartości). Zapis: dodaj argument \"apply\"." );
}
