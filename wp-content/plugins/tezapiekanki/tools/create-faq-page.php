<?php
/**
 * "Najczęstsze pytania" page (/faq/) + footer link + shortened FAQ on the front page.
 *
 * Usage (WP-CLI, as administrator):
 *   wp eval-file wp-content/plugins/tezapiekanki/tools/create-faq-page.php --user=<login administratora>
 *
 * What it does (WordPress and Elementor APIs, no SQL):
 * 1. Creates or overwrites the page (meta _tzp_page = faq, URL /faq/): intro, 4 question groups in
 *    "FAQ (zwiń / rozwiń)" widgets with FAQPage schema (one per page), "Napisz do nas" at the end. Rank Math description.
 *    The page ID is stored in the tzp_faq_page_id option ("Zobacz wszystkie pytania" links take their URL from it).
 * 2. Adds "Najczęstsze pytania" to the footer link list (HTML Block, the list containing "Sposoby dostawy") if missing.
 * 3. Front page: the FAQ widget (tzp_faq) gets 4 questions not repeated by the SEO text further down the page,
 *    without schema (these questions are marked up on the FAQ page) and with a "Zobacz wszystkie pytania" button.
 *    Requires the accordion to be converted first: tools/convert-accordion-to-faq.php.
 *
 * Re-running overwrites the FAQ page content and the front page FAQ (manual Elementor changes will be lost).
 *
 * @package tezapiekanki
 */

defined( 'WP_CLI' ) || exit;

if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\TZP\Faq' ) ) {
	WP_CLI::error( 'Wymagany Elementor i aktywna wtyczka tezapiekanki.' );
}

$tzp_url = static function ( $path ) {
	return esc_url( home_url( $path ) );
};

/*
 * Content. Answers taken from existing shop texts (front page FAQ, product descriptions, shipping popup).
 */
