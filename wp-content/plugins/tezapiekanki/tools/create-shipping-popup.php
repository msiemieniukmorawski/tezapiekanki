<?php
/**
 * Creates (or overwrites) the Woodmart popup "Dlaczego wysyłamy tylko 2 razy w tygodniu?" for the shipping bar.
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/create-shipping-popup.php --user=<login administratora>
 *
 * The popup is saved via the Elementor documents API (no SQL), so the client can later edit it normally
 * in Elementor: Popups menu. The script also sets it as the bar popup (Appearance → Customize → "Belka o wysyłce").
 * Settings:
 * - no stored trigger: the plugin attaches the bar click on the fly (Shipping_Bar::popup_trigger), so once
 *   another popup is chosen in Customize, this one stops opening from the bar,
 * - conditions: everywhere except checkout (the bar is not shown there),
 * - "Persistent close" disabled (enabled by default in Woodmart: after the first close the popup would never
 *   open again and the bar link would stop working), "after time" trigger disabled (enabled by default with 0 s,
 *   i.e. the popup would open by itself on every visit),
 * - requires the wd_popup type registration fix from the child theme (inc/woodmart-popups.php), otherwise with
 *   Elementor 4.3 Woodmart ignores the popup settings from Elementor,
 * - nearest date: [ship_today_notice] from the WooCommerce Ship Today Notice plugin (computed in the browser, works on
 *   cached pages, rolls over to the next slot by itself after the cutoff).
 *
 * Re-running overwrites the content and settings of the popup marked with meta _tzp_popup = shipping-why.
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

if ( ! class_exists( '\Elementor\Plugin' ) || ! post_type_exists( 'wd_popup' ) ) {
	WP_CLI::error( 'Wymagany Elementor i Woodmart (typ wpisu wd_popup).' );
}

if ( ! current_user_can( 'edit_posts' ) ) {
	WP_CLI::error( 'Uruchom z --user=<administrator>.' );
}

$tzp_id = static function () {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
};

$tzp_px = static function ( $size ) {
	return array(
		'unit'  => 'px',
		'size'  => $size,
		'sizes' => array(),
	);
};

$tzp_text = static function ( $html ) use ( $tzp_id, $tzp_px ) {
	return array(
		'id'         => $tzp_id(),
		'elType'     => 'widget',
		'widgetType' => 'wd_text_block',
		'settings'   => array(
			'text'                          => $html,
			'typography_typography'         => 'custom',
			'typography_font_size'          => $tzp_px( 16 ),
			'typography_font_size_mobile'   => $tzp_px( 15 ),
			'typography_line_height'        => array(
				'unit'  => 'em',
				'size'  => 1.6,
				'sizes' => array(),
			),
		),
		'elements'   => array(),
	);
};

$elements = array(
	array(
		'id'       => $tzp_id(),
		'elType'   => 'container',
		'settings' => array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => array(
				'unit'   => 'px',
				'size'   => 18,
				'column' => '18',
				'row'    => '18',
			),
			'padding'        => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '0',
				'bottom'   => '0',
				'left'     => '0',
				'isLinked' => true,
			),
		),
		'elements' => array(
			array(
				'id'         => $tzp_id(),
				'elType'     => 'widget',
				'widgetType' => 'wd_title',
				'settings'   => array(
					'title'                             => 'Dlaczego wysyłamy tylko 2 razy w tygodniu?',
					// Without these empty values the widget inserts the sample "Subtitle text example" and "Text after title text example".
					'subtitle'                          => '',
					'after_title'                       => '',
					'tag'                               => 'h3',
					'align'                             => 'left',
					'title_typography_typography'       => 'custom',
					'title_typography_font_size'        => $tzp_px( 26 ),
					'title_typography_font_size_mobile' => $tzp_px( 21 ),
					'title_typography_font_weight'      => '600',
					'title_typography_line_height'      => array(
						'unit'  => 'em',
						'size'  => 1.2,
						'sizes' => array(),
					),
				),
				'elements'   => array(),
			),
			$tzp_text( '<p>Bo nasz farsz pieczarkowy nie powstaje w 5 minut na patelni. Gotujemy go powoli, przez kilka godzin, jak dobre ragu, aż osiągnie właściwą gęstość i smak. Bez półproduktów i bez skrótów.</p><p>Gotujemy go na świeżo dwa razy w tygodniu, dokładnie pod zaplanowane wysyłki. Nic nie czeka w magazynie. Twoja zapiekanka powstaje pod Twoje zamówienie.</p>' ),
			array(
				'id'         => $tzp_id(),
				'elType'     => 'widget',
				'widgetType' => 'shortcode',
				'settings'   => array(
					'shortcode' => '[ship_today_notice class="tzp-why-dates" text="Zamów {cutoff_when}, a dostaniesz ją {delivery_when}."]',
				),
				'elements'   => array(),
			),
			$tzp_text( '<p>Przyjeżdża w termoboxie z wkładami chłodzącymi. W lodówce wytrzyma 7 dni, możesz ją też zamrozić.</p>' ),
			array(
				'id'         => $tzp_id(),
				'elType'     => 'widget',
				'widgetType' => 'wd_button',
				'settings'   => array(
					'text'              => 'Wybieram zapiekanki',
					'link'              => array(
						'url'               => '/sklep/',
						'is_external'       => '',
						'nofollow'          => '',
						'custom_attributes' => '',
					),
					'color'             => 'primary',
					'shape'             => 'semi-round',
					'_element_width'    => 'auto',
					'_flex_align_self'  => 'flex-start',
				),
				'elements'   => array(),
			),
		),
		'isInner'  => false,
	),
);

$settings = array(
	'wd_popup_width'                        => $tzp_px( 620 ),
	'wd_popup_padding'                      => array(
		'unit'     => 'px',
		'top'      => '40',
		'right'    => '40',
		'bottom'   => '36',
		'left'     => '40',
		'isLinked' => false,
	),
	'wd_popup_padding_mobile'               => array(
		'unit'     => 'px',
		'top'      => '30',
		'right'    => '20',
		'bottom'   => '24',
		'left'     => '20',
		'isLinked' => false,
	),
	'wd_popup_border_radius'                => array(
		'unit'     => 'px',
		'top'      => '8',
		'right'    => '8',
		'bottom'   => '8',
		'left'     => '8',
		'isLinked' => true,
	),
	'wd_popup_persistent_close'             => '',
	'wd_popup_is_some_time_enabled'         => '',
	'wd_popup_is_on_selector_click_enabled' => '',
	'wd_popup_selector'                     => '',
	'wd_popup_conditions'                   => array(
		array(
			'_id'        => $tzp_id(),
			'comparison' => 'include',
			'type'       => 'all',
		),
		array(
			'_id'          => $tzp_id(),
			'comparison'   => 'exclude',
			'type'         => 'custom',
			'query_custom' => 'checkout',
		),
	),
);

$existing = get_posts(
	array(
		'post_type'      => 'wd_popup',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_tzp_popup', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => 'shipping-why', // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);

$documents = \Elementor\Plugin::$instance->documents;

// In WP-CLI Elementor registers document types before Woodmart hooks into elementor/documents/register (init 10).
if ( ! $documents->get_document_type( 'wd_popup', false ) && class_exists( '\XTS\Modules\Floating_Blocks\Integrations\Popup_Document' ) ) {
	$documents->register_document_type( 'wd_popup', \XTS\Modules\Floating_Blocks\Integrations\Popup_Document::class );
}

if ( $existing ) {
	$document = $documents->get( (int) $existing[0], false );
	WP_CLI::log( 'Nadpisuję popup ' . $existing[0] . '.' );
} else {
	$document = $documents->create(
		'wd_popup',
		array(
			'post_title'  => 'Dlaczego 2 razy w tygodniu (belka)',
			'post_status' => 'publish',
		)
	);

	if ( is_wp_error( $document ) || ! $document ) {
		WP_CLI::error( 'Nie udało się utworzyć popupu.' );
	}

	update_post_meta( $document->get_main_id(), '_tzp_popup', 'shipping-why' );
}

$document->save(
	array(
		'elements' => $elements,
		'settings' => $settings,
	)
);

wp_update_post(
	array(
		'ID'          => $document->get_main_id(),
		'post_status' => 'publish',
	)
);

// Woodmart keeps popup conditions in a transient and clears it only when saving in the admin.
delete_transient( 'wd_all_popup_conditions' );

// Bar popup (same as choosing it in Appearance → Customize → "Belka o wysyłce").
$bar_option             = get_option( 'tzp_shipping_bar', array() );
$bar_option             = is_array( $bar_option ) ? $bar_option : array();
$bar_option['action']   = 'popup';
$bar_option['popup_id'] = $document->get_main_id();
update_option( 'tzp_shipping_bar', $bar_option );

WP_CLI::success( 'Popup ' . $document->get_main_id() . ' gotowy i ustawiony jako popup belki.' );
