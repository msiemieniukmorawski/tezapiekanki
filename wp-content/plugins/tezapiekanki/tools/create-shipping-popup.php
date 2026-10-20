<?php
/**
 * Tworzy (albo nadpisuje) popup Woodmarta "Dlaczego wysyłamy tylko 2 razy w tygodniu?" dla belki o wysyłce.
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/create-shipping-popup.php --user=<login administratora>
 *
 * Popup zapisuje się przez API dokumentów Elementora (nie SQL-em), więc później klient edytuje go normalnie
 * w Elementorze: menu Popups. Skrypt ustawia go też jako popup belki (Wygląd → Dostosuj → "Belka o wysyłce").
 * Ustawienia:
 * - bez zapisanego wyzwalacza: kliknięcie w belkę dopina wtyczka w locie (Shipping_Bar::popup_trigger), więc po
 *   wybraniu innego popupu w Dostosuj ten przestaje się otwierać z belki,
 * - warunki: wszędzie oprócz checkoutu (tam belki nie ma),
 * - "Persistent close" wyłączone (domyślnie w Woodmarcie włączone: po pierwszym zamknięciu popup już by się
 *   nie otworzył, a link w belce przestałby działać), wyzwalacz "po czasie" wyłączony (domyślnie włączony z 0 s,
 *   czyli popup otwierałby się sam przy każdym wejściu),
 * - wymaga poprawki rejestracji typu wd_popup z motywu potomnego (inc/woodmart-popups.php), inaczej przy
 *   Elementorze 4.3 Woodmart pomija ustawienia popupu z Elementora,
 * - najbliższy termin: [ship_today_notice] z wtyczki WooCommerce Ship Today Notice (liczony w przeglądarce, działa na
 *   stronach z cache, po godzinie granicznej sam przeskakuje na kolejny).
 *
 * Ponowne uruchomienie nadpisuje treść i ustawienia popupu oznaczonego meta _tzp_popup = shipping-why.
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
					// Bez tych pustych wartości widget wstawia przykładowe "Subtitle text example" i "Text after title text example".
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
			$tzp_text( '<p>Przyjeżdża w termoboxie z wkładami chłodzącymi. W lodówce wytrzyma 6 dni, możesz ją też zamrozić.</p>' ),
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

// W WP-CLI Elementor rejestruje typy dokumentów, zanim Woodmart podepnie się pod elementor/documents/register (init 10).
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

// Woodmart trzyma warunki popupów w transiencie i czyści go tylko przy zapisie w panelu.
delete_transient( 'wd_all_popup_conditions' );

// Popup belki (to samo co wybór w Wygląd → Dostosuj → "Belka o wysyłce").
$bar_option             = get_option( 'tzp_shipping_bar', array() );
$bar_option             = is_array( $bar_option ) ? $bar_option : array();
$bar_option['action']   = 'popup';
$bar_option['popup_id'] = $document->get_main_id();
update_option( 'tzp_shipping_bar', $bar_option );

WP_CLI::success( 'Popup ' . $document->get_main_id() . ' gotowy i ustawiony jako popup belki.' );
