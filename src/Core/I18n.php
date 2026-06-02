<?php
/**
 * Internationalisation loader.
 *
 * @package PRBP\Core
 */

namespace PRBP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I18n {

	public static function register(): void {
		// JS translations registered after assets are enqueued.
		add_action( 'admin_enqueue_scripts', [ self::class, 'loadJsAdmin' ],   20 );
		add_action( 'wp_enqueue_scripts',    [ self::class, 'loadJsFrontend' ], 20 );
	}

	/**
	 * Enables translation of admin JS strings via wp_set_script_translations().
	 * Requires a matching JSON file: languages/priceblueprint-for-woocommerce-{locale}-prbp-admin.json
	 */
	public static function loadJsAdmin(): void {
		wp_set_script_translations( 'prbp-admin', 'priceblueprint-for-woocommerce', PRBP_PLUGIN_DIR . 'languages' );
	}

	public static function loadJsFrontend(): void {
		wp_set_script_translations( 'prbp-frontend', 'priceblueprint-for-woocommerce', PRBP_PLUGIN_DIR . 'languages' );
	}
}
