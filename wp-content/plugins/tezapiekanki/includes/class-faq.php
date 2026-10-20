<?php
/**
 * FAQ: pytania rozwijane po kliknięciu + dane strukturalne FAQPage.
 *
 * - Markup: natywne <details>/<summary>. Rozwijanie działa bez JavaScriptu (także na stronach z cache LiteSpeed),
 *   z klawiatury i w czytnikach ekranu. "Jedno otwarte naraz" to atrybut name na <details> (przeglądarki bez wsparcia
 *   po prostu pozwalają otworzyć kilka). Płynne rozwijanie: CSS ::details-content (gdzie przeglądarka to obsługuje).
 * - Schema: pytania ze wszystkich bloków FAQ na stronie z włączoną schemą trafiają do jednego FAQPage (JSON-LD na końcu
 *   strony). Dwa osobne FAQPage na jednej stronie Google zgłasza jako błąd, a wbudowane akordeony Elementora wypisują
 *   schemę osobno dla każdego widgetu. Powtórzone pytania liczą się raz.
 * - Wytyczne Google: te same pytania oznaczaj schemą tylko w jednym miejscu serwisu (np. na stronie FAQ), w innych
 *   miejscach wyłącz schemę w widgecie.
 *
 * Użycie: widget Elementora "FAQ (zwiń / rozwiń)" (class-faq-widget.php) albo w kodzie Faq::render( $items, $args ).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Faq {

	const STYLE = 'tzp-faq';

	/** Opcja z ID strony "Najczęstsze pytania" (ustawia tools/create-faq-page.php). */
	const PAGE_OPTION = 'tzp_faq_page_id';

	/**
	 * Pytania do schemy: klucz = znormalizowane pytanie.
	 *
	 * @var array<string, array{question: string, answer: string}>
	 */
	private static $schema = array();

	/** @var int Licznik bloków na stronie (unikalna nazwa grupy dla "jedno otwarte naraz"). */
	private static $count = 0;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_schema' ), 100 );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, TZP_URL . 'assets/css/faq.css', array(), asset_version( 'assets/css/faq.css' ) );
		}
	}

	/**
	 * @param \Elementor\Elements_Manager $manager
	 */
	public static function register_category( $manager ) {
		$manager->add_category(
			'tezapiekanki',
			array(
				'title' => 'TeZapiekanki',
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public static function register_widget( $manager ) {
		require_once TZP_DIR . 'includes/class-faq-widget.php';
		$manager->register( new Faq_Widget() );
	}

	/**
	 * Adres strony ze wszystkimi pytaniami.
	 */
	public static function page_url() {
		$id  = (int) get_option( self::PAGE_OPTION );
		$url = $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/faq/' );

		return (string) apply_filters( 'tzp_faq_page_url', $url );
	}

	/**
	 * Lista pytań jako bloki rozwijane.
	 *
	 * @param array $items Lista [ 'question' => string, 'answer' => string (HTML) ].
	 * @param array $args  open_first (bool) pierwsze otwarte, exclusive (bool) jedno otwarte naraz,
	 *                     schema (bool) dodać do FAQPage, tag (span|h2|h3|h4) znacznik pytania, id (string),
	 *                     more_text / more_url: link pod listą (np. "Zobacz wszystkie pytania"), more_align (left|center|right).
	 * @return string HTML.
	 */
	public static function render( array $items, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'open_first' => false,
				// Domyślnie można otworzyć kilka pytań naraz (decyzja z 19.10: zamykanie poprzedniego przesuwało treść,
				// na telefonie trzeba było przewijać).
				'exclusive'  => false,
				'schema'     => true,
				'tag'        => 'span',
				'id'         => '',
				'more_text'  => '',
				'more_url'   => '',
				'more_align' => 'left',
			)
		);

		$items = array_values(
			array_filter(
				array_map(
					static function ( $item ) {
						return array(
							'question' => trim( wp_strip_all_tags( (string) ( $item['question'] ?? '' ) ) ),
							'answer'   => trim( wp_kses_post( (string) ( $item['answer'] ?? '' ) ) ),
						);
					},
					$items
				),
				static function ( $item ) {
					return '' !== $item['question'];
				}
			)
		);

		if ( ! $items ) {
			return '';
		}

		self::register_assets();
		wp_enqueue_style( self::STYLE );

		self::$count++;
		$tag   = in_array( $args['tag'], array( 'span', 'h2', 'h3', 'h4' ), true ) ? $args['tag'] : 'span';
		$group = 'tzp-faq-' . ( '' !== $args['id'] ? sanitize_html_class( $args['id'] ) : self::$count );
		$html  = '<div class="tzp-faq">';

		foreach ( $items as $i => $item ) {
			$attrs = '';
			if ( $args['exclusive'] ) {
				$attrs .= ' name="' . esc_attr( $group ) . '"';
			}
			if ( $args['open_first'] && 0 === $i ) {
				$attrs .= ' open';
			}

			$html .= '<details class="tzp-faq__item"' . $attrs . '>';
			$html .= '<summary class="tzp-faq__question">';
			$html .= '<' . $tag . ' class="tzp-faq__title">' . esc_html( $item['question'] ) . '</' . $tag . '>';
			$html .= '<span class="tzp-faq__icon" aria-hidden="true"></span>';
			$html .= '</summary>';
			$html .= '<div class="tzp-faq__answer">' . wpautop( $item['answer'] ) . '</div>';
			$html .= '</details>';
		}

		if ( $args['schema'] ) {
			self::add_to_schema( $items );
		}

		$html .= '</div>';

		if ( '' !== trim( $args['more_text'] ) && '' !== trim( $args['more_url'] ) ) {
			$align = in_array( $args['more_align'], array( 'left', 'center', 'right' ), true ) ? $args['more_align'] : 'left';
			$html .= sprintf(
				'<p class="tzp-faq-more tzp-faq-more--%s"><a class="tzp-faq-more__link" href="%s">%s<span class="tzp-faq-more__arrow" aria-hidden="true">&rarr;</span></a></p>',
				esc_attr( $align ),
				esc_url( $args['more_url'] ),
				esc_html( $args['more_text'] )
			);
		}

		return $html;
	}

	/**
	 * @param array $items Lista [ 'question' => string, 'answer' => string ].
	 */
	public static function add_to_schema( array $items ) {
		foreach ( $items as $item ) {
			$key = mb_strtolower( preg_replace( '/\s+/u', ' ', $item['question'] ) );

			if ( '' === $key || isset( self::$schema[ $key ] ) ) {
				continue;
			}

			$answer = self::schema_answer( $item['answer'] );

			if ( '' !== $answer ) {
				self::$schema[ $key ] = array(
					'question' => $item['question'],
					'answer'   => $answer,
				);
			}
		}
	}

	/**
	 * Odpowiedź do schemy: tylko znaczniki, które Google dopuszcza w Answer.text, bez atrybutów (poza href).
	 */
	private static function schema_answer( $html ) {
		$allowed = array(
			'a'      => array( 'href' => true ),
			'br'     => array(),
			'p'      => array(),
			'div'    => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'b'      => array(),
			'strong' => array(),
			'i'      => array(),
			'em'     => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'h5'     => array(),
			'h6'     => array(),
		);

		$html = wp_kses( wpautop( (string) $html ), $allowed );
		$html = preg_replace( '/\s+/u', ' ', $html );

		return trim( preg_replace( '/<p>\s*<\/p>/', '', $html ) );
	}

	/**
	 * Jeden FAQPage na stronę, na końcu strony (po wyrenderowaniu treści, popupów i bloków HTML).
	 */
	public static function print_schema() {
		if ( ! self::$schema || is_admin() ) {
			return;
		}

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(),
		);

		foreach ( self::$schema as $item ) {
			$data['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}

		/**
		 * Pozwala zmienić albo wyłączyć (pusta tablica) schemę FAQPage na bieżącej stronie.
		 *
		 * @param array $data
		 */
		$data = apply_filters( 'tzp_faq_schema', $data );

		if ( empty( $data['mainEntity'] ) ) {
			return;
		}

		// JSON_HEX_TAG: "</p>" w odpowiedziach nie zamknie znacznika <script>.
		echo "\n" . '<script type="application/ld+json" class="tzp-faq-schema">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
