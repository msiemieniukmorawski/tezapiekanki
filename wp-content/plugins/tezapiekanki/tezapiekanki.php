<?php
/**
 * Plugin Name:       TeZapiekanki
 * Description:       Theme-independent features of the tezapiekanki.pl shop: shipping bar with a popup, collapsible FAQ with structured data (Elementor widget), nutrition facts with a product data sheet (PDF), flavor pins and product data for Google (shipping, returns). Shipping and delivery dates are calculated by the WooCommerce Ship Today Notice plugin.
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
require_once TZP_DIR . 'includes/class-emails.php';

add_action( 'plugins_loaded', array( 'TZP\Shipping_Bar', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Shipping_Bar_Customizer', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Faq', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Faq', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Card', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Flavor_Pins', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Product_Schema', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Bundles', 'init' ) );
add_action( 'plugins_loaded', array( 'TZP\Emails', 'init' ) );
