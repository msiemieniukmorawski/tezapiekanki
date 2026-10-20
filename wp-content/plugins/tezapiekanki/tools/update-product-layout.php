<?php
/**
 * Karta zapiekanki (layout Woodmarta 6480): wartość odżywcza z kartą PDF i piny smaków.
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-product-layout.php --user=<login>
 *   (opcjonalnie ID layoutu jako pierwszy argument, domyślnie 6480)
 *
 * Co robi (przez API Elementora, nie SQL-em):
 * 1. Kontener z tabelą atrybutów Woodmarta (widgety: tekst "Wartości odżywcze na 100g", "Pobierz kartę produktu"
 *    i tabela) dostaje zamiast nich jeden widget Shortcode [tzp_nutrition]. Ustawienia kontenera (odstępy) zostają.
 * 2. Ten kontener przechodzi wyżej: zaraz pod blok "Info praktyczne" (shortcode [html_block …]), a gdy go nie ma,
 *    pod kontener z przyciskiem "Dodaj do koszyka". Logotypy płatności i licznik odwiedzin są pod nim.
 * 3. Pod formularzem zakupu (kontener z ceną i "Dodaj do koszyka") nowy kontener z widgetem Shortcode
 *    [tzp_flavor_pins]. Gdy piny są w innym miejscu (do 20.10 były pod tytułem), przenosi je.
 * Kroki, które już są w layoucie, pomija. Widgety mają wyłączony cache elementu (treść zależy od produktu).
 * Przed zapisem kopia _elementor_data trafia do wp-content/uploads/tzp-backup/.
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

$tzp_layout_id = (int) ( $args[0] ?? 6480 );
$tzp_document  = \Elementor\Plugin::$instance->documents->get( $tzp_layout_id, false );

if ( ! $tzp_document ) {
	WP_CLI::error( 'Brak layoutu ' . $tzp_layout_id . '.' );
}

$tzp_id = static function () {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
};

$tzp_shortcode_widget = static function ( $shortcode, $css_class ) use ( $tzp_id ) {
	return array(
		'id'         => $tzp_id(),
		'elType'     => 'widget',
		'widgetType' => 'shortcode',
		'settings'   => array(
			'shortcode'      => $shortcode,
			// "yes" = renderowany przy każdym wyświetleniu (bez cache elementów Elementora), bo treść zależy od produktu.
			'_element_cache' => 'yes',
			'_css_classes'   => $css_class,
		),
		'elements'   => array(),
	);
};

$tzp_has = static function ( $elements, callable $test ) use ( &$tzp_has ) {
	foreach ( $elements as $el ) {
		if ( $test( $el ) ) {
			return true;
		}
		if ( ! empty( $el['elements'] ) && $tzp_has( $el['elements'], $test ) ) {
			return true;
		}
	}
	return false;
};

$tzp_is_shortcode = static function ( $pattern ) {
	return static function ( $el ) use ( $pattern ) {
		return 'shortcode' === ( $el['widgetType'] ?? '' ) && preg_match( $pattern, (string) ( $el['settings']['shortcode'] ?? '' ) );
	};
};

$tzp_is_widget = static function ( $type ) {
	return static function ( $el ) use ( $type ) {
		return $type === ( $el['widgetType'] ?? '' );
	};
};

$elements = $tzp_document->get_elements_data();
$changes  = array();

/*
 * 1 i 2. Wartość odżywcza.
 */
if ( $tzp_has( $elements, $tzp_is_shortcode( '/\[tzp_nutrition\b/' ) ) ) {
	WP_CLI::log( '[tzp_nutrition] już jest, pomijam.' );
} else {
	$move = static function ( array &$els ) use ( &$move, $tzp_has, $tzp_is_widget, $tzp_is_shortcode, $tzp_shortcode_widget ) {
		foreach ( $els as $i => $el ) {
			if ( 'container' !== ( $el['elType'] ?? '' ) || empty( $el['elements'] ) ) {
				continue;
			}

			$is_table_box = $tzp_has( array( $el ), $tzp_is_widget( 'wd_single_product_additional_info_table' ) );

			// Kontener z tabelą jest bezpośrednim dzieckiem tego poziomu: zamiana treści i przeniesienie wśród rodzeństwa.
			if ( $is_table_box && ! $tzp_has( $el['elements'], $tzp_is_widget( 'wd_single_product_add_to_cart' ) ) ) {
				$box             = $el;
				$box['elements'] = array( $tzp_shortcode_widget( '[tzp_nutrition]', 'tzp-nutrition-widget' ) );
				array_splice( $els, $i, 1 );

				$target = null;
				foreach ( $els as $j => $sibling ) {
					if ( $tzp_has( array( $sibling ), $tzp_is_shortcode( '/\[html_block\b/' ) ) ) {
						$target = $j;
						break;
					}
				}
				if ( null === $target ) {
					foreach ( $els as $j => $sibling ) {
						if ( $tzp_has( array( $sibling ), $tzp_is_widget( 'wd_single_product_add_to_cart' ) ) ) {
							$target = $j;
							break;
						}
					}
				}

				$position = null === $target ? $i : $target + 1;
				array_splice( $els, $position, 0, array( $box ) );

				return sprintf( 'kontener %s: [tzp_nutrition] zamiast %d elementów, pozycja %d → %d', $el['id'], count( $el['elements'] ), $i, $position );
			}

			$done = $move( $els[ $i ]['elements'] );
			if ( $done ) {
				return $done;
			}
		}
		return false;
	};

	$done = $move( $elements );

	if ( ! $done ) {
		WP_CLI::warning( 'Brak kontenera z tabelą atrybutów Woodmarta, [tzp_nutrition] nie wstawiony.' );
	} else {
		$changes[] = $done;
	}
}

