<?php
/**
 * AJAX handlers for the Quick Setup feature.
 *
 * @package PRBP\Ajax
 */

namespace PRBP\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QuickSetup {

	public static function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'wp_ajax_prbp_search_products',        [ self::class, 'searchProducts' ] );
		add_action( 'wp_ajax_prbp_get_product_attributes', [ self::class, 'getProductAttributes' ] );
	}

	public static function searchProducts(): void {
		check_ajax_referer( 'prbp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$term = isset( $_POST['term'] )
			? sanitize_text_field( wp_unslash( $_POST['term'] ) )
			: '';

		$products = wc_get_products( [
			's'      => $term,
			'limit'  => 20,
			'status' => 'publish',
			'return' => 'objects',
		] );

		$data = array_map(
			fn( $p ) => [ 'id' => $p->get_id(), 'title' => $p->get_name() ],
			$products
		);

		wp_send_json_success( $data );
	}

	public static function getProductAttributes(): void {
		check_ajax_referer( 'prbp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$data = [];

		foreach ( $product->get_attributes() as $attr ) {
			if ( ! $attr->is_taxonomy() ) {
				continue;
			}

			$wc_terms = get_terms( [
				'taxonomy'   => $attr->get_name(),
				'include'    => $attr->get_options(),
				'orderby'    => 'include',
				'hide_empty' => false,
			] );

			if ( is_wp_error( $wc_terms ) || empty( $wc_terms ) ) {
				continue;
			}

			$data[] = self::buildAttributeEntry(
				$attr->get_name(),
				wc_attribute_label( $attr->get_name() ),
				$wc_terms
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * Build a single attribute entry for the AJAX response.
	 *
	 * value_ids are cast to strings because Tom Select stores option keys as
	 * strings — pre-selection via ts.options[id] only matches if types agree.
	 *
	 * @param  string     $slug   Taxonomy slug, e.g. "pa_size".
	 * @param  string     $label  Human-readable attribute label.
	 * @param  \WP_Term[] $terms  Terms assigned to the product.
	 * @return array{slug:string, label:string, value_ids:string[], value_slugs:string[], value_labels:string[]}
	 */
	private static function buildAttributeEntry( string $slug, string $label, array $terms ): array {
		return [
			'slug'         => $slug,
			'label'        => $label,
			'value_ids'    => array_map( fn( $t ) => (string) $t->term_id, $terms ),
			'value_slugs'  => array_map( fn( $t ) => $t->slug,             $terms ),
			'value_labels' => array_map( fn( $t ) => $t->name,             $terms ),
		];
	}
}