$tzp_q = array(
	'wysylka'     => array(
		'question' => 'Kiedy wysyłacie zamówienia i kiedy dotrze moja paczka?',
		'answer'   => '<p>Wysyłamy dwa razy w tygodniu: w poniedziałki i środy. Zamówienia złożone do niedzieli do 23:59 wysyłamy w poniedziałek, a dostarczamy we wtorek. Zamówienia złożone do wtorku do 23:59 wysyłamy w środę, a dostarczamy w czwartek. Termin dostawy wybierasz przy składaniu zamówienia, a najbliższy wolny termin widzisz też na stronie każdej zapiekanki.</p>',
	),
	'dlaczego'    => array(
		'question' => 'Dlaczego wysyłacie tylko dwa razy w tygodniu?',
		'answer'   => '<p>Bo nasz farsz pieczarkowy nie powstaje w 5 minut na patelni. Gotujemy go powoli, przez kilka godzin, aż osiągnie właściwą gęstość i smak. Robimy to na świeżo dwa razy w tygodniu, dokładnie pod zaplanowane wysyłki. Nic nie czeka w magazynie, a Twoja zapiekanka powstaje pod Twoje zamówienie.</p>',
	),
	'bezpiecznie' => array(
		'question' => 'Jak wysyłacie zapiekanki i czy docierają bezpiecznie?',
		'answer'   => '<p>Wysyłamy na terenie całej Polski przez InPost: kurierem albo do Paczkomatów InPost 24/7. Zapiekanki pakujemy w termobox z wkładami chłodzącymi, które utrzymują niską temperaturę przez cały czas transportu, a opakowanie chroni je przed uszkodzeniem. Dostajesz produkt świeży, niepieczony, gotowy do przygotowania w domu.</p>',
	),
	'paczkomat'   => array(
		'question' => 'Czy mogę zamówić zapiekanki do Paczkomatu InPost?',
		'answer'   => '<p>Tak. Oferujemy wysyłkę zapiekanek do Paczkomatów InPost 24/7 na terenie całej Polski. To wygodne rozwiązanie dla osób, które chcą odebrać świeże zapiekanki w dogodnym czasie, bez konieczności oczekiwania na kuriera.</p>',
	),
	'smaki'       => array(
		'question' => 'Czy mogę zamówić różne smaki w jednym zamówieniu?',
		'answer'   => '<p>Tak, smaki możesz dowolnie łączyć w jednym zamówieniu. Jeśli zamawiasz więcej, zajrzyj do <a href="' . $tzp_url( '/zestawy-zapiekanek/' ) . '">zestawów 9 i 12 zapiekanek</a>: sam wybierasz w nich smaki, a każda zapiekanka w zestawie kosztuje mniej niż kupiona osobno.</p>',
	),
	'mrozone'     => array(
		'question' => 'Czy zapiekanki są mrożone przed wysyłką?',
		'answer'   => '<p>Nie. Nasze zapiekanki nie są mrożone. Produkujemy je na bieżąco, w krótkich seriach, a następnie wysyłamy świeże zapiekanki do pieczenia w domu. Dzięki temu po upieczeniu zachowują idealną strukturę, aromat i smak, którego nie da się osiągnąć w produktach mrożonych.</p>',
	),
	'swiezosc'    => array(
		'question' => 'Jak długo zapiekanki zachowują świeżość?',
		'answer'   => '<p>Zapiekanki są zdatne do spożycia przez 7 dni od dostawy, jeśli przechowujesz je w lodówce. Włóż je do lodówki zaraz po odbiorze paczki.</p>',
	),
	'mrozenie'    => array(
		'question' => 'Czy zapiekanki można zamrozić?',
		'answer'   => '<p>Tak. Najlepiej zrób to od razu po odbiorze przesyłki, jeśli wiesz, że nie zjesz ich w ciągu kilku dni. Zamrożone zapiekanki możesz później rozmrozić w lodówce albo zapiec od razu, wydłużając czas pieczenia. Po upieczeniu zachowują świetny smak i strukturę.</p>',
	),
	'pieczenie'   => array(
		'question' => 'Jak długo piec zapiekanki i w jakiej temperaturze?',
		'answer'   => '<p>Zapiekanki są przygotowane tak, aby każdy mógł je łatwo upiec w domowym piekarniku. Wystarczy nagrzać piekarnik do 220-250°C (bez termoobiegu) i piec zapiekankę <strong>około 8 minut</strong>, aby uzyskać chrupiące pieczywo i gorący, soczysty farsz. Szczegółowe instrukcje otrzymasz od nas w wiadomości mailowej. Do każdej z naszych zapiekanek przygotowaliśmy osobną instrukcję, która pomoże Ci uzyskać jej najlepsze walory smakowe.</p>',
	),
	'farsz'       => array(
		'question' => 'Na czym polega wyjątkowość waszego farszu?',
		'answer'   => '<p>Farsz to serce naszych zapiekanek. Jego przygotowanie to wieloetapowy, długi proces, obejmujący selekcję składników, odpowiednie proporcje, czas obróbki oraz dojrzewanie smaków. Nie korzystamy z gotowych półproduktów. Każdy składnik ma konkretne zadanie: buduje głębię smaku, teksturę i aromat po upieczeniu.</p>',
	),
	'imprezy'     => array(
		'question' => 'Czy zapiekanki sprawdzą się na imprezy, spotkania lub w gastronomii?',
		'answer'   => '<p>Tak. Sprawdzają się na imprezach domowych, spotkaniach rodzinnych i firmowych, a także jako uzupełnienie oferty barów, pubów czy food trucków. Są świeże, łatwe w przechowywaniu i szybkie w przygotowaniu, więc w krótkim czasie podasz większą liczbę porcji. Przy zamówieniach dla firm zajrzyj do zakładki <a href="' . $tzp_url( '/klienci-b2b/' ) . '">Klienci B2B</a>.</p>',
	),
);

$tzp_groups = array(
	array( 'Zamówienie <b>i dostawa</b>', array( 'wysylka', 'dlaczego', 'bezpiecznie', 'paczkomat', 'smaki' ) ),
	array( 'Świeżość, <b>przechowywanie i mrożenie</b>', array( 'mrozone', 'swiezosc', 'mrozenie' ) ),
	array( 'Pieczenie <b>w domu</b>', array( 'pieczenie' ) ),
	array( 'Nasze <b>zapiekanki</b>', array( 'farsz', 'imprezy' ) ),
);

// Front page: questions not repeated by the SEO text below the FAQ (freezing, filling and Paczkomat have their own sections there).
$tzp_home = array( 'wysylka', 'bezpiecznie', 'swiezosc', 'pieczenie' );

/*
 * Elementor elements.
 */
$tzp_id = static function () {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
};

$tzp_px = static function ( $size ) {
	return array(
		'unit'  => 'px',
		'size'  => $size,
		'sizes' => array(),
	);
};

$tzp_items = static function ( array $keys ) use ( $tzp_q, $tzp_id ) {
	return array_map(
		static function ( $key ) use ( $tzp_q, $tzp_id ) {
			return array( '_id' => $tzp_id() ) + $tzp_q[ $key ];
		},
		$keys
	);
};

