<?php
/**
 * Checkout TeZapiekanki (layout Woodmarta 6470, wariant B z propozycji z 18.10.2026).
 *
 * - [tzp_checkout_delivery]: kafelki metod wysyłki i przycisk paczkomatu w sekcji "Dostawa",
 * - pole kodu rabatowego w podsumowaniu, nad "Łącznie",
 * - kwota na przycisku "Kupuję i płacę".
 *
 * Bez usuwania hooków WooCommerce i bez nadpisywania szablonów: oryginalne pola wyboru wysyłki
 * i przycisk mapy (Apaczka albo InPost PL) zostają w tabeli podsumowania (ukryte wizualnie w style.css). Kafelki to etykiety
 * <label for> podpięte do oryginalnych pól, a nasz przycisk uruchamia oryginalny przycisk mapy (js/checkout.js),
 * więc walidacja, zapis zamówienia i mapa działają bez zmian.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'tzp_checkout_delivery', 'tzp_checkout_delivery_shortcode' );
add_filter( 'woocommerce_update_order_review_fragments', 'tzp_checkout_delivery_fragment' );
add_action( 'woocommerce_review_order_before_order_total', 'tzp_checkout_coupon_row' );
add_filter( 'woocommerce_order_button_html', 'tzp_checkout_order_button_with_total' );
add_action( 'wp_enqueue_scripts', 'tzp_checkout_assets' );

/**
 * Czy layout checkoutu jest już przebudowany (zawiera [tzp_checkout_delivery]).
 * Pozwala wgrać motyw potomny przed przebudową layoutu 6470: na starym checkoucie nie pojawi się
 * drugie pole kuponu ani kwota na przycisku.
 */
function tzp_checkout_layout_ready() {
	static $ready = null;

	if ( null !== $ready ) {
		return $ready;
	}

	$ready   = false;
	$layouts = get_posts(
		array(
			'post_type'   => 'woodmart_layout',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => 'wd_layout_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'checkout_form', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	foreach ( $layouts as $layout_id ) {
		if ( false !== strpos( (string) get_post_meta( $layout_id, '_elementor_data', true ), 'tzp_checkout_delivery' ) ) {
			$ready = true;
			break;
		}
	}

	return $ready;
}

/**
 * Skrypt checkoutu tylko na stronie zamówienia.
 */
function tzp_checkout_assets() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! tzp_checkout_layout_ready() ) {
		return;
	}

	$file = get_stylesheet_directory() . '/js/checkout.js';
	wp_enqueue_script( 'tzp-checkout', get_stylesheet_directory_uri() . '/js/checkout.js', array( 'jquery', 'wc-checkout' ), (string) filemtime( $file ), true );
}

function tzp_checkout_delivery_shortcode() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}

	return tzp_checkout_delivery_html();
}

/**
 * Sekcja odświeżana razem z checkoutem (ceny i dostępne metody mogą się zmienić po zmianie adresu).
 */
function tzp_checkout_delivery_fragment( $fragments ) {
	if ( ! tzp_checkout_layout_ready() ) {
		return $fragments;
	}

	$fragments['.tzp-delivery-methods'] = tzp_checkout_delivery_html();

	return $fragments;
}

/**
 * Kafelki metod wysyłki. Identyfikatory pól jak w szablonie WooCommerce cart/cart-shipping.php.
 */
function tzp_checkout_delivery_html() {
	$html = '<div class="tzp-delivery-methods">';

	if ( ! WC()->cart->needs_shipping() ) {
		return $html . '</div>';
	}

	$packages = WC()->shipping()->get_packages();

	if ( empty( $packages ) && ! wp_doing_ajax() ) {
		// Sekcja renderuje się przed podsumowaniem, więc koszyk może nie być jeszcze przeliczony (to samo robi WooCommerce przed tabelą).
		WC()->cart->calculate_totals();
		$packages = WC()->shipping()->get_packages();
	}

	$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : array();

	if ( empty( $packages ) ) {
		return $html . '</div>';
	}

	foreach ( $packages as $index => $package ) {
		$rates = isset( $package['rates'] ) ? $package['rates'] : array();

		if ( ! $rates ) {
			$html .= '<p class="tzp-delivery-methods__empty">Uzupełnij adres, aby zobaczyć metody dostawy.</p>';
			continue;
		}

		$current = isset( $chosen[ $index ] ) && isset( $rates[ $chosen[ $index ] ] ) ? $chosen[ $index ] : key( $rates );

		$html .= '<div class="tzp-delivery-methods__tiles">';
		foreach ( $rates as $rate_id => $rate ) {
			$description = method_exists( $rate, 'get_description' ) ? (string) $rate->get_description() : '';
			$html       .= sprintf(
				'<label class="tzp-method%1$s" for="%2$s"><span class="tzp-method__name">%3$s</span><span class="tzp-method__price">%4$s</span>%5$s</label>',
				$rate_id === $current ? ' is-checked' : '',
				esc_attr( sprintf( 'shipping_method_%d_%s', $index, sanitize_title( $rate_id ) ) ),
				esc_html( $rate->get_label() ),
				wp_kses_post( tzp_checkout_rate_cost( $rate ) ),
				'' !== $description ? '<span class="tzp-method__desc">' . esc_html( wp_strip_all_tags( $description ) ) . '</span>' : ''
			);
		}
		$html .= '</div>';

		// Paczkomat: nasz przycisk uruchamia oryginalny przycisk wtyczki przewoźnika (js/checkout.js, parcelProviders).
		$provider = tzp_checkout_parcel_provider( $rates[ $current ] );
		if ( '' !== $provider ) {
			$html .= sprintf(
				'<div class="tzp-parcel" data-provider="%s">'
				. '<button type="button" class="button tzp-parcel__button">Wybierz paczkomat</button>'
				. '<div class="tzp-parcel__selected" role="status" aria-live="polite" hidden></div>'
				. '</div>',
				esc_attr( $provider )
			);
		}
	}

	return $html . '</div>';
}

