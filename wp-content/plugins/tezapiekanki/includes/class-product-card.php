<?php
/**
 * Wartość odżywcza i karta produktu (PDF) na karcie produktu.
 *
 * Shortcode [tzp_nutrition] (layout 6480, w miejscu dawnego nagłówka, przycisku "Pobierz kartę produktu" i tabeli
 * Woodmarta): tabela jak na etykiecie (wartości w 100 g przed upieczeniem, "w tym" wcięte), waga zapiekanki i wyraźny
 * przycisk do karty PDF ze składem i alergenami.
 * Shortcode [tzp_product_card]: sam przycisk do karty PDF.
 *
 * Dane: atrybuty produktu (Produkty → Edytuj → Atrybuty), klient zmienia je jak dotąd. Link do PDF-a: pole własne
 * "kartaproduktowa" (gotowy link HTML albo sam adres). Bez danych shortcode niczego nie wypisuje, więc nie ma pustej
 * tabeli ani przycisku (np. na zestawach).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Product_Card {

	const STYLE = 'tzp-product';

	/**
	 * Wiersze tabeli: taksonomia atrybutu => [etykieta, wiersz "w tym"].
	 */
	const ROWS = array(
		'pa_kalorie'        => array( 'Wartość energetyczna', false ),
		'pa_tluszcze'       => array( 'Tłuszcz', false ),
		'pa_kwasy-nasycone' => array( 'w tym kwasy tłuszczowe nasycone', true ),
		'pa_weglowodany'    => array( 'Węglowodany', false ),
		'pa_cukry'          => array( 'w tym cukry', true ),
		'pa_bialko'         => array( 'Białko', false ),
		'pa_sol'            => array( 'Sól', false ),
	);

	public static function init() {
		add_shortcode( 'tzp_nutrition', array( __CLASS__, 'nutrition_shortcode' ) );
		add_shortcode( 'tzp_product_card', array( __CLASS__, 'card_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( self::STYLE, TZP_URL . 'assets/css/product.css', array(), asset_version( 'assets/css/product.css' ) );

		// Na karcie produktu od razu w <head>, żeby tabela i piny nie mignęły bez stylów.
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_style( self::STYLE );
		}
	}

	/**
	 * @param array|string $atts product (ID, domyślnie bieżący produkt), title.
	 * @return string
	 */
	public static function nutrition_shortcode( $atts ) {
		$atts    = shortcode_atts(
			array(
				'product' => 0,
				'title'   => __( 'Wartość odżywcza', 'tezapiekanki' ),
			),
			$atts,
			'tzp_nutrition'
		);
		$product = self::product( $atts['product'] );

		if ( ! $product ) {
			return '';
		}

		$rows = '';
		foreach ( self::ROWS as $tax => $row ) {
			$value = self::value( $product, $tax );

			if ( '' === $value ) {
				continue;
			}

			$rows .= sprintf(
				'<tr%s><th scope="row">%s</th><td>%s</td></tr>',
				$row[1] ? ' class="tzp-nutrition__sub"' : '',
				esc_html( $row[0] ),
				esc_html( $value )
			);
		}

		$card = self::card_link( $product );

		if ( ! $rows && ! $card ) {
			return '';
		}

		wp_enqueue_style( self::STYLE );

		$weight = self::value( $product, 'pa_waga' );
		$id     = 'tzp-nutrition-' . $product->get_id();
		$html   = '<section class="tzp-nutrition" aria-labelledby="' . esc_attr( $id ) . '">';

		if ( $rows ) {
			$html .= '<div class="tzp-nutrition__head">';
			$html .= '<h2 class="tzp-nutrition__title" id="' . esc_attr( $id ) . '">' . esc_html( $atts['title'] ) . '</h2>';
			$html .= '<span class="tzp-nutrition__per">' . esc_html__( 'w 100 g, przed upieczeniem', 'tezapiekanki' ) . '</span>';
			$html .= '</div>';
			$html .= '<table class="tzp-nutrition__table">' . $rows . '</table>';
		}

		if ( $weight || $card ) {
			$html .= '<div class="tzp-nutrition__foot">';
			if ( $weight ) {
				/* translators: %s: waga, np. "349 g" */
				$html .= '<p class="tzp-nutrition__weight">' . sprintf( esc_html__( 'Waga zapiekanki: %s', 'tezapiekanki' ), '<strong>' . esc_html( $weight ) . '</strong>' ) . '</p>';
			}
			$html .= $card;
			$html .= '</div>';
		}

		return $html . '</section>';
	}

	/**
	 * @param array|string $atts product (ID, domyślnie bieżący produkt).
	 * @return string
	 */
	public static function card_shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'product' => 0 ), $atts, 'tzp_product_card' );
		$product = self::product( $atts['product'] );
		$card    = $product ? self::card_link( $product ) : '';

		if ( $card ) {
			wp_enqueue_style( self::STYLE );
		}

		return $card;
	}

	/**
	 * Adres PDF-a z pola "kartaproduktowa": gotowy link <a href="…">…</a> (tak jest od początku) albo sam adres.
	 *
	 * @param \WC_Product $product Produkt.
	 * @return string
	 */
	public static function card_url( $product ) {
		$value = trim( (string) $product->get_meta( 'kartaproduktowa' ) );

		if ( preg_match( '/href=["\']([^"\']+)["\']/i', $value, $m ) ) {
			$value = html_entity_decode( $m[1] );
		}

		return wp_http_validate_url( $value ) ? $value : '';
	}

	/**
	 * @param \WC_Product $product Produkt.
	 * @return string
	 */
	private static function card_link( $product ) {
		$url = (string) apply_filters( 'tzp_product_card_url', self::card_url( $product ), $product );

		if ( ! $url ) {
			return '';
		}

		$icon = '<svg class="tzp-card-link__icon" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M8.5 13.5h7M8.5 17h5"/></svg>';

		return sprintf(
			'<a class="tzp-card-link" href="%1$s" target="_blank" rel="noopener">%2$s<span class="tzp-card-link__text"><span class="tzp-card-link__title">%3$s</span><span class="tzp-card-link__desc">%4$s</span></span></a>',
			esc_url( $url ),
			$icon,
			esc_html__( 'Pobierz kartę produktu (PDF)', 'tezapiekanki' ),
			esc_html__( 'pełny skład, alergeny i wartości odżywcze', 'tezapiekanki' )
		);
	}

	/**
	 * @param \WC_Product $product Produkt.
	 * @param string      $tax     Taksonomia atrybutu.
	 * @return string
	 */
	private static function value( $product, $tax ) {
		return trim( wp_strip_all_tags( (string) $product->get_attribute( $tax ) ) );
	}

	/**
	 * @param int|string $id ID produktu albo 0 = bieżący.
	 * @return \WC_Product|null
	 */
	private static function product( $id ) {
		$id = absint( $id );

		if ( ! $id ) {
			global $product;

			if ( $product instanceof \WC_Product ) {
				return $product;
			}

			$id = get_the_ID();
		}

		$found = $id ? wc_get_product( $id ) : null;

		return $found instanceof \WC_Product ? $found : null;
	}
}
