<?php
/**
 * FAQ in the product description as collapsible questions.
 *
 * Product descriptions ("Opis" tab, WooCommerce content) have a section: an H2 heading containing "FAQ" or "pytania",
 * below it questions as H3 and answers as paragraphs. On display we replace this section with Faq::render() (collapsible
 * questions + FAQPage schema) and add a "Zobacz wszystkie pytania" link to the FAQ page. The content in the database
 * doesn't change, so the client keeps editing the product FAQ with plain headings in the description editor.
 *
 * Product questions are about a specific flavor (e.g. "Czy zapiekanka Salami nadaje się do mrożenia?"), so they can
 * have their own schema alongside the FAQ page with general questions.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Product_Faq {

	public static function init() {
		// After wpautop (10) and shortcodes (11): we work on the final paragraph HTML.
		add_filter( 'the_content', array( __CLASS__, 'transform' ), 20 );
	}

	/**
	 * @param string $content Product description content.
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

		// The FAQ section ends at the next H2 or at the end of the description.
		$end     = preg_match( '/<h2[\s>]/i', $rest, $next, PREG_OFFSET_CAPTURE ) ? $next[0][1] : strlen( $rest );
		$section = substr( $rest, 0, $end );

		$parts = preg_split( '/<h3[^>]*>(.*?)<\/h3>/isu', $section, -1, PREG_SPLIT_DELIM_CAPTURE );
		$items = array();

		for ( $i = 1, $count = count( $parts ); $i < $count; $i += 2 ) {
			$items[] = array(
				// The "1. " numbering from old descriptions is unnecessary in a collapsible list.
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