function tzp_checkout_rate_cost( $rate ) {
	$cost = (float) $rate->get_cost();

	if ( WC()->cart->display_prices_including_tax() ) {
		$cost += array_sum( (array) $rate->get_taxes() );
	}

	return $cost > 0 ? wc_price( $cost ) : 'za darmo';
}

/**
 * Która wtyczka obsługuje wybór paczkomatu dla tej metody wysyłki: 'inpost', 'apaczka' albo '' (bez paczkomatu).
 *
 * - InPost PL (iLabs, inpost-for-woocommerce): metoda easypack_parcel_machines* albo metoda Flexible Shipping
 *   powiązana z nią w ustawieniu "Integracja z InPost PL" (fs_inpost_pl_method). Tak samo sprawdza to wtyczka.
 * - Apaczka: metoda z zaznaczonym "display_apaczka_map".
 *
 * Gdy metoda jest podpięta pod obie wtyczki, wygrywa InPost PL. Inną wtyczkę można dodać filtrem
 * tzp_checkout_parcel_provider i wpisem w parcelProviders w js/checkout.js.
 */
function tzp_checkout_parcel_provider( $rate ) {
	$method_id = $rate->get_method_id();
	$settings  = get_option( sprintf( 'woocommerce_%s_%d_settings', $method_id, $rate->get_instance_id() ) );
	$settings  = is_array( $settings ) ? $settings : array();
	$provider  = '';

	if ( class_exists( '\InspireLabs\WoocommerceInpost\EasyPack' ) ) {
		$inpost_method = $method_id;
		if ( 'flexible_shipping_single' === $method_id && ! empty( $settings['fs_inpost_pl_method'] ) ) {
			$inpost_method = (string) $settings['fs_inpost_pl_method'];
		}
		if ( 0 === strpos( $inpost_method, 'easypack_parcel_machines' ) ) {
			$provider = 'inpost';
		}
	}

	if ( '' === $provider && function_exists( 'apaczka' ) && isset( $settings['display_apaczka_map'] ) && 'yes' === $settings['display_apaczka_map'] ) {
		$provider = 'apaczka';
	}

	return (string) apply_filters( 'tzp_checkout_parcel_provider', $provider, $rate );
}

/**
 * Kod rabatowy nad "Łącznie". Pole bez atrybutu name (nie trafia do zamówienia),
 * kod zatwierdza js/checkout.js przez wbudowany endpoint WooCommerce apply_coupon.
 */
function tzp_checkout_coupon_row() {
	if ( ! wc_coupons_enabled() || ! tzp_checkout_layout_ready() ) {
		return;
	}
	?>
	<tr class="tzp-coupon">
		<td colspan="2">
			<details class="tzp-coupon__box">
				<summary>Masz kod rabatowy?</summary>
				<div class="tzp-coupon__row">
					<label class="screen-reader-text" for="tzp_coupon_code">Kod rabatowy</label>
					<input type="text" id="tzp_coupon_code" class="input-text" placeholder="Wpisz kod" autocomplete="off" />
					<button type="button" class="button tzp-coupon__apply">Zastosuj</button>
				</div>
				<div class="tzp-coupon__msg" role="status" aria-live="polite"></div>
			</details>
		</td>
	</tr>
	<?php
}

/**
 * "Kupuję i płacę · 39,99 zł". data-value też z kwotą, bo WooCommerce przywraca z niego tekst po zmianie płatności.
 */
function tzp_checkout_order_button_with_total( $html ) {
	if ( ! WC()->cart || ! tzp_checkout_layout_ready() ) {
		return $html;
	}

	$total = trim( html_entity_decode( wp_strip_all_tags( WC()->cart->get_total() ), ENT_QUOTES, 'UTF-8' ) );

	return preg_replace_callback(
		'/(data-value=")([^"]*)(")|(>)([^<]+)(<\/button>)/u',
		function ( $m ) use ( $total ) {
			if ( '' !== $m[1] ) {
				return $m[1] . esc_attr( $m[2] . ' · ' . $total ) . $m[3];
			}
			return $m[4] . esc_html( trim( $m[5] ) . ' · ' . $total ) . $m[6];
		},
		$html
	);
}
