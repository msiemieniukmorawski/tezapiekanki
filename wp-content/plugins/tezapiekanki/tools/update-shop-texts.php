<?php
/**
 * Teksty po uwagach klientki z 26.10: termin przydatności 7 dni w lodówce, pieczenie 220-250°C około 8 minut,
 * godzina graniczna 23:59, bez napisu "Zamów teraz, a my dostarczymy je jutro…" na stronie głównej.
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shop-texts.php --user=<login>          (podgląd)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shop-texts.php apply --user=<login>    (zapis)
 *
 * Co zmienia (przez API Elementora, WooCommerce i WordPressa, nie SQL-em):
 * 1. Dokumenty Elementora, wyszukane po roli, nie po ID: strona główna, strona FAQ (opcja tzp_faq_page_id), popup
 *    belki (meta _tzp_popup = shipping-why), blok "Info praktyczne" (meta _tzp_block = product-info), HTML Block
 *    zakładki "Sposób przygotowania" (opcja Woodmarta additional_tab_html_block) i strona "Sposoby dostawy".
 *    Zamienia tylko teksty z tablicy $tzp_texts, reszta dokumentu zostaje. Kopia _elementor_data przed zapisem trafia
 *    do wp-content/uploads/tzp-backup/.
 * 2. Tekst zakładki "Sposób przygotowania" w opcjach Woodmarta (additional_tab_text, zapas, gdy zakładka nie ma bloku).
 * 3. Opisy wszystkich produktów (także ukrytych kopii do zestawów): 6 dni → 7 dni, "kilka minut" → "około 8 minut".
 * 4. Atrybut "Termin przydatności" (pa_termin-przydatnosci): "6 dni" → "7 dni", pusty term "6 dni" jest usuwany.
 * Tekst, którego już nie ma (np. po drugim uruchomieniu), jest pomijany.
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

// Stary fragment => nowy. Zamiana fragmentów, nie całych pól, więc reszta tekstu (np. pogrubienia) zostaje.
$tzp_texts = array(
	// Strona główna, hero: napis usunięty (decyzja klientki z 26.10, obietnica "jutro" nie pasuje do 2 wysyłek w tygodniu).
	'<p>Zamów teraz, a my dostarczymy je jutro do Paczkomatu lub Twojego domu.</p>' => '',
	// Strona główna, kafelek "Świeże i gotowe do upieczenia w domu".
	'w domowym piekarniku, w ciągu kilku minut!'                                    => 'w domowym piekarniku, w około 8 minut!',
	// Strona główna, kroki pieczenia.
	'Nastaw piekarnik na 180-220 stopni.'                                           => 'Nastaw piekarnik na 220-250 stopni.',
	'Piecz przez kilka minut.'                                                      => 'Piecz około 8 minut.',
	// FAQ (strona główna i /faq/).
	'do niedzieli do 20:00'                                                         => 'do niedzieli do 23:59',
	'do wtorku do 20:00'                                                            => 'do wtorku do 23:59',
	'zdatne do spożycia przez 6 dni od dostawy'                                     => 'zdatne do spożycia przez 7 dni od dostawy',
	'Standardowo wystarczy <strong>kilka minut pieczenia</strong>, aby uzyskać'     => 'Wystarczy nagrzać piekarnik do 220-250°C (bez termoobiegu) i piec zapiekankę <strong>około 8 minut</strong>, aby uzyskać',
	// Zakładka "Sposób przygotowania" na karcie produktu.
	'Temperatura 180 - 220 °C'                                                      => 'Temperatura 220-250°C',
	'Czas: ~5 - 8 minut'                                                            => 'Czas: około 8 minut',
	// Popup belki.
	'W lodówce wytrzyma 6 dni'                                                      => 'W lodówce wytrzyma 7 dni',
	// Blok "Info praktyczne".
	'6 dni świeżości'                                                               => '7 dni świeżości',
	'<p>liczone od dostawy</p>'                                                     => '<p>w lodówce, liczone od dostawy</p>',
	// "Sposoby dostawy", sekcja "Kiedy wysyłamy" (wcześniej "w dni robocze do 10:00 tego samego dnia").
	'<ul><li><strong>Zamówienia złożone w dni robocze do godziny 10:00</strong> przekazujemy do wysyłki tego samego dnia.</li><li><strong>Zamówienia złożone w piątek</strong> realizujemy w najbliższy poniedziałek, aby uniknąć przetrzymywania przesyłek w magazynach logistycznych.</li></ul>' => '<ul><li><strong>Zamówienia złożone do niedzieli do 23:59</strong> wysyłamy w poniedziałek, a dostarczamy we wtorek.</li><li><strong>Zamówienia złożone do wtorku do 23:59</strong> wysyłamy w środę, a dostarczamy w czwartek.</li></ul><p>Wysyłamy tylko w poniedziałki i środy, dzięki temu paczki nie czekają w magazynach przez weekend.</p>',
);

// Zakładka "Sposób przygotowania": oba kroki miały tytuł "Rozgrzej piekarnik…", drugi (z czasem pieczenia) dostaje swój.
$tzp_items = static function ( array &$row ) {
	if ( 'Rozgrzej piekarnik i umieść zapiekankę' === ( $row['title'] ?? '' ) && 0 === strpos( (string) ( $row['subtitle'] ?? '' ), 'Czas:' ) ) {
		$row['title'] = 'Piecz zapiekankę';
		return 1;
	}
	return 0;
};

// Opisy produktów.
$tzp_product_texts = array(
	'6 dni od dnia dostarczenia'             => '7 dni od dnia dostarczenia',
	'(6 dni od dostarczenia)'                => '(7 dni od dostarczenia)',
	'podania w kilka minut'                  => 'podania w około 8 minut',
	'przechowywać w lodówce przez kilka dni' => 'przechowywać w lodówce do 7 dni',
);

$tzp_woodmart_tab_texts = array(
	'Nastaw piekarnik na 180-220 stopni' => 'Nastaw piekarnik na 220-250 stopni',
	'Piecz przez kilka minut aż'         => 'Piecz około 8 minut, aż',
);

$tzp_log = static function ( $label, $count ) {
	WP_CLI::log( sprintf( '%-55s %3d zmian', $label, $count ) );
};

/*
 * 1. Dokumenty Elementora.
 */
