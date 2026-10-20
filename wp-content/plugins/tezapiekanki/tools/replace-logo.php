<?php
/**
 * Logo "TE Zapiekanki Skalbmierskie od 1987" zamiast "TE Zapiekanki est 1987" (decyzja Marcina z 20.10).
 *
 * Uruchomienie (WP-CLI, jako administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/replace-logo.php --user=<login>            (podgląd)
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/replace-logo.php apply --user=<login>      (zapis)
 *
 * Logo w wersji "Skalbmierskie od 1987" jest w mediach od 06.2024 (Light.png białe, Black.png czarne; Black.png ma
 * też nagłówek maili). Gdy plików nie ma w mediach, skrypt wgrywa je z tools/logo/.
 * 1. Nagłówek Woodmarta (Header Builder, domyślny nagłówek): logo i logo przyklejonego nagłówka, desktop i telefon,
 *    białe SVG → Light.png. Zapis przez Header Buildera, więc CSS nagłówka też się przebudowuje.
 * 2. Dokumenty Elementora (stopka i bloki HTML, popupy, floating blocki; bez rewizji): czarne SVG → Black.png
 *    w pełnym rozmiarze (szerokość ustawia widget, jak dotąd).
 * Kopie przed zapisem: wp-content/uploads/tzp-backup/.
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

$tzp_apply = in_array( 'apply', (array) ( $args ?? array() ), true );

if ( $tzp_apply && ! current_user_can( 'edit_theme_options' ) ) {
	WP_CLI::error( 'Uruchom z --user=<administrator>.' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Stare logo: pliki SVG (białe w nagłówku, czarne w stopce).
$tzp_old = array(
	'light' => '/tezapiekankibialelogo\.svg$/',
	'dark'  => '/tezapiekankiczarnelogo\.svg$/',
);

/**
 * Załącznik z logo: istniejący w mediach (po nazwie pliku) albo wgrany z tools/logo/.
 */
$tzp_logo = static function ( $file ) use ( $tzp_apply ) {
	global $wpdb;

	$id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND ( meta_value = %s OR meta_value LIKE %s ) ORDER BY post_id ASC LIMIT 1",
			$file,
			'%/' . $wpdb->esc_like( $file )
		)
	);

	if ( $id ) {
		return $id;
	}

	if ( ! $tzp_apply ) {
		WP_CLI::log( "[podgląd] Brak $file w mediach, zostanie wgrany z tools/logo/." );
		return 0;
	}

	$tmp = wp_tempnam( $file );
	copy( TZP_DIR . 'tools/logo/' . $file, $tmp );
	$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$id              = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		0,
		'TE Zapiekanki Skalbmierskie od 1987'
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "$file: " . $id->get_error_message() );
	}

	return (int) $id;
};

$tzp_new = array(
	'light' => $tzp_logo( 'Light.png' ),
	'dark'  => $tzp_logo( 'Black.png' ),
);

$tzp_image = static function ( $id ) {
	$meta = $id ? wp_get_attachment_metadata( $id ) : array();
	return array(
		'id'     => $id,
		'url'    => $id ? wp_get_attachment_url( $id ) : '',
		'width'  => (int) ( $meta['width'] ?? 1400 ),
		'height' => (int) ( $meta['height'] ?? 298 ),
	);
};

$tzp_backup_dir = wp_upload_dir()['basedir'] . '/tzp-backup';
wp_mkdir_p( $tzp_backup_dir );
$tzp_changes = 0;

/*
 * 1. Nagłówek.
 */
$tzp_builder = class_exists( '\XTS\Modules\Header_Builder' ) ? \XTS\Modules\Header_Builder::get_instance() : null;

if ( ! $tzp_builder ) {
	WP_CLI::warning( 'Brak Header Buildera Woodmarta, nagłówek pominięty.' );
} else {
	$header_id = $tzp_builder->manager->get_default_header();
	$raw       = get_option( 'whb_' . $header_id );
	$count     = 0;

	$walk = static function ( &$node ) use ( &$walk, &$count, $tzp_old, $tzp_new, $tzp_image ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( array( 'image', 'sticky_image' ) as $key ) {
			$url = $node['params'][ $key ]['value']['url'] ?? '';
			if ( $url && preg_match( $tzp_old['light'], $url ) ) {
				$node['params'][ $key ]['value'] = $tzp_image( $tzp_new['light'] );
				$count++;
			}
		}
		foreach ( $node as &$child ) {
			if ( is_array( $child ) ) {
				$walk( $child );
			}
		}
	};

	$structure = $raw['structure'] ?? array();
	$walk( $structure );

	WP_CLI::log( sprintf( '%sNagłówek %s: %d obrazów logo do podmiany na Light.png.', $tzp_apply ? '' : '[podgląd] ', $header_id, $count ) );

	if ( $count && $tzp_apply ) {
		file_put_contents( $tzp_backup_dir . '/whb-' . $header_id . '-' . gmdate( 'Ymd-His' ) . '.json', wp_json_encode( $raw ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$tzp_builder->factory->update_header( $header_id, $raw['name'], $structure, $raw['settings'] );
	}
	$tzp_changes += $count;
}

/*
 * 2. Dokumenty Elementora.
 */
global $wpdb;
$tzp_docs = $wpdb->get_col(
	"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE '%tezapiekankiczarnelogo%' AND p.post_type <> 'revision'"
);

foreach ( $tzp_docs as $doc_id ) {
	$document = \Elementor\Plugin::$instance->documents->get( (int) $doc_id, false );
	if ( ! $document ) {
		continue;
	}

	$elements = $document->get_elements_data();
	$count    = 0;

	$swap = static function ( array &$els ) use ( &$swap, &$count, $tzp_old, $tzp_new ) {
		foreach ( $els as &$el ) {
			$url = $el['settings']['image']['url'] ?? '';
			if ( $url && preg_match( $tzp_old['dark'], $url ) ) {
				$el['settings']['image']['id']  = $tzp_new['dark'];
				$el['settings']['image']['url'] = $tzp_new['dark'] ? wp_get_attachment_url( $tzp_new['dark'] ) : '';
				// SVG skalował się do szerokości widgetu; PNG w pełnym rozmiarze (1400 px) robi to samo i jest ostre na retinie.
				$el['settings']['image_size'] = 'full';
				$count++;
			}
			if ( ! empty( $el['elements'] ) ) {
				$swap( $el['elements'] );
			}
		}
	};
	$swap( $elements );

	if ( ! $count ) {
		continue;
	}

	WP_CLI::log( sprintf( '%s#%d %s (%s, %s): %d logo → Black.png', $tzp_apply ? '' : '[podgląd] ', $doc_id, get_the_title( $doc_id ), get_post_type( $doc_id ), get_post_status( $doc_id ), $count ) );

	if ( $tzp_apply ) {
		file_put_contents( $tzp_backup_dir . '/elementor-data-' . $doc_id . '-' . gmdate( 'Ymd-His' ) . '.json', get_post_meta( $doc_id, '_elementor_data', true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$document->save( array( 'elements' => $elements ) );
	}
	$tzp_changes += $count;
}

if ( $tzp_apply ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
	do_action( 'litespeed_purge_all' );
	WP_CLI::success( "$tzp_changes podmian logo zapisanych." );
} else {
	WP_CLI::success( "$tzp_changes podmian logo do zrobienia (podgląd). Zapis: dodaj argument \"apply\"." );
}
