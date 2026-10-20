<?php
/**
 * Maile i adresy w zamówieniu: poprawki wyglądu.
 *
 * 1. Adres kontaktowy w mailach do klientów. Stopka maili ("skontaktuj się z nami pod adresem {store_email}") pokazuje
 *    adres nadawcy, a nadawcą jest noreply@… (adres ze skrzynki SMTP, nie do zmiany bez ryzyka dla doręczalności).
 *    Gdy w WooCommerce → Ustawienia → E-maile jest włączony "Adres odpowiedzi" (Reply-to), w treści maili do klientów
 *    zamiast adresu nadawcy pokazujemy adres odpowiedzi. Maile do sklepu (nowe, anulowane, nieudane zamówienie)
 *    zostają bez zmian, tam WooCommerce ustawia odpowiedź na adres klienta.
 * 2. Puste pola Flexible Checkout Fields w adresie. FCF dopisuje do adresu "Nazwa firmy: " i "Numer NIP: " także wtedy,
 *    gdy klient ich nie wypełnił (bez faktury). Takie linie czyścimy, a WooCommerce usuwa puste linie z adresu.
 *    Dotyczy maili, strony podziękowania, Moje konto i panelu zamówienia.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Emails {

	// Maile do sklepu: odpowiedź idzie do klienta (WooCommerce), adresu nie podmieniamy.
	const ADMIN_EMAILS = array( 'new_order', 'cancelled_order', 'failed_order' );

	public static function init() {
		add_filter( 'woocommerce_email_format_string', array( __CLASS__, 'contact_address' ), 10, 2 );
		// Po FCF (priorytet 999999).
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'drop_empty_labels' ), PHP_INT_MAX );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'drop_empty_labels' ), PHP_INT_MAX );
	}

	/**
	 * @param string    $text  Tekst maila po podstawieniu znaczników.
	 * @param \WC_Email $email Mail.
	 * @return string
	 */
	public static function contact_address( $text, $email ) {
		if ( ! is_string( $text ) || ! is_object( $email ) || ! method_exists( $email, 'get_reply_to_enabled' ) ) {
			return $text;
		}

		if ( in_array( $email->id ?? '', self::ADMIN_EMAILS, true ) || ! $email->get_reply_to_enabled() ) {
			return $text;
		}

		$reply = (string) $email->get_reply_to_address();
		$from  = (string) $email->get_from_address();

		if ( ! is_email( $reply ) || '' === $from || $reply === $from || false === strpos( $text, $from ) ) {
			return $text;
		}

		return str_replace( $from, $reply, $text );
	}

	/**
	 * @param array|mixed $fields Pola adresu (po FCF).
	 * @return array|mixed
	 */
	public static function drop_empty_labels( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( self::fcf_labels() as $name => $label ) {
			$keys = array( $name, preg_replace( '/^(billing|shipping)_/', '', $name ) );

			foreach ( $keys as $key ) {
				if ( isset( $fields[ $key ] ) && is_string( $fields[ $key ] ) && trim( wp_strip_all_tags( $fields[ $key ] ) ) === $label . ':' ) {
					$fields[ $key ] = '';
				}
			}
		}

		return $fields;
	}

	/**
	 * Własne pola FCF (adres rozliczeniowy i wysyłki): nazwa pola => etykieta.
	 *
	 * @return array
	 */
	private static function fcf_labels() {
		static $labels = null;

		if ( null !== $labels ) {
			return $labels;
		}

		$labels   = array();
		$settings = get_option( 'wpdesk_checkout_fields_settings' );

		foreach ( array( 'billing', 'shipping' ) as $section ) {
			foreach ( (array) ( $settings[ $section ] ?? array() ) as $name => $field ) {
				if ( '1' === (string) ( $field['custom_field'] ?? '' ) && ! empty( $field['label'] ) ) {
					$labels[ $name ] = trim( wp_strip_all_tags( $field['label'] ) );
				}
			}
		}

		return $labels;
	}
}
