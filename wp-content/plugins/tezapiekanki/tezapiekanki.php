<?php
/**
 * Plugin Name:       TeZapiekanki
 * Description:       Funkcje sklepu tezapiekanki.pl niezależne od motywu: belka o wysyłce z popupem, FAQ rozwijane z danymi strukturalnymi (widget Elementora), wartość odżywcza z kartą produktu (PDF), piny smaków i dane produktu dla Google (wysyłka, zwroty). Terminy wysyłki i dostawy liczy wtyczka WooCommerce Ship Today Notice.
 * Version:           1.2.0
 * Author:            MS-M.pl
 * Author URI:        https://ms-m.pl
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Text Domain:       tezapiekanki
 */

defined( 'ABSPATH' ) || exit;

define( 'TZP_VERSION', '1.2.0' );
define( 'TZP_FILE', __FILE__ );
define( 'TZP_DIR', plugin_dir_path( __FILE__ ) );
define( 'TZP_URL', plugin_dir_url( __FILE__ ) );

require_once TZP_DIR . 'includes/functions.php';
require_once TZP_DIR . 'includes/class-shipping-bar.php';
require_once TZP_DIR . 'includes/class-shipping-bar-customizer.php';
require_once TZP_DIR . 'includes/class-faq.php';
require_once TZP_DIR . 'includes/class-product-faq.php';
require_once TZP_DIR . 'includes/class-product-card.php';
require_once TZP_DIR . 'includes/class-flavor-pins.php';
require_once TZP_DIR . 'includes/class-product-schema.php';
require_once TZP_DIR . 'includes/class-bundles.php';

add_action( 'plugins_loaded', array( 'TZP\Shipping_Bar', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Shipping_Bar_Customizer', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Faq', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Faq', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Card', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Flavor_Pins', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Schema', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Bundles', 'init' ) );
