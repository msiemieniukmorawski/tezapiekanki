<?php
/**
 * Plugin helper functions.
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin file version = its modification time, so browsers and LiteSpeed don't keep the old version after an update.
 *
 * @param string $file Path relative to the plugin directory, e.g. "assets/css/faq.css".
 * @return string
 */
function asset_version( $file ) {
	$path = TZP_DIR . $file;

	return file_exists( $path ) ? (string) filemtime( $path ) : TZP_VERSION;
}
