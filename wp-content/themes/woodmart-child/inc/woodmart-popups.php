<?php
/**
 * Woodmart popups and floating blocks built in Elementor.
 *
 * Woodmart 8.5.5 + Elementor 4.3 compatibility bug (found 2026-10-19): Elementor's MCP module (Pro: Manage_Site_Parts_Ability)
 * triggers document type registration (elementor/documents/register) on init priority 5. Woodmart hooks its
 * wd_popup and wd_floating_block types onto that hook only on init 10 (Floating_Blocks\Main::include_files), so they
 * never get registered. Result: Woodmart treats an Elementor popup as a Gutenberg block and uses its default
 * settings ("after time" trigger at 0 s, "persistent close", condition "everywhere"), while the Elementor settings
 * (click trigger, conditions) are ignored. The Elementor editor doesn't show the popup settings either.
 *
 * Here we register these types right after Woodmart. If Woodmart manages it itself (e.g. fixed in an update), the function does nothing.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tzp_register_woodmart_popup_documents', 11 );

function tzp_register_woodmart_popup_documents() {
	if ( ! did_action( 'elementor/documents/register' ) || ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\XTS\Modules\Floating_Blocks\Manager' ) ) {
		return;
	}

	$documents = \Elementor\Plugin::$instance->documents;

	if ( $documents->get_document_type( 'wd_popup', false ) ) {
		return;
	}

	\XTS\Modules\Floating_Blocks\Manager::get_instance()->register_elementor_document_type( $documents );
}
