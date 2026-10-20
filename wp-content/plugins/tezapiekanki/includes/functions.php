<?php
/**
 * Funkcje pomocnicze wtyczki.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

/**
 * Wersja pliku z wtyczki = data jego zmiany, żeby po aktualizacji przeglądarki i LiteSpeed nie trzymały starej wersji.
 *
 * @param string $file Ścieżka względem katalogu wtyczki, np. "assets/css/faq.css".
 * @return string
 */
function asset_version( $file ) {
	$path = TZP_DIR . $file;

	return file_exists( $path ) ? (string) filemtime( $path ) : TZP_VERSION;
}
