<?php
/**
 * Zestawy (WPC Product Bundles): poprawki komunikatów.
 *
 * Komunikat przy próbie dodania pustego zestawu (WPC Bundles → Localization → "alert_empty") ma w ustawieniach tekst
 * "Wybierz [min] zapiekanek przed dodaniem zestawu do koszyka.", ale wtyczka podmienia [min] tylko w innych
 * komunikatach, więc klient widział dosłownie "[min]". Na karcie zestawu podstawiamy minimalną liczbę sztuk z jego
 * ustawień (woosb_limit_whole_min: 9 albo 12).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Bundles {

	public static function init() {
		add_filter( 'woosb_localization_alert_empty', array( __CLASS__, 'alert_empty' ) );
	}

	/**
	 * @param string $text Tekst komunikatu z ustawień wtyczki.
	 * @return string
	 */
	public static function alert_empty( $text ) {
		if ( false === strpos( (string) $text, '[min]' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $text;
		}

		$min = (int) get_post_meta( get_queried_object_id(), 'woosb_limit_whole_min', true );

		return $min > 0 ? str_replace( '[min]', (string) $min, $text ) : $text;
	}
}