$tzp_find_post = static function ( $meta_key, $meta_value, $post_type ) {
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'meta_key'       => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	return $ids ? (int) $ids[0] : 0;
};

$tzp_delivery_page = get_page_by_path( 'sposoby-dostawy' );
$tzp_docs          = array_filter(
	array(
		'Strona główna'                   => (int) get_option( 'page_on_front' ),
		'Strona FAQ'                      => (int) get_option( 'tzp_faq_page_id' ),
		'Popup belki'                     => $tzp_find_post( '_tzp_popup', 'shipping-why', 'wd_popup' ),
		'Blok "Info praktyczne"'          => $tzp_find_post( '_tzp_block', 'product-info', 'cms_block' ),
		'Zakładka "Sposób przygotowania"' => function_exists( 'woodmart_get_opt' ) ? (int) woodmart_get_opt( 'additional_tab_html_block' ) : 0,
		'Sposoby dostawy'                 => $tzp_delivery_page ? $tzp_delivery_page->ID : 0,
	)
);

$tzp_replace_all = static function ( &$value, array $map ) {
	$count = 0;
	foreach ( $map as $old => $new ) {
		$n = substr_count( $value, $old );
		if ( $n ) {
			$value  = str_replace( $old, $new, $value );
			$count += $n;
		}
	}
	return $count;
};

$tzp_walk = static function ( array &$data ) use ( &$tzp_walk, $tzp_replace_all, $tzp_texts, $tzp_items ) {
	$count = $tzp_items( $data );
	foreach ( $data as &$value ) {
		if ( is_string( $value ) ) {
			$count += $tzp_replace_all( $value, $tzp_texts );
		} elseif ( is_array( $value ) ) {
			$count += $tzp_walk( $value );
		}
	}
	unset( $value );
	return $count;
};

