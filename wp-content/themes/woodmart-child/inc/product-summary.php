<?php
/**
 * WooCommerce → Zestawienie produktów.
 *
 * CSV z liczbą sztuk każdego produktu z zamówień "W realizacji", filtrowanych po dacie wysyłki
 * (wc-ship-today-notice: _wc_stn_ship_date; starsze zamówienia: Iconic jckwds_date_ymd). Służy do planowania produkcji.
 *
 * Przeniesione 1:1 z functions.php motywu Woodmart (2026-10-18). Slug strony, akcja formularza
 * i nonce bez zmian, nowe są tylko nazwy funkcji.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejestruje moduł, chyba że stara kopia nadal siedzi w functions.php Woodmarta
 * (na produkcji do czasu jej usunięcia albo aktualizacji motywu). Bez tego menu byłoby zdublowane.
 */
function tzp_product_summary_init() {
	if ( function_exists( 'wc_product_summary_page' ) ) {
		return;
	}

	add_action( 'admin_menu', 'tzp_product_summary_menu' );
	add_action( 'admin_post_generate_product_summary', 'tzp_generate_product_summary' );
}
add_action( 'after_setup_theme', 'tzp_product_summary_init' );

/**
 * Pozycja w menu WooCommerce.
 */
function tzp_product_summary_menu() {
	add_submenu_page(
		'woocommerce',
		'Zestawienie produktów',
		'Zestawienie produktów',
		'manage_woocommerce',
		'wc-product-summary',
		'tzp_product_summary_page'
	);
}

/**
 * Formularz z zakresem dat.
 */
function tzp_product_summary_page() {
	?>
	<div class="wrap">

		<h1>Zestawienie produktów wg terminu wysyłki</h1>

		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

			<input type="hidden" name="action" value="generate_product_summary">

			<?php wp_nonce_field( 'wc_product_summary_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">Data od</th>
					<td>
						<input type="date" name="date_from" required>
					</td>
				</tr>

				<tr>
					<th scope="row">Data do</th>
					<td>
						<input type="date" name="date_to" required>
					</td>
				</tr>
			</table>

			<p>
				<button class="button button-primary">
					Generuj CSV
				</button>
			</p>

		</form>

	</div>
	<?php
}

/**
 * Generuje i wysyła CSV.
 */
function tzp_generate_product_summary() {

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Brak uprawnień' );
	}

	check_admin_referer( 'wc_product_summary_nonce' );

	$date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
	$date_to   = sanitize_text_field( $_POST['date_to'] ?? '' );

	if ( ! $date_from || ! $date_to ) {
		wp_die( 'Brak zakresu dat' );
	}

	// Wykluczone: zestawy 9 i 12 (6412, 6417) oraz usunięty już produkt 6420.
	$excluded_ids = array( 6420, 6417, 6412 );

	// Czyścimy bufory, żeby do CSV nie trafił żaden wcześniejszy output.
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	// Zamówienia przez API WooCommerce (zgodne z HPOS).
	$orders = wc_get_orders(
		array(
			'limit'  => -1,
			'status' => array( 'processing' ),
			'return' => 'objects',
		)
	);

	$products  = array();
	$total_all = 0;

	foreach ( $orders as $order ) {

		// Data wysyłki: z wc-ship-today-notice (Y-m-d), a dla zamówień sprzed przełączenia z Iconic (Ymd). Bez migracji.
		$delivery_date_formatted = (string) $order->get_meta( '_wc_stn_ship_date' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $delivery_date_formatted ) ) {
			$delivery_date = $order->get_meta( 'jckwds_date_ymd' );

			if ( empty( $delivery_date ) ) {
				continue;
			}

			$delivery_date_formatted = DateTime::createFromFormat( 'Ymd', $delivery_date );

			if ( ! $delivery_date_formatted ) {
				continue;
			}

			$delivery_date_formatted = $delivery_date_formatted->format( 'Y-m-d' );
		}

		if (
			$delivery_date_formatted < $date_from ||
			$delivery_date_formatted > $date_to
		) {
			continue;
		}

		foreach ( $order->get_items() as $item ) {

			$product_id = $item->get_product_id();

			if ( in_array( $product_id, $excluded_ids, true ) ) {
				continue;
			}

			$name = trim( $item->get_name() );
			$qty  = (int) $item->get_quantity();

			if ( empty( $name ) || $qty <= 0 ) {
				continue;
			}

			if ( ! isset( $products[ $name ] ) ) {
				$products[ $name ] = 0;
			}

			$products[ $name ] += $qty;
			$total_all         += $qty;
		}
	}

	// Sortowanie malejąco po liczbie sztuk.
	arsort( $products );

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=product-summary-' . date( 'Y-m-d-H-i-s' ) . '.csv' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

	// BOM UTF-8 dla Excela.
	fwrite( $output, "\xEF\xBB\xBF" );

	fputcsv(
		$output,
		array(
			'Produkt',
			'Ilość',
		),
		';'
	);

	foreach ( $products as $name => $qty ) {
		fputcsv(
			$output,
			array(
				$name,
				$qty,
			),
			';'
		);
	}

	// Pusty wiersz przed sumą.
	fputcsv( $output, array(), ';' );

	fputcsv(
		$output,
		array(
			'SUMA',
			$total_all,
		),
		';'
	);

	fclose( $output );
	exit;
}
