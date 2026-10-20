<?php
/**
 * FAQ w opisie produktu jako pytania rozwijane.
 *
 * Opisy produktów (zakładka "Opis", treść WooCommerce) mają sekcję: nagłówek H2 z "FAQ" albo "pytania", pod nim
 * pytania jako H3 i odpowiedzi jako akapity. Przy wyświetlaniu zamieniamy tę sekcję na Faq::render() (rozwijane
 * pytania + schema FAQPage) i dodajemy link "Zobacz wszystkie pytania" do strony FAQ. Treść w bazie się nie zmienia,
 * więc klient dalej edytuje FAQ produktu zwykłymi nagłówkami w edytorze opisu.
 *
 * Pytania na produktach dotyczą konkretnego smaku (np. "Czy zapiekanka Salami nadaje się do mrożenia?"), więc mogą
 * mieć własną schemę obok strony FAQ z pytaniami ogólnymi.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Product_Faq {

	public static function init() {
		// Po wpautop (10) i shortcode'ach (11): pracujemy na gotowym HTML-u akapitów.
		add_filter( 'the_content', array( __CLASS__, 'transform' ), 20 );
	}

	/**
	 * @param string $content Treść opisu produktu.
	 * @return string
	 */
	public static function transform( $content ) {
		if ( is_admin() || ! is_singular( 'product' ) || 'product' !== get_post_type() || false === stripos( $content, '<h2' ) ) {
			return $content;
		}

		if ( ! preg_match( '/<h2[^>]*>(?:(?!<\/h2>).)*?(?:FAQ|pytania)(?:(?!<\/h2>).)*<\/h2>/isu', $content, $heading, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		$start = $heading[0][1] + strlen( $heading[0][0] );
		$rest  = substr( $content, $start );

		// Sekcja FAQ kończy się na następnym H2 albo na końcu opisu.
		$end     = preg_match( '/<h2[\s>]/i', $rest, $next, PREG_OFFSET_CAPTURE ) ? $next[0][1] : strlen( $rest );
		$section = substr( $rest, 0, $end );

		$parts = preg_split( '/<h3[^>]*>(.*?)<\/h3>/isu', $section, -1, PREG_SPLIT_DELIM_CAPTURE );
		$items = array();

		for ( $i = 1, $count = count( $parts ); $i < $count; $i += 2 ) {
			$items[] = array(
				// Numeracja "1. " z dawnych opisów jest zbędna w rozwijanej liście.
				'question' => preg_replace( '/^\s*\d+\.\s*/u', '', wp_strip_all_tags( $parts[ $i ] ) ),
				'answer'   => $parts[ $i + 1 ] ?? '',
			);
		}

		if ( ! $items ) {
			return $content;
		}

		$faq = $parts[0] . Faq::render(
			$items,
			array(
				'tag'        => 'h3',
				'exclusive'  => false,
				'schema'     => true,
				'id'         => 'product-' . get_the_ID(),
				'more_text'  => __( 'Zobacz wszystkie pytania', 'tezapiekanki' ),
				'more_url'   => Faq::page_url(),
				'more_align' => 'left',
			)
		);

		return substr( $content, 0, $start ) . $faq . substr( $rest, $end );
	}
}
