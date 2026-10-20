<?php
/**
 * Dane strukturalne produktu (Product/Offer) generowane przez Rank Math: uzupełnienia pod "Merchant listings" Google.
 *
 * Rank Math (darmowy) wypisuje Product z Offer, ale:
 * - nie ma kosztu i czasu dostawy (shippingDetails) ani zasad zwrotów (hasMerchantReturnPolicy), Google zgłasza to jako
 *   brakujące pola,
 * - nazwa produktu to tytuł SEO z nazwą sklepu ("Zapiekanka z salami - TE Zapiekanki Skalbmierskie"),
 * - waga pochodzi z pola wysyłkowego (np. "1 kg" przy zapiekance 404 g), a atrybuty są podpisane slugami ("pa_waga").
 *
 * Tu: nazwa produktu z WooCommerce, waga z atrybutu "Waga", podpisy atrybutów, marka, wysyłka (koszt jak w Flexible
 * Shipping, czas wysyłki i dostawy z silnika terminów wc-ship-today-notice) i zwroty (odstąpienie od umowy nie
 * przysługuje: produkt szybko się psuje, strona "Zwroty i reklamacje").
 *
 * Filtry: tzp_product_schema (cała encja), tzp_product_schema_shipping_rate (koszt, domyślnie 14,99 zł),
 * tzp_product_schema_brand.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Product_Schema {

	// Najniższy płatny koszt wysyłki (InPost Paczkomat i Kurier, Flexible Shipping, stan z 20.10.2026; darmowa od 300 zł).
	const SHIPPING_RATE = '14.99';

	const DELIVERY_CACHE = 'tzp_schema_delivery_time';

	// Atrybuty z wartościami w 100 g (w danych dla Google podpis z dopiskiem).
	const PER_100G = array( 'pa_kalorie', 'pa_tluszcze', 'pa_kwasy-nasycone', 'pa_weglowodany', 'pa_cukry', 'pa_bialko', 'pa_sol' );

	public static function init() {
		add_filter( 'rank_math/snippet/rich_snippet_product_entity', array( __CLASS__, 'product' ), 20 );
		// Czas dostawy liczy się z ustawień terminów, więc po ich zmianie liczymy go od nowa.
		add_action( 'update_option_wc_ship_today_notice_options', array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		delete_transient( self::DELIVERY_CACHE );
	}

	/**
	 * @param array $entity Encja Product z Rank Math.
	 * @return array
	 */
	public static function product( $entity ) {
		if ( ! is_array( $entity ) || ! function_exists( 'wc_get_product' ) ) {
			return $entity;
		}

		$product = wc_get_product( get_queried_object_id() ?: get_the_ID() );

		if ( ! $product ) {
			return $entity;
		}

		$entity['name'] = $product->get_name();

		$weight = (int) preg_replace( '/\D+/', '', $product->get_attribute( 'pa_waga' ) );
		if ( $weight ) {
			$entity['weight'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $weight,
				'unitCode' => 'GRM',
			);
		}

		if ( ! empty( $entity['additionalProperty'] ) && is_array( $entity['additionalProperty'] ) ) {
			foreach ( $entity['additionalProperty'] as &$property ) {
				$key = $property['name'] ?? '';

				if ( 0 !== strpos( $key, 'pa_' ) ) {
					continue;
				}

				$property['name'] = wc_attribute_label( $key, $product ) . ( in_array( $key, self::PER_100G, true ) ? ' (w 100 g)' : '' );
			}
			unset( $property );
		}

		if ( empty( $entity['brand'] ) ) {
			$entity['brand'] = array(
				'@type' => 'Brand',
				'name'  => (string) apply_filters( 'tzp_product_schema_brand', 'TE Zapiekanki Skalbmierskie', $product ),
			);
		}

		if ( isset( $entity['offers'] ) && is_array( $entity['offers'] ) ) {
			if ( isset( $entity['offers']['@type'] ) ) {
				$entity['offers'] = self::offer( $entity['offers'], $product );
			} else {
				foreach ( $entity['offers'] as &$offer ) {
					$offer = is_array( $offer ) ? self::offer( $offer, $product ) : $offer;
				}
				unset( $offer );
			}
		}

		return apply_filters( 'tzp_product_schema', $entity, $product );
	}

	/**
	 * @param array       $offer   Offer z Rank Math.
	 * @param \WC_Product $product Produkt.
	 * @return array
	 */
	private static function offer( $offer, $product ) {
		$rate     = (string) apply_filters( 'tzp_product_schema_shipping_rate', self::SHIPPING_RATE, $product );
		$shipping = array(
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => array(
				'@type'    => 'MonetaryAmount',
				'value'    => $rate,
				'currency' => 'PLN',
			),
			'shippingDestination' => array(
				'@type'          => 'DefinedRegion',
				'addressCountry' => 'PL',
			),
		);

		$time = self::delivery_time();
		if ( $time ) {
			$shipping['deliveryTime'] = array(
				'@type'        => 'ShippingDeliveryTime',
				// Dni kalendarzowe (wysyłka w wybrane dni tygodnia, dostawa także po weekendzie).
				'businessDays' => array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array(
						'https://schema.org/Monday',
						'https://schema.org/Tuesday',
						'https://schema.org/Wednesday',
						'https://schema.org/Thursday',
						'https://schema.org/Friday',
						'https://schema.org/Saturday',
						'https://schema.org/Sunday',
					),
				),
				'handlingTime' => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => $time['handling'][0],
					'maxValue' => $time['handling'][1],
					'unitCode' => 'DAY',
				),
				'transitTime'  => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => $time['transit'][0],
					'maxValue' => $time['transit'][1],
					'unitCode' => 'DAY',
				),
			);
		}

		$offer['shippingDetails'] = $shipping;

		$policy = array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => 'PL',
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
		);

		$returns = get_page_by_path( 'zwroty-i-reklamacje' );
		if ( $returns && 'publish' === $returns->post_status ) {
			$policy['merchantReturnLink'] = get_permalink( $returns );
		}

		$offer['hasMerchantReturnPolicy'] = $policy;

		return $offer;
	}

	/**
	 * Najkrótszy i najdłuższy czas od zamówienia do wysyłki i od wysyłki do dostawy (dni kalendarzowe), policzony z
	 * terminów wc-ship-today-notice dla zamówień składanych co godzinę przez najbliższy tydzień. Cache 12 h.
	 *
	 * @return array|null ['handling' => [min, max], 'transit' => [min, max]]
	 */
	public static function delivery_time() {
		$cached = get_transient( self::DELIVERY_CACHE );

		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}

		$result = array();

		if ( function_exists( 'wc_stn_get_slots' ) ) {
			$handling = array();
			$transit  = array();
			$start    = new \DateTimeImmutable( 'today', wp_timezone() );

			for ( $hour = 0; $hour < 7 * 24; $hour++ ) {
				$now   = $start->modify( '+' . $hour . ' hours' );
				$slots = wc_stn_get_slots( 1, $now );

				if ( empty( $slots[0]['ship'] ) ) {
					continue;
				}

				$handling[] = (int) $now->setTime( 0, 0 )->diff( $slots[0]['ship']->setTime( 0, 0 ) )->days;

				if ( ! empty( $slots[0]['delivery'] ) ) {
					$transit[] = (int) $slots[0]['ship']->setTime( 0, 0 )->diff( $slots[0]['delivery']->setTime( 0, 0 ) )->days;
				}
			}

			if ( $handling && $transit ) {
				$result = array(
					'handling' => array( min( $handling ), max( $handling ) ),
					'transit'  => array( min( $transit ), max( $transit ) ),
				);
			}
		}

		// Pusta tablica w cache = brak danych (bez liczenia przy każdym wyświetleniu).
		set_transient( self::DELIVERY_CACHE, $result, 12 * HOUR_IN_SECONDS );

		return $result ?: null;
	}
}