$tzp_title = static function ( $title, $tag = 'h2', $after = '', $align = 'left' ) use ( $tzp_id, $tzp_px ) {
	return array(
		'id'         => $tzp_id(),
		'elType'     => 'widget',
		'widgetType' => 'wd_title',
		'settings'   => array(
			'title'                             => $title,
			'subtitle'                          => '',
			'after_title'                       => $after,
			'tag'                               => $tag,
			'align'                             => $align,
			'width'                             => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
			'title_typography_typography'       => 'custom',
			'title_typography_font_size'        => $tzp_px( 30 ),
			'title_typography_font_size_mobile' => $tzp_px( 21 ),
			'title_typography_font_weight'      => '400',
			'title_typography_line_height'      => array( 'unit' => 'em', 'size' => 1.15, 'sizes' => array() ),
			'text_typography_typography'        => 'custom',
			'text_typography_font_size'         => $tzp_px( 16 ),
		),
		'elements'   => array(),
	);
};

$tzp_container = static function ( array $elements, array $settings = array() ) use ( $tzp_id ) {
	return array(
		'id'       => $tzp_id(),
		'elType'   => 'container',
		'settings' => $settings + array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => array( 'unit' => 'px', 'size' => 20, 'column' => '20', 'row' => '20', 'isLinked' => true ),
			'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
		),
		'elements' => $elements,
		'isInner'  => true,
	);
};

$tzp_sections = array(
	array(
		'id'         => $tzp_id(),
		'elType'     => 'widget',
		'widgetType' => 'wd_text_block',
		'settings'   => array(
			'text'                        => '<p>Zebraliśmy odpowiedzi na pytania, które najczęściej słyszymy przed zamówieniem i po odbiorze paczki: o terminach dostawy, przechowywaniu, mrożeniu i pieczeniu naszych zapiekanek.</p>',
			'typography_typography'       => 'custom',
			'typography_font_size'        => $tzp_px( 18 ),
			'typography_font_size_mobile' => $tzp_px( 16 ),
		),
		'elements'   => array(),
	),
);

foreach ( $tzp_groups as $group ) {
	$tzp_sections[] = $tzp_container(
		array(
			$tzp_title( $group[0] ),
			array(
				'id'         => $tzp_id(),
				'elType'     => 'widget',
				'widgetType' => 'tzp_faq',
				'settings'   => array(
					'items'       => $tzp_items( $group[1] ),
					'open_first'  => '',
					'exclusive'   => '',
					'schema'      => 'yes',
					'title_tag'   => 'h3',
					'item_border' => '#E8E8E8',
				),
				'elements'   => array(),
			),
		)
	);
}

