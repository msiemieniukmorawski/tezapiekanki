<?php
/**
 * Shipping bar settings in Appearance → Customize → "Belka o wysyłce" (option tzp_shipping_bar, theme-independent).
 *
 * Texts update live in the preview, the rest after the preview refreshes. After saving the bar settings and after saving
 * the selected popup in Elementor we purge the LiteSpeed cache (in production pages are cached for 7 days).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Shipping_Bar_Customizer {

	const SECTION = 'tzp_shipping_bar';

	public static function init() {
		add_action( 'customize_register', array( __CLASS__, 'register' ) );
		add_action( 'customize_preview_init', array( __CLASS__, 'preview_script' ) );
		add_action( 'customize_save_after', array( __CLASS__, 'purge_after_customize' ) );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'purge_after_popup_save' ) );
	}

	private static function setting_id( $key ) {
		return Shipping_Bar::OPTION . '[' . $key . ']';
	}

	/**
	 * @param \WP_Customize_Manager $wp_customize
	 */
	public static function register( $wp_customize ) {
		$defaults = Shipping_Bar::defaults();

		$wp_customize->add_section(
			self::SECTION,
			array(
				'title'       => __( 'Belka o wysyłce', 'tezapiekanki' ),
				'description' => __( 'Pasek nad nagłówkiem na wszystkich stronach oprócz zamówienia (checkout). Klient może go zamknąć krzyżykiem, wtedy nie widzi go do końca dnia.', 'tezapiekanki' ),
				'priority'    => 30,
			)
		);

		$action_is = static function ( $actions ) use ( $wp_customize ) {
			return static function () use ( $wp_customize, $actions ) {
				return in_array( $wp_customize->get_setting( self::setting_id( 'action' ) )->value(), (array) $actions, true );
			};
		};

		$fields = array(
			'enabled'   => array(
				'label'    => __( 'Pokazuj belkę', 'tezapiekanki' ),
				'type'     => 'checkbox',
				'sanitize' => 'rest_sanitize_boolean',
			),
			'text'      => array(
				'label'     => __( 'Tekst', 'tezapiekanki' ),
				'type'      => 'text',
				'sanitize'  => 'sanitize_text_field',
				'transport' => 'postMessage',
			),
			'link_text' => array(
				'label'       => __( 'Tekst linku', 'tezapiekanki' ),
				'description' => __( 'Podkreślony, ze strzałką. Puste pole: sam tekst, ale cały pasek dalej jest klikalny.', 'tezapiekanki' ),
				'type'        => 'text',
				'sanitize'    => 'sanitize_text_field',
				'transport'   => 'postMessage',
				'active'      => $action_is( array( 'popup', 'url' ) ),
			),
			'action'    => array(
				'label'    => __( 'Po kliknięciu w belkę', 'tezapiekanki' ),
				'type'     => 'select',
				'choices'  => array(
					'popup' => __( 'Otwórz popup', 'tezapiekanki' ),
					'url'   => __( 'Przejdź pod adres', 'tezapiekanki' ),
					'none'  => __( 'Nic (sam tekst, bez linku)', 'tezapiekanki' ),
				),
				'sanitize' => array( __CLASS__, 'sanitize_action' ),
			),
			'popup_id'  => array(
				'label'       => __( 'Popup', 'tezapiekanki' ),
				'description' => __( 'Lista opublikowanych popupów z menu Popups (tam edytujesz ich treść w Elementorze). Wyzwalacza nie trzeba ustawiać w popupie, belka dopina go sama, a automatyczne otwieranie po czasie jest w nim wtedy wyłączone. Popup musi mieć warunki wyświetlania obejmujące strony z belką (np. "All"), inaczej kliknięcie prowadzi pod adres poniżej.', 'tezapiekanki' ),
				'type'        => 'select',
				'choices'     => self::popup_choices(),
				'sanitize'    => array( __CLASS__, 'sanitize_popup' ),
				'active'      => $action_is( 'popup' ),
			),
			'url'       => array(
				'label'       => __( 'Adres', 'tezapiekanki' ),
				'description' => __( 'Gdzie prowadzi kliknięcie. Przy popupie to adres zapasowy, gdy popup się nie otworzy.', 'tezapiekanki' ),
				'type'        => 'url',
				'sanitize'    => 'esc_url_raw',
				'active'      => $action_is( array( 'popup', 'url' ) ),
			),
		);

		foreach ( $fields as $key => $field ) {
			$id = self::setting_id( $key );

			$wp_customize->add_setting(
				$id,
				array(
					'type'              => 'option',
					'capability'        => 'edit_theme_options',
					'default'           => $defaults[ $key ],
					'transport'         => isset( $field['transport'] ) ? $field['transport'] : 'refresh',
					'sanitize_callback' => $field['sanitize'],
				)
			);

			$control = array(
				'section' => self::SECTION,
				'label'   => $field['label'],
				'type'    => $field['type'],
			);

			foreach ( array( 'description', 'choices' ) as $arg ) {
				if ( isset( $field[ $arg ] ) ) {
					$control[ $arg ] = $field[ $arg ];
				}
			}

			if ( isset( $field['active'] ) ) {
				$control['active_callback'] = $field['active'];
			}

			$wp_customize->add_control( $id, $control );
		}
	}

	private static function popup_choices() {
		$choices = array( 0 => __( '(nie wybrano)', 'tezapiekanki' ) );

		$popups = get_posts(
			array(
				'post_type'      => 'wd_popup',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $popups as $popup ) {
			$choices[ $popup->ID ] = sprintf( '%s (#%d)', $popup->post_title ? $popup->post_title : __( '(bez tytułu)', 'tezapiekanki' ), $popup->ID );
		}

		return $choices;
	}

	public static function sanitize_action( $value ) {
		return in_array( $value, Shipping_Bar::ACTIONS, true ) ? $value : 'popup';
	}

	public static function sanitize_popup( $value ) {
		$value = absint( $value );

		return 'wd_popup' === get_post_type( $value ) ? $value : 0;
	}

	public static function preview_script() {
		wp_enqueue_script(
			'tzp-shipping-bar-customizer',
			TZP_URL . 'assets/js/shipping-bar-customizer.js',
			array( 'customize-preview' ),
			Shipping_Bar::asset_version( 'assets/js/shipping-bar-customizer.js' ),
			true
		);
	}

	/**
	 * @param \WP_Customize_Manager $manager
	 */
	public static function purge_after_customize( $manager ) {
		foreach ( array_keys( $manager->unsanitized_post_values() ) as $id ) {
			if ( 0 === strpos( $id, Shipping_Bar::OPTION . '[' ) ) {
				self::purge_cache();
				return;
			}
		}
	}

	/**
	 * @param \Elementor\Core\Base\Document $document
	 */
	public static function purge_after_popup_save( $document ) {
		$settings = Shipping_Bar::settings();

		if ( 'popup' === $settings['action'] && (int) $document->get_main_id() === $settings['popup_id'] ) {
			self::purge_cache();
		}
	}

	private static function purge_cache() {
		do_action( 'litespeed_purge_all' );
	}
}