/*
 * 3. Piny smaków pod formularzem zakupu: osobny kontener zaraz pod kontenerem z ceną i "Dodaj do koszyka", z jego
 *    odstępami (decyzja Marcina z 20.10; wcześniej piny były pod tytułem). Piny z innego miejsca są przenoszone.
 */
$tzp_is_pins = $tzp_is_shortcode( '/\[tzp_flavor_pins\b/' );
$tzp_is_atc  = $tzp_is_widget( 'wd_single_product_add_to_cart' );

// 'yes' = piny są już w kontenerze tuż pod kontenerem z przyciskiem, 'no' = nie, false = brak przycisku.
$pins_placed = static function ( $els ) use ( &$pins_placed, $tzp_has, $tzp_is_pins, $tzp_is_atc ) {
	foreach ( array_values( $els ) as $i => $el ) {
		$kids = $el['elements'] ?? array();
		foreach ( $kids as $kid ) {
			if ( $tzp_is_atc( $kid ) ) {
				$next = array_values( $els )[ $i + 1 ] ?? null;
				return $next && $tzp_has( array( $next ), $tzp_is_pins ) ? 'yes' : 'no';
			}
		}
		if ( $kids ) {
			$found = $pins_placed( $kids );
			if ( $found ) {
				return $found;
			}
		}
	}
	return false;
};

$placed = $pins_placed( $elements );

if ( 'yes' === $placed ) {
	WP_CLI::log( '[tzp_flavor_pins] już jest pod formularzem zakupu, pomijam.' );
} elseif ( false === $placed ) {
	WP_CLI::warning( 'Brak widgetu "Dodaj do koszyka", piny nie wstawione.' );
} else {
	// Usunięcie pinów z dotychczasowego miejsca (widget zostaje ten sam) i pustych kontenerów po nich.
	$pins_widget = null;
	$pins_remove = static function ( array &$els ) use ( &$pins_remove, &$pins_widget, $tzp_is_pins ) {
		foreach ( array_keys( $els ) as $i ) {
			if ( $tzp_is_pins( $els[ $i ] ) ) {
				$pins_widget = $els[ $i ];
				unset( $els[ $i ] );
				continue;
			}
			if ( ! empty( $els[ $i ]['elements'] ) ) {
				$pins_remove( $els[ $i ]['elements'] );
				$classes = (string) ( $els[ $i ]['settings']['css_classes'] ?? '' );
				if ( ! $els[ $i ]['elements'] && false !== strpos( $classes, 'tzp-flavor-pins-box' ) ) {
					unset( $els[ $i ] );
				}
			}
		}
		$els = array_values( $els );
	};
	$pins_remove( $elements );

	$box = array(
		'id'       => $tzp_id(),
		'elType'   => 'container',
		'settings' => array(
			'content_width' => 'full',
			'css_classes'   => 'tzp-flavor-pins-box',
		),
		'elements' => array( $pins_widget ?: $tzp_shortcode_widget( '[tzp_flavor_pins]', 'tzp-flavor-pins-widget' ) ),
		'isInner'  => true,
	);

	$pins_insert = static function ( array &$els ) use ( &$pins_insert, $box, $tzp_is_atc ) {
		foreach ( array_keys( $els ) as $i ) {
			$kids = $els[ $i ]['elements'] ?? array();
			foreach ( $kids as $kid ) {
				if ( ! $tzp_is_atc( $kid ) ) {
					continue;
				}
				foreach ( $els[ $i ]['settings'] ?? array() as $key => $value ) {
					if ( preg_match( '/^(padding|margin)(_tablet|_mobile)?$/', $key ) ) {
						$box['settings'][ $key ] = $value;
					}
				}
				array_splice( $els, $i + 1, 0, array( $box ) );
				return $els[ $i ]['id'];
			}
			if ( $kids ) {
				$done = $pins_insert( $els[ $i ]['elements'] );
				if ( $done ) {
					return $done;
				}
			}
		}
		return false;
	};

	$after      = $pins_insert( $elements );
	$changes[] = 'piny ' . ( $pins_widget ? 'przeniesione' : 'wstawione' ) . ' pod kontener z ceną i "Dodaj do koszyka" ' . $after;
}

if ( ! $changes ) {
	WP_CLI::success( 'Layout ' . $tzp_layout_id . ' bez zmian.' );
	return;
}

$tzp_dir = wp_upload_dir()['basedir'] . '/tzp-backup';
wp_mkdir_p( $tzp_dir );
$tzp_backup = $tzp_dir . '/elementor-data-' . $tzp_layout_id . '-' . gmdate( 'Ymd-His' ) . '.json';
file_put_contents( $tzp_backup, get_post_meta( $tzp_layout_id, '_elementor_data', true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

// Elementor przy zapisie renderuje widgety do tekstu, a widgety karty produktu wymagają globalnego $product.
$tzp_ids = wc_get_products(
	array(
		'category' => array( 'zapiekanki' ),
		'status'   => 'publish',
		'limit'    => 1,
		'return'   => 'ids',
	)
);

if ( ! $tzp_ids ) {
	WP_CLI::error( 'Brak produktu z kategorii zapiekanki do zapisu layoutu.' );
}

$GLOBALS['post'] = get_post( $tzp_ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
setup_postdata( $GLOBALS['post'] );
$GLOBALS['product'] = wc_get_product( $tzp_ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride

$tzp_document->save( array( 'elements' => $elements ) );
wp_reset_postdata();

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );

foreach ( $changes as $change ) {
	WP_CLI::log( $change );
}
WP_CLI::success( 'Layout ' . $tzp_layout_id . ' zapisany. Kopia: ' . $tzp_backup );
