<?php
/**
 * FAQ: click-to-expand questions + FAQPage structured data.
 *
 * - Markup: native <details>/<summary>. Expanding works without JavaScript (also on LiteSpeed-cached pages),
 *   from the keyboard and in screen readers. "One open at a time" is the name attribute on <details> (browsers without
 *   support simply allow opening several). Smooth expanding: CSS ::details-content (where the browser supports it).
 * - Schema: questions from all FAQ blocks on the page with schema enabled go into a single FAQPage (JSON-LD at the end
 *   of the page). Google reports two separate FAQPage items on one page as an error, and Elementor's built-in accordions
 *   print the schema separately for each widget. Repeated questions count once.
 * - Google guidelines: mark up the same questions with schema in only one place on the site (e.g. the FAQ page);
 *   elsewhere disable the schema in the widget.
 *
 * Usage: Elementor widget "FAQ (zwiń / rozwiń)" (class-faq-widget.php) or in code Faq::render( $items, $args ).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Faq {

	const STYLE = 'tzp-faq';

	/** Option holding the ID of the "Najczęstsze pytania" page (set by tools/create-faq-page.php). */
	const PAGE_OPTION = 'tzp_faq_page_id';

	/**
	 * Questions for the schema: key = normalized question.
	 *
	 * @var array<string, array{question: string, answer: string}>
	 */
	private static $schema = array();

	/** @var int Block counter on the page (unique group name for "one open at a time"). */
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
	 * URL of the page with all questions.
	 */
	public static function page_url() {
		$id  = (int) get_option( self::PAGE_OPTION );
		$url = $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/faq/' );

		return (string) apply_filters( 'tzp_faq_page_url', $url );
	}

	/**
	 * List of questions as collapsible blocks.
	 *
	 * @param array $items List of [ 'question' => string, 'answer' => string (HTML) ].
	 * @param array $args  open_first (bool) first one open, exclusive (bool) one open at a time,
	 *                     schema (bool) add to FAQPage, tag (span|h2|h3|h4) question tag, id (string),
	 *                     more_text / more_url: link below the list (e.g. "Zobacz wszystkie pytania"), more_align (left|center|right).
	 * @return string HTML.
	 */
	public static function render( array $items, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'open_first' => false,
				// By default several questions can be open at once (decided 2026-10-19: closing the previous one shifted
				// the content, on phones you had to scroll).
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
	 * @param array $items List of [ 'question' => string, 'answer' => string ].
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
	 * Answer for the schema: only the tags Google allows in Answer.text, without attributes (except href).
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
	 * One FAQPage per page, at the end of the page (after content, popups and HTML blocks are rendered).
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
		 * Allows changing or disabling (empty array) the FAQPage schema on the current page.
		 *
		 * @param array $data
		 */
		$data = apply_filters( 'tzp_faq_schema', $data );

		if ( empty( $data['mainEntity'] ) ) {
			return;
		}

		// JSON_HEX_TAG: "</p>" in answers won't close the <script> tag.
		echo "\n" . '<script type="application/ld+json" class="tzp-faq-schema">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
