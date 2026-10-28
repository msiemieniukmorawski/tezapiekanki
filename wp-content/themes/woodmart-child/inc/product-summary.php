<?php
/**
 * WooCommerce → Zestawienie produktów (product summary).
 *
 * CSV with the quantity of each product from "Processing" orders, filtered by shipping date
 * (wc-ship-today-notice: _wc_stn_ship_date; older orders: Iconic jckwds_date_ymd). Used for production planning.
 *
 * Moved 1:1 from the Woodmart theme's functions.php (2026-10-18). Page slug, form action
 * and nonce are unchanged; only the function names are new.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the module unless the old copy still lives in Woodmart's functions.php
 * (on production until it is removed or the theme is updated). Otherwise the menu would be duplicated.
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
 * WooCommerce menu item.
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
 * Date range form.
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
 * Generates and sends the CSV.
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

	// Excluded: bundles of 9 and 12 (6412, 6417) and the already deleted product 6420.
	$excluded_ids = array( 6420, 6417, 6412 );

	// Clear output buffers so no earlier output ends up in the CSV.
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	// Orders via the WooCommerce API (HPOS compatible).
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

		// Shipping date: from wc-ship-today-notice (Y-m-d), or from Iconic (Ymd) for orders placed before the switch. No migration.
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

	// Sort by quantity, descending.
	arsort( $products );

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=product-summary-' . date( 'Y-m-d-H-i-s' ) . '.csv' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

	// UTF-8 BOM for Excel.
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

	// Empty row before the total.
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
