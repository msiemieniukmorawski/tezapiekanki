<?php
/**
 * Shipping settings after the client's feedback of 2026-10-26: cutoff 23:59, shipping always 14.99 PLN (free from 300 PLN).
 *
 * Usage (WP-CLI):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shipping-settings.php           (preview)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/update-shipping-settings.php apply     (save)
 *
 * 1. wc-ship-today-notice: cutoff 23:59 for every shipping day (was 20:00). Day offsets stay the same:
 *    by Sunday 23:59 → shipped Monday, by Tuesday 23:59 → shipped Wednesday. In the admin: WooCommerce →
 *    Ship Today Notice, cutoff times table.
 * 2. Flexible Shipping: in every flexible_shipping_single method a single "Zawsze" (always) rule of 14.99 PLN instead of
 *    weight rules (0-4 kg 14.99 PLN, from 5 kg 9.99 PLN; a 4-5 kg cart matched no rule and got 0 PLN shipping).
 *    Free shipping from 300 PLN is a separate method setting ("Darmowa wysyłka") and stays unchanged.
 *    In the admin: WooCommerce → Settings → Shipping → Polska → method → Rules: a single "Zawsze" rule, cost 14.99.
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply  = in_array( 'apply', (array) ( $args ?? array() ), true );
$tzp_cutoff = '23:59';
$tzp_cost   = '14.99';
$changes    = 0;

/*
 * 1. Cutoff time.
 */
$tzp_stn = get_option( 'wc_ship_today_notice_options' );

if ( ! is_array( $tzp_stn ) ) {
	WP_CLI::warning( 'Brak ustawień wc-ship-today-notice, pomijam godzinę graniczną.' );
} else {
	$times = (array) ( $tzp_stn['cutoff_times'] ?? array() );
	$new   = array_fill_keys( range( 1, 7 ), $tzp_cutoff );

	if ( $times != $new ) { // phpcs:ignore Universal.Operators.StrictComparisons
		WP_CLI::log( 'Godzina graniczna: ' . implode( ', ', array_unique( $times ) ) . ' → ' . $tzp_cutoff );
		$changes++;
		if ( $tzp_apply ) {
			$tzp_stn['cutoff_times'] = $new;
			update_option( 'wc_ship_today_notice_options', $tzp_stn );
			// The shipping time in the product data for Google (class-product-schema.php) is computed from the schedule.
			delete_transient( 'tzp_schema_delivery_time' );
		}
	} else {
		WP_CLI::log( 'Godzina graniczna: już ' . $tzp_cutoff . '.' );
	}
}

/*
 * 2. Flexible Shipping rules.
 */
$tzp_rules = wp_json_encode(
	array(
		array(
			'conditions'       => array( array( 'condition_id' => 'none' ) ),
			'cost_per_order'   => $tzp_cost,
			'additional_costs' => array(),
			'special_action'   => '',
		),
	),
	JSON_PRETTY_PRINT
);

$tzp_zones   = WC_Shipping_Zones::get_zones();
$tzp_zones[] = array( 'shipping_methods' => ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods() );

foreach ( $tzp_zones as $zone ) {
	foreach ( $zone['shipping_methods'] as $method ) {
		if ( 'flexible_shipping_single' !== $method->id ) {
			continue;
		}

		$key      = $method->get_instance_option_key();
		$settings = get_option( $key );
		$rules    = json_decode( (string) ( $settings['method_rules'] ?? '' ), true );

		if ( json_decode( $tzp_rules, true ) === $rules ) {
			WP_CLI::log( sprintf( 'Metoda %d "%s": już jedna reguła %s zł.', $method->instance_id, $method->get_title(), $tzp_cost ) );
			continue;
		}

		$old = array();
		foreach ( (array) $rules as $rule ) {
			$condition = $rule['conditions'][0] ?? array();
			$old[]     = sprintf( '%s %s-%s: %s', $condition['condition_id'] ?? '?', $condition['min'] ?? '', $condition['max'] ?? '', $rule['cost_per_order'] ?? '' );
		}
		WP_CLI::log( sprintf( 'Metoda %d "%s": [%s] → zawsze %s zł (darmowa od: %s zł)', $method->instance_id, $method->get_title(), implode( '; ', $old ), $tzp_cost, $settings['method_free_shipping'] ?? '-' ) );
		$changes++;

		if ( $tzp_apply ) {
			$settings['method_rules'] = $tzp_rules;
			update_option( $key, $settings );
		}
	}
}

if ( ! $tzp_apply ) {
	WP_CLI::success( $changes . ' zmian do zrobienia (podgląd). Zapis: dodaj argument "apply".' );
	return;
}

if ( class_exists( 'WC_Cache_Helper' ) ) {
	WC_Cache_Helper::get_transient_version( 'shipping', true );
}
do_action( 'litespeed_purge_all' );
WP_CLI::success( $changes . ' zmian zapisanych.' );
