<?php
/**
 * Texts after the client's feedback of 2026-10-26: shelf life 7 days in the fridge, baking at 220-250°C for about 8 minutes,
 * cutoff 23:59, no "Zamów teraz, a my dostarczymy je jutro…" line on the front page.
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shop-texts.php --user=<login>          (preview)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shop-texts.php apply --user=<login>    (save)
 *
 * What it changes (via Elementor, WooCommerce and WordPress APIs, no SQL):
 * 1. Elementor documents, looked up by role, not by ID: front page, FAQ page (option tzp_faq_page_id), bar popup
 *    (meta _tzp_popup = shipping-why), "Info praktyczne" block (meta _tzp_block = product-info), the HTML Block of the
 *    "Sposób przygotowania" tab (Woodmart option additional_tab_html_block) and the "Sposoby dostawy" page.
 *    Replaces only the texts from the $tzp_texts array, the rest of the document is kept. A copy of _elementor_data is
 *    saved before writing to wp-content/uploads/tzp-backup/.
 * 2. Text of the "Sposób przygotowania" tab in Woodmart options (additional_tab_text, fallback when the tab has no block).
 * 3. Descriptions of all products (including hidden bundle copies): 6 days → 7 days, "kilka minut" → "około 8 minut".
 * 4. Attribute "Termin przydatności" (pa_termin-przydatnosci): "6 dni" → "7 dni", the empty "6 dni" term is deleted.
 * Text that is no longer present (e.g. on a second run) is skipped.
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

// Old fragment => new. Fragments are replaced, not whole fields, so the rest of the text (e.g. bold) is kept.
$tzp_texts = array(
	// Front page, hero: line removed (client's decision, 2026-10-26; the "tomorrow" promise does not fit 2 shipments a week).
	'<p>Zamów teraz, a my dostarczymy je jutro do Paczkomatu lub Twojego domu.</p>' => '',
	// Front page, "Świeże i gotowe do upieczenia w domu" tile.
	'w domowym piekarniku, w ciągu kilku minut!'                                    => 'w domowym piekarniku, w około 8 minut!',
	// Front page, baking steps.
	'Nastaw piekarnik na 180-220 stopni.'                                           => 'Nastaw piekarnik na 220-250 stopni.',
	'Piecz przez kilka minut.'                                                      => 'Piecz około 8 minut.',
	// FAQ (front page and /faq/).
	'do niedzieli do 20:00'                                                         => 'do niedzieli do 23:59',
	'do wtorku do 20:00'                                                            => 'do wtorku do 23:59',
	'zdatne do spożycia przez 6 dni od dostawy'                                     => 'zdatne do spożycia przez 7 dni od dostawy',
	'Standardowo wystarczy <strong>kilka minut pieczenia</strong>, aby uzyskać'     => 'Wystarczy nagrzać piekarnik do 220-250°C (bez termoobiegu) i piec zapiekankę <strong>około 8 minut</strong>, aby uzyskać',
	// "Sposób przygotowania" tab on the product page.
	'Temperatura 180 - 220 °C'                                                      => 'Temperatura 220-250°C',
	'Czas: ~5 - 8 minut'                                                            => 'Czas: około 8 minut',
	// Bar popup.
	'W lodówce wytrzyma 6 dni'                                                      => 'W lodówce wytrzyma 7 dni',
	// "Info praktyczne" block.
	'6 dni świeżości'                                                               => '7 dni świeżości',
	'<p>liczone od dostawy</p>'                                                     => '<p>w lodówce, liczone od dostawy</p>',
	// "Sposoby dostawy", "Kiedy wysyłamy" section (previously "w dni robocze do 10:00 tego samego dnia").
	'<ul><li><strong>Zamówienia złożone w dni robocze do godziny 10:00</strong> przekazujemy do wysyłki tego samego dnia.</li><li><strong>Zamówienia złożone w piątek</strong> realizujemy w najbliższy poniedziałek, aby uniknąć przetrzymywania przesyłek w magazynach logistycznych.</li></ul>' => '<ul><li><strong>Zamówienia złożone do niedzieli do 23:59</strong> wysyłamy w poniedziałek, a dostarczamy we wtorek.</li><li><strong>Zamówienia złożone do wtorku do 23:59</strong> wysyłamy w środę, a dostarczamy w czwartek.</li></ul><p>Wysyłamy tylko w poniedziałki i środy, dzięki temu paczki nie czekają w magazynach przez weekend.</p>',
);

// "Sposób przygotowania" tab: both steps were titled "Rozgrzej piekarnik…", the second one (with baking time) gets its own.
$tzp_items = static function ( array &$row ) {
	if ( 'Rozgrzej piekarnik i umieść zapiekankę' === ( $row['title'] ?? '' ) && 0 === strpos( (string) ( $row['subtitle'] ?? '' ), 'Czas:' ) ) {
		$row['title'] = 'Piecz zapiekankę';
		return 1;
	}
	return 0;
};

// Product descriptions.
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
 * 1. Elementor documents.
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
 * 2. Tab text in Woodmart options.
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
 * 3. Product descriptions.
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
 * 4. "Termin przydatności" attribute.
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