$tzp_dir = wp_upload_dir()['basedir'] . '/tzp-backup';
$total   = 0;

foreach ( $tzp_docs as $label => $doc_id ) {
	$document = \Elementor\Plugin::$instance->documents->get( $doc_id, false );

	if ( ! $document || ! $document->is_built_with_elementor() ) {
		WP_CLI::warning( $label . ' (' . $doc_id . '): brak dokumentu Elementora, pomijam.' );
		continue;
	}

	$elements = $document->get_elements_data();
	$count    = $tzp_walk( $elements );
	$total   += $count;
	$tzp_log( $label . ' (' . $doc_id . ')', $count );

	if ( ! $tzp_apply || ! $count ) {
		continue;
	}

	wp_mkdir_p( $tzp_dir );
	file_put_contents( $tzp_dir . '/elementor-data-' . $doc_id . '-' . gmdate( 'Ymd-His' ) . '.json', get_post_meta( $doc_id, '_elementor_data', true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$document->save( array( 'elements' => $elements ) );
}

/*
 * 2. Tekst zakładki w opcjach Woodmarta.
 */
$tzp_options = get_option( 'xts-woodmart-options' );

if ( is_array( $tzp_options ) && isset( $tzp_options['additional_tab_text'] ) ) {
	$count  = $tzp_replace_all( $tzp_options['additional_tab_text'], $tzp_woodmart_tab_texts );
	$total += $count;
	$tzp_log( 'Opcje Woodmarta, tekst zakładki', $count );

	if ( $tzp_apply && $count ) {
		update_option( 'xts-woodmart-options', $tzp_options );
	}
}

/*
 * 3. Opisy produktów.
 */
$tzp_product_ids = wc_get_products(
	array(
		'limit'  => -1,
		'status' => array( 'publish', 'private', 'draft' ),
		'return' => 'ids',
	)
);
$count           = 0;
$products        = 0;

foreach ( $tzp_product_ids as $id ) {
	$product     = wc_get_product( $id );
	$description = $product->get_description();
	$short       = $product->get_short_description();
	$n           = $tzp_replace_all( $description, $tzp_product_texts ) + $tzp_replace_all( $short, $tzp_product_texts );

	if ( ! $n ) {
		continue;
	}

	$count += $n;
	$products++;

	if ( $tzp_apply ) {
		$product->set_description( $description );
		$product->set_short_description( $short );
		$product->save();
	}
}

$total += $count;
$tzp_log( 'Opisy produktów (' . $products . ' produktów)', $count );

/*
 * 4. Atrybut "Termin przydatności".
 */
$tzp_tax = 'pa_termin-przydatnosci';
$tzp_old = taxonomy_exists( $tzp_tax ) ? get_term_by( 'name', '6 dni', $tzp_tax ) : false;

if ( $tzp_old ) {
	$ids = get_objects_in_term( $tzp_old->term_id, $tzp_tax );
	$total += count( $ids );
	$tzp_log( 'Atrybut "Termin przydatności": 6 dni → 7 dni', count( $ids ) );

	if ( $tzp_apply ) {
		if ( ! get_term_by( 'name', '7 dni', $tzp_tax ) ) {
			wp_insert_term( '7 dni', $tzp_tax );
		}
		foreach ( $ids as $id ) {
			wp_remove_object_terms( (int) $id, (int) $tzp_old->term_id, $tzp_tax );
			wp_set_object_terms( (int) $id, '7 dni', $tzp_tax, true );
			wc_delete_product_transients( (int) $id );
		}
		wp_delete_term( $tzp_old->term_id, $tzp_tax );
	}
} else {
	$tzp_log( 'Atrybut "Termin przydatności": brak "6 dni"', 0 );
}

if ( ! $tzp_apply ) {
	WP_CLI::success( $total . ' zmian do zrobienia (podgląd). Zapis: dodaj argument "apply".' );
	return;
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );
WP_CLI::success( $total . ' zmian zapisanych. Kopie dokumentów Elementora: ' . $tzp_dir );