$tzp_sections[] = $tzp_container(
	array(
		$tzp_title( 'Nie ma tu <b>odpowiedzi na Twoje pytanie?</b>', 'h2', '<p>Napisz do nas, chętnie pomożemy.</p>', 'center' ),
		array(
			'id'         => $tzp_id(),
			'elType'     => 'widget',
			'widgetType' => 'wd_button',
			'settings'   => array(
				'text'           => 'Napisz do nas',
				'link'           => array( 'url' => '/kontakt/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
				'style'          => 'bordered',
				'color'          => 'primary',
				'shape'          => 'semi-round',
				'align'          => 'center',
			),
			'elements'   => array(),
		),
	),
	array( 'flex_gap' => array( 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10', 'isLinked' => true ) )
);

$tzp_elements = array(
	array(
		'id'       => $tzp_id(),
		'elType'   => 'container',
		'settings' => array(
			'content_width'  => 'boxed',
			'boxed_width'    => array( 'unit' => 'px', 'size' => 960, 'sizes' => array() ),
			'flex_direction' => 'column',
			'flex_gap'       => array( 'unit' => 'px', 'size' => 50, 'column' => '50', 'row' => '50', 'isLinked' => true ),
			'padding'        => array( 'unit' => 'px', 'top' => '50', 'right' => '20', 'bottom' => '70', 'left' => '20', 'isLinked' => false ),
			'padding_mobile' => array( 'unit' => 'px', 'top' => '30', 'right' => '15', 'bottom' => '50', 'left' => '15', 'isLinked' => false ),
		),
		'elements' => $tzp_sections,
		'isInner'  => false,
	),
);

/*
 * 1. FAQ page.
 */
$documents = \Elementor\Plugin::$instance->documents;

$tzp_page = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_tzp_page', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => 'faq', // phpcs:ignore WordPress.DB.SlowDBQuery
	)
);

if ( $tzp_page ) {
	$tzp_page_id = (int) $tzp_page[0];
	WP_CLI::log( 'Nadpisuję stronę FAQ ' . $tzp_page_id . '.' );
} else {
	$tzp_doc = $documents->create(
		'wp-page',
		array(
			'post_title'  => 'Najczęstsze pytania',
			'post_name'   => 'faq',
			'post_status' => 'publish',
			'post_type'   => 'page',
		)
	);

	if ( is_wp_error( $tzp_doc ) || ! $tzp_doc ) {
		WP_CLI::error( 'Nie udało się utworzyć strony FAQ.' );
	}

	$tzp_page_id = $tzp_doc->get_main_id();
	update_post_meta( $tzp_page_id, '_tzp_page', 'faq' );
}

$documents->get( $tzp_page_id, false )->save( array( 'elements' => $tzp_elements ) );
wp_update_post(
	array(
		'ID'          => $tzp_page_id,
		'post_status' => 'publish',
	)
);
update_option( \TZP\Faq::PAGE_OPTION, $tzp_page_id );
update_post_meta( $tzp_page_id, 'rank_math_description', 'Odpowiedzi na najczęstsze pytania o świeże zapiekanki TeZapiekanki: terminy wysyłki i dostawy, przechowywanie, mrożenie i pieczenie w domu.' );
WP_CLI::log( 'Strona FAQ: ' . get_permalink( $tzp_page_id ) );

/*
 * 2. Footer link.
 */
$tzp_footer_done = false;
$tzp_blocks      = get_posts(
	array(
		'post_type'      => 'cms_block',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $tzp_blocks as $block_id ) {
	$raw = (string) get_post_meta( $block_id, '_elementor_data', true );

	if ( false === strpos( $raw, 'wd_extra_menu_list' ) || false === strpos( $raw, 'sposoby-dostawy' ) ) {
		continue;
	}

	$doc      = $documents->get( $block_id, false );
	$elements = $doc->get_elements_data();
	$status   = '';

	$walk = static function ( array &$els ) use ( &$walk, &$status, $tzp_id ) {
		foreach ( $els as &$el ) {
			if ( 'wd_extra_menu_list' === ( $el['widgetType'] ?? '' ) && ! empty( $el['settings']['menu_items_repeater'] ) ) {
				$items = $el['settings']['menu_items_repeater'];
				$urls  = wp_list_pluck( wp_list_pluck( $items, 'link' ), 'url' );

				foreach ( $urls as $u ) {
					if ( false !== strpos( (string) $u, '/faq' ) ) {
						$status = 'jest';
						return;
					}
				}

				foreach ( $urls as $i => $u ) {
					if ( false !== strpos( (string) $u, 'sposoby-dostawy' ) ) {
						array_splice(
							$items,
							$i + 1,
							0,
							array(
								array(
									'_id'   => $tzp_id(),
									'title' => 'Najczęstsze pytania',
									'link'  => array( 'url' => '/faq/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
								),
							)
						);
						$el['settings']['menu_items_repeater'] = $items;
						$status                               = 'dodany';
						return;
					}
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
				if ( $status ) {
					return;
				}
			}
		}
	};
	$walk( $elements );

	if ( 'dodany' === $status ) {
		$doc->save( array( 'elements' => $elements ) );
	}

	if ( $status ) {
		WP_CLI::log( 'Stopka (HTML Block ' . $block_id . '): link ' . $status . '.' );
		$tzp_footer_done = true;
		break;
	}
}

if ( ! $tzp_footer_done ) {
	WP_CLI::warning( 'Nie znalazłem listy linków ze "Sposoby dostawy" w stopce, link do FAQ dodaj ręcznie.' );
}

/*
 * 3. Front page FAQ.
 */
$tzp_front = (int) get_option( 'page_on_front' );
$tzp_home_done = false;

if ( $tzp_front ) {
	$doc      = $documents->get( $tzp_front, false );
	$elements = $doc->get_elements_data();

	$walk = static function ( array &$els ) use ( &$walk, &$tzp_home_done, $tzp_items, $tzp_home ) {
		foreach ( $els as &$el ) {
			if ( 'tzp_faq' === ( $el['widgetType'] ?? '' ) ) {
				$el['settings']['items']      = $tzp_items( $tzp_home );
				$el['settings']['exclusive']  = '';
				$el['settings']['schema']     = '';
				$el['settings']['more_text']  = 'Zobacz wszystkie pytania';
				$el['settings']['more_url']   = '';
				$el['settings']['more_align'] = 'center';
				$tzp_home_done                = true;
				return;
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( $elements );

	if ( $tzp_home_done ) {
		$doc->save( array( 'elements' => $elements ) );
		WP_CLI::log( 'Strona główna: FAQ skrócone do ' . count( $tzp_home ) . ' pytań, bez schemy, z linkiem do strony FAQ.' );
	}
}

if ( ! $tzp_home_done ) {
	WP_CLI::warning( 'Na stronie głównej nie ma widgetu FAQ (tzp_faq). Najpierw: tools/convert-accordion-to-faq.php.' );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
do_action( 'litespeed_purge_all' );

WP_CLI::success( 'Gotowe.' );
