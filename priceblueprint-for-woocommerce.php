<?php
/**
 * Plugin Name:       PriceBlueprint — Configurable Product Pricing for WooCommerce
 * Description:       Reusable pricing blueprints for WooCommerce. Assign one blueprint to multiple products — define attribute-based pricing rules once, update everywhere instantly.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Edgar Khachaturov
 * Author URI:        https://getpriceblueprint.com
 * Plugin URI:        https://getpriceblueprint.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       priceblueprint-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// To regenerate the POT file run:
// wp i18n make-pot . languages/priceblueprint.pot --domain=priceblueprint --exclude=vendor,node_modules

define( 'PRBP_VERSION',    '1.0.0' );
define( 'PRBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 compatible autoloader for namespace PRBP\ → /src/
 */
spl_autoload_register( function ( string $class ): void {
	$prefix = 'PRBP\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative = substr( $class, $len );
	$file     = PRBP_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Activation hook — register CPT so rewrite rules exist before flush.
 */
register_activation_hook( __FILE__, function (): void {
	PRBP\CPT\Blueprint::registerCPT();
	flush_rewrite_rules();
} );

/**
 * Boot plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	PRBP\Core\Plugin::instance()->init();
}, 10 );
