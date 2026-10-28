<?php
/**
 * Bundles (WPC Product Bundles): message fixes.
 *
 * The message shown when adding an empty bundle (WPC Bundles → Localization → "alert_empty") is set to
 * "Wybierz [min] zapiekanek przed dodaniem zestawu do koszyka.", but the plugin replaces [min] only in other
 * messages, so customers saw a literal "[min]". On the bundle page we substitute the minimum quantity from its
 * settings (woosb_limit_whole_min: 9 or 12).
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
	 * @param string $text Message text from the plugin settings.
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
