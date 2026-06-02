<?php
/**
 * AJAX handler: prbp_calculate_price
 *
 * Public endpoint — calculates the total price given a product and selections.
 *
 * @package PRBP\Ajax
 */

namespace PRBP\Ajax;

use PRBP\Utils\RulesCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CalculatePrice {

	public static function register(): void {
		add_action( 'wp_ajax_prbp_calculate_price',        [ self::class, 'handle' ], 10 );
		add_action( 'wp_ajax_nopriv_prbp_calculate_price', [ self::class, 'handle' ], 10 );
	}

	public static function handle(): void {
		check_ajax_referer( 'prbp_frontend_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$selections = isset( $_POST['selections'] )
			? array_map( 'sanitize_key', wp_unslash( (array) $_POST['selections'] ) )
			: [];

		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$template_id = (int) get_post_meta( $product_id, 'prbp_template_id', true );

		if ( ! $template_id ) {
			wp_send_json_error( [ 'message' => __( 'No price blueprint assigned.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$rules = RulesCache::get( $template_id );
		$total = (float) get_post_meta( $product_id, '_price', true );

		foreach ( $selections as $attribute => $value_slug ) {
			$attribute  = sanitize_key( $attribute );
			$value_slug = sanitize_key( $value_slug );

			foreach ( $rules as $rule ) {
				if ( $rule['attribute'] === $attribute && in_array( $value_slug, $rule['value_slugs'] ?? [], true ) ) {
					$total += (float) $rule['price'];
					break;
				}
			}
		}

		wp_send_json_success( [
			'formatted' => wp_strip_all_tags( wc_price( $total ) ),
		] );
	}
}
