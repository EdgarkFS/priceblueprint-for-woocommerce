<?php
/**
 * WooCommerce Configurable Product type.
 *
 * @package PRBP\ProductType
 */

namespace PRBP\ProductType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConfigurableProduct extends \WC_Product {

	/**
	 * Register all hooks for this module.
	 */
	public static function register(): void {
		// product_type_selector is the filter wc_get_product_types() actually applies.
		add_filter( 'product_type_selector',     [ self::class, 'addToSelector' ], 10, 1 );
		add_filter( 'woocommerce_product_class', [ self::class, 'mapClass' ],      10, 2 );

		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
	}

	/**
	 * Add this type to the WooCommerce product-type dropdown.
	 *
	 * @param array<string, string> $types
	 * @return array<string, string>
	 */
	public static function addToSelector( array $types ): array {
		$types['prbp_configurable_product'] = __( 'Configurable Product', 'priceblueprint-for-woocommerce' );
		return $types;
	}

	/**
	 * Map the type slug to this class so WooCommerce instantiates it correctly.
	 *
	 * @param string $classname    Current resolved class name.
	 * @param string $product_type Product type slug.
	 * @return string
	 */
	public static function mapClass( string $classname, string $product_type ): string {
		if ( 'prbp_configurable_product' === $product_type ) {
			return self::class;
		}
		return $classname;
	}

	public static function enqueueAssets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_style(
			'prbp-admin-product',
			PRBP_PLUGIN_URL . 'assets/css/admin-product.css',
			[],
			PRBP_VERSION
		);

		wp_enqueue_script(
			'prbp-admin-product',
			PRBP_PLUGIN_URL . 'assets/js/admin-product.js',
			[ 'jquery' ],
			PRBP_VERSION . '.' . filemtime( PRBP_PLUGIN_DIR . 'assets/js/admin-product.js' ),
			true
		);

	}

	/**
	 * Return the product type slug.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'prbp_configurable_product';
	}
}
