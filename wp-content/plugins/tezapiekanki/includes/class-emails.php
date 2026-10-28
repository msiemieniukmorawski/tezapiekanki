<?php
/**
 * Emails and order addresses: display fixes.
 *
 * 1. Contact address in customer emails. The email footer ("skontaktuj się z nami pod adresem {store_email}") shows
 *    the sender address, and the sender is noreply@… (the SMTP mailbox address, can't be changed without risking deliverability).
 *    When "Reply-to address" is enabled in WooCommerce → Settings → Emails, customer emails show the reply-to
 *    address instead of the sender address. Emails to the shop (new, cancelled, failed order)
 *    stay unchanged; there WooCommerce sets the reply-to to the customer's address.
 * 2. Empty Flexible Checkout Fields in the address. FCF appends "Nazwa firmy: " and "Numer NIP: " to the address even
 *    when the customer left them empty (no invoice). We clear such lines, and WooCommerce removes empty lines from the address.
 *    Applies to emails, the thank-you page, My Account and the order admin screen.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Emails {

	// Emails to the shop: replies go to the customer (WooCommerce), we don't replace the address.
	const ADMIN_EMAILS = array( 'new_order', 'cancelled_order', 'failed_order' );

	public static function init() {
		add_filter( 'woocommerce_email_format_string', array( __CLASS__, 'contact_address' ), 10, 2 );
		// After FCF (priority 999999).
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'drop_empty_labels' ), PHP_INT_MAX );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'drop_empty_labels' ), PHP_INT_MAX );
	}

	/**
	 * @param string    $text  Email text after placeholder substitution.
	 * @param \WC_Email $email Email.
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
	 * @param array|mixed $fields Address fields (after FCF).
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
	 * Custom FCF fields (billing and shipping address): field name => label.
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
