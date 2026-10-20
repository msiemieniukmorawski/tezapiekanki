<?php
/**
 * Piny smaków na karcie produktu: klikalne nazwy pozostałych zapiekanek pod tytułem (layout 6480).
 *
 * Shortcode [tzp_flavor_pins]. Lista to widoczne produkty z kategorii Zapiekanki (bez ukrytych kopii do zestawów)
 * w kolejności ze sklepu (Rearrange Products, meta rwpp_sortorder_{ID kategorii}), więc nowy smak pojawia się sam.
 * Bieżący smak wyróżniony, niedostępne wyszarzone, ale klikalne (można sprawdzić opis i wrócić później).
 * Na telefonie jeden przewijany wiersz, skrypt przewija go do bieżącego smaku.
 *
 * Atrybuty: category (slug kategorii, domyślnie "zapiekanki"), label (tekst przed pinami).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Flavor_Pins {

	const SCRIPT = 'tzp-flavor-pins';

	public static function init() {
		add_shortcode( 'tzp_flavor_pins', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_script( self::SCRIPT, TZP_URL . 'assets/js/flavor-pins.js', array(), asset_version( 'assets/js/flavor-pins.js' ), true );
	}

	/**
	 * @param array|string $atts category, label.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => 'zapiekanki',
				'label'    => __( 'Smak:', 'tezapiekanki' ),
			),
			$atts,
			'tzp_flavor_pins'
		);

		$term = get_term_by( 'slug', sanitize_title( $atts['category'] ), 'product_cat' );

		if ( ! $term ) {
			return '';
		}

		$products = self::products( $term );

		if ( count( $products ) < 2 ) {
			return '';
		}

		$current = get_queried_object_id();
		$items   = '';

		foreach ( $products as $product ) {
			$is_current = $product->get_id() === $current;
			$is_out     = ! $product->is_in_stock();
			$classes    = 'tzp-flavors__pin' . ( $is_current ? ' is-current' : '' ) . ( $is_out ? ' is-out' : '' );

			$items .= sprintf(
				'<li><a class="%1$s" href="%2$s"%3$s%4$s>%5$s%6$s</a></li>',
				esc_attr( $classes ),
				esc_url( $product->get_permalink() ),
				$is_current ? ' aria-current="page"' : '',
				$is_out ? ' title="' . esc_attr__( 'Chwilowo niedostępna', 'tezapiekanki' ) . '"' : '',
				esc_html( self::short_name( $product->get_name() ) ),
				$is_out ? '<span class="screen-reader-text"> ' . esc_html__( '(chwilowo niedostępna)', 'tezapiekanki' ) . '</span>' : ''
			);
		}

		wp_enqueue_style( Product_Card::STYLE );
		wp_enqueue_script( self::SCRIPT );

		return sprintf(
			'<nav class="tzp-flavors" aria-label="%1$s"><span class="tzp-flavors__label" aria-hidden="true">%2$s</span><ul class="tzp-flavors__list">%3$s</ul></nav>',
			esc_attr__( 'Smaki zapiekanek', 'tezapiekanki' ),
			esc_html( $atts['label'] ),
			$items
		);
	}

	/**
	 * "Zapiekanka z boczkiem" → "Z boczkiem".
	 *
	 * @param string $name Nazwa produktu.
	 * @return string
	 */
	public static function short_name( $name ) {
		$short = trim( preg_replace( '/^zapiekanka\s+/iu', '', $name ) );

		if ( '' === $short ) {
			return $name;
		}

		return mb_strtoupper( mb_substr( $short, 0, 1 ) ) . mb_substr( $short, 1 );
	}

	/**
	 * Widoczne produkty kategorii w kolejności ze sklepu.
	 *
	 * @param \WP_Term $term Kategoria.
	 * @return \WC_Product[]
	 */
	private static function products( $term ) {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => 50,
				'category'   => array( $term->slug ),
				'visibility' => 'catalog',
				'orderby'    => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		$order_key = 'rwpp_sortorder_' . $term->term_id;

		usort(
			$products,
			static function ( $a, $b ) use ( $order_key ) {
				$oa = $a->get_meta( $order_key );
				$ob = $b->get_meta( $order_key );
				$oa = '' === $oa ? $a->get_menu_order() : (int) $oa;
				$ob = '' === $ob ? $b->get_menu_order() : (int) $ob;

				return $oa <=> $ob ?: strcmp( $a->get_name(), $b->get_name() );
			}
		);

		return $products;
	}
}
