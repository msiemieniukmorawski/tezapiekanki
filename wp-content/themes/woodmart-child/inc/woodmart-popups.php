<?php
/**
 * Popupy i floating blocki Woodmarta budowane w Elementorze.
 *
 * Błąd zgodności Woodmart 8.5.5 + Elementor 4.3 (znaleziony 19.10): moduł MCP Elementora (Pro: Manage_Site_Parts_Ability)
 * na init z priorytetem 5 wywołuje rejestrację typów dokumentów (elementor/documents/register). Woodmart podpina swoje
 * typy wd_popup i wd_floating_block pod ten hook dopiero na init 10 (Floating_Blocks\Main::include_files), więc nie
 * zdążą się zarejestrować. Skutek: Woodmart traktuje popup z Elementora jak blok Gutenberga i bierze jego domyślne
 * ustawienia (wyzwalacz "po czasie" 0 s, "persistent close", warunek "wszędzie"), a ustawienia z Elementora
 * (wyzwalacz po kliknięciu, warunki) są pomijane. Edytor Elementora też nie pokazuje wtedy ustawień popupu.
 *
 * Tu dorejestrowujemy te typy zaraz po Woodmarcie. Gdy Woodmart zdąży sam (np. po poprawce w aktualizacji), funkcja nic nie robi.
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
