<?php
/**
 * AJAX handler: prbp_get_terms
 *
 * Returns taxonomy terms for a given WC global attribute (pa_*).
 * Logged-in only.
 *
 * @package PRBP\Ajax
 */

namespace PRBP\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetTerms {

	public static function register(): void {
		add_action( 'wp_ajax_prbp_get_terms', [ self::class, 'handle' ], 10 );
	}

	public static function handle(): void {
		check_ajax_referer( 'prbp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$attribute = isset( $_POST['attribute'] ) ? sanitize_key( wp_unslash( $_POST['attribute'] ) ) : '';

		if ( ! preg_match( '/^pa_[a-z0-9_]+$/', $attribute ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid attribute.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$terms = get_terms( [
			'taxonomy'   => $attribute,
			'hide_empty' => false,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not retrieve attribute terms.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$data = array_map( fn( \WP_Term $term ): array => [
			'id'   => $term->term_id,
			'slug' => $term->slug,
			'name' => $term->name,
		], $terms );

		wp_send_json_success( $data );
	}
}
