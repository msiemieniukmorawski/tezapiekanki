<?php
/**
 * Zamienia akordeon Woodmarta (wd_accordion) na stronie Elementora na widget "FAQ (zwiń / rozwiń)" (tzp_faq)
 * z tymi samymi pytaniami i odpowiedziami. Szerokość i wyrównanie widgetu zostają.
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/convert-accordion-to-faq.php <ID strony> [ID widgetu] [noschema] --user=<admin>
 *
 * Bez ID widgetu zamienia wszystkie akordeony Woodmarta na stronie. "noschema" wyłącza dane strukturalne FAQPage
 * w nowych widgetach (gdy te same pytania są oznaczone gdzie indziej, np. na stronie FAQ).
 * Przed zapisem kopia _elementor_data trafia do wp-content/uploads/tzp-backup/.
 *
 * Lokalnie 19.10: strona główna (11), widget 2e3bb3e (5 pytań, pierwsze otwarte, schema włączona).
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
				// Woodmart: data-state "first" (domyślnie) = pierwsze otwarte, "all_closed" = wszystkie zamknięte.
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
