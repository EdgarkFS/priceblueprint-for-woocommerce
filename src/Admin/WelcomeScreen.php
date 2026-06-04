<?php
/**
 * One-time welcome screen shown after first activation.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WelcomeScreen {

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'addPage' ] );
		add_action( 'admin_init', [ self::class, 'maybeRedirect' ] );
	}

	public static function addPage(): void {
		add_dashboard_page(
			__( 'Welcome to PriceBlueprint', 'priceblueprint-for-woocommerce' ),
			'',
			'manage_options',
			'prbp-welcome',
			[ self::class, 'render' ]
		);
	}

	public static function maybeRedirect(): void {
		if ( ! get_option( 'prbp_show_welcome' ) ) {
			return;
		}

		// Skip on bulk activation or network activation.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) {
			return;
		}

		// Let Freemius finish its opt-in flow first.
		if ( function_exists( 'pfwp_fs' ) && pfwp_fs()->is_activation_mode() ) {
			return;
		}

		// Already on the welcome page — don't redirect again.
		if ( isset( $_GET['page'] ) && 'prbp-welcome' === $_GET['page'] ) {
			return;
		}

		wp_safe_redirect( admin_url( 'index.php?page=prbp-welcome' ) );
		exit;
	}

	public static function render(): void {
		delete_option( 'prbp_show_welcome' );

		wp_enqueue_style(
			'prbp-welcome-screen',
			PRBP_PLUGIN_URL . 'assets/css/welcome-screen.css',
			[],
			PRBP_VERSION
		);

		require PRBP_PLUGIN_DIR . 'templates/welcome-screen.php';
	}
}