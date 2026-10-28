<?php
/**
 * Replaces a Woodmart accordion (wd_accordion) on an Elementor page with the "FAQ (zwiń / rozwiń)" widget (tzp_faq)
 * using the same questions and answers. Widget width and alignment are kept.
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/convert-accordion-to-faq.php <ID strony> [ID widgetu] [noschema] --user=<admin>
 *
 * Without a widget ID, converts every Woodmart accordion on the page. "noschema" disables FAQPage structured data
 * in the new widgets (when the same questions are marked up elsewhere, e.g. on the FAQ page).
 * Before saving, a copy of _elementor_data goes to wp-content/uploads/tzp-backup/.
 *
 * Locally 2026-10-19: front page (11), widget 2e3bb3e (5 questions, first open, schema enabled).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_post_id   = (int) ( $args[0] ?? 0 );
$tzp_widget_id = isset( $args[1] ) && 'noschema' !== $args[1] ? $args[1] : '';
$tzp_schema    = ! in_array( 'noschema', (array) $args, true );

if ( ! $tzp_post_id || ! get_post( $tzp_post_id ) ) {
	WP_CLI::error( 'Podaj ID strony: wp eval-file … <ID strony> [ID widgetu] [noschema]' );
}

$tzp_doc = \Elementor\Plugin::$instance->documents->get( $tzp_post_id, false );

if ( ! $tzp_doc || ! $tzp_doc->is_built_with_elementor() ) {
	WP_CLI::error( 'Strona ' . $tzp_post_id . ' nie jest zbudowana w Elementorze.' );
}

$tzp_dir = wp_upload_dir()['basedir'] . '/tzp-backup';
wp_mkdir_p( $tzp_dir );
$tzp_backup = $tzp_dir . '/elementor-data-' . $tzp_post_id . '-' . gmdate( 'Ymd-His' ) . '.json';
file_put_contents( $tzp_backup, get_post_meta( $tzp_post_id, '_elementor_data', true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

$tzp_elements = $tzp_doc->get_elements_data();
$tzp_done     = 0;

$tzp_walk = static function ( array &$els ) use ( &$tzp_walk, &$tzp_done, $tzp_widget_id, $tzp_schema ) {
	foreach ( $els as &$el ) {
		if ( 'wd_accordion' === ( $el['widgetType'] ?? '' ) && ( '' === $tzp_widget_id || $tzp_widget_id === $el['id'] ) ) {
			$old   = $el['settings'];
			$items = array();

			foreach ( (array) ( $old['items'] ?? array() ) as $item ) {
				$items[] = array(
					'_id'      => $item['_id'] ?? substr( md5( uniqid( '', true ) ), 0, 7 ),
					'question' => $item['item_title'] ?? '',
					'answer'   => $item['item_content'] ?? '',
				);
			}

			$settings = array(
				'items'      => $items,
				// Woodmart: data-state "first" (default) = first item open, "all_closed" = all closed.
				'open_first' => 'all_closed' === ( $old['state'] ?? 'first' ) ? '' : 'yes',
				'exclusive'  => '',
				'schema'     => $tzp_schema ? 'yes' : '',
				'title_tag'  => 'span',
			);

			foreach ( $old as $key => $value ) {
				if ( preg_match( '/^(_element_width|_element_custom_width|_flex_align_self|_margin|_padding|_css_classes|scroll_y)/', $key ) ) {
					$settings[ $key ] = $value;
				}
			}

			$el = array(
				'id'         => $el['id'],
				'elType'     => 'widget',
				'widgetType' => 'tzp_faq',
				'settings'   => $settings,
				'elements'   => array(),
			);
			$tzp_done++;
			WP_CLI::log( 'Widget ' . $el['id'] . ': ' . count( $items ) . ' pytań.' );
		}

		if ( ! empty( $el['elements'] ) ) {
			$tzp_walk( $el['elements'] );
		}
	}
};
$tzp_walk( $tzp_elements );

if ( ! $tzp_done ) {
	WP_CLI::error( 'Nie znaleziono akordeonu Woodmarta do zamiany.' );
}

$tzp_doc->save( array( 'elements' => $tzp_elements ) );
\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );

WP_CLI::success( $tzp_done . ' akordeon(y) zamienione. Kopia: ' . $tzp_backup );
