<?php
/**
 * AJAX handler for one-click demo data import.
 *
 * @package PRBP\Ajax
 */

namespace PRBP\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImportDemo {

	public static function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'wp_ajax_prbp_import_demo', [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		check_ajax_referer( 'prbp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$blueprint_id = (int) get_option( 'prbp_demo_blueprint_id' );
		$product_id   = (int) get_option( 'prbp_demo_product_id' );

		if ( $blueprint_id && $product_id
			&& false !== get_post_status( $blueprint_id )
			&& false !== get_post_status( $product_id )
		) {
			wp_send_json_success( self::buildUrls( $blueprint_id, $product_id ) );
		}

		$json_path = PRBP_PLUGIN_DIR . 'demo/blueprint-chair.json';

		if ( ! file_exists( $json_path ) ) {
			wp_send_json_error( [ 'message' => __( 'Demo data file not found.', 'priceblueprint-for-woocommerce' ) ] );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = json_decode( file_get_contents( $json_path ), true );

		if ( ! is_array( $json ) ) {
			wp_send_json_error( [ 'message' => __( 'Demo data file is invalid.', 'priceblueprint-for-woocommerce' ) ] );
		}

		$attr_data = self::ensureAttributesAndTerms( $json['attributes'] );

		$blueprint_id = self::createBlueprint( $json['title'], $attr_data );
		if ( is_wp_error( $blueprint_id ) ) {
			wp_send_json_error( [ 'message' => $blueprint_id->get_error_message() ] );
		}

		$product_id = self::createProduct( $blueprint_id, $attr_data );
		if ( is_wp_error( $product_id ) ) {
			wp_send_json_error( [ 'message' => $product_id->get_error_message() ] );
		}

		update_option( 'prbp_demo_blueprint_id', $blueprint_id );
		update_option( 'prbp_demo_product_id',   $product_id );

		wp_send_json_success( self::buildUrls( $blueprint_id, $product_id ) );
	}

	/**
	 * @param  array<int, array<string, mixed>> $attributes
	 * @return array<string, array<string, mixed>>
	 */
	private static function ensureAttributesAndTerms( array $attributes ): array {
		$attr_data = [];

		foreach ( $attributes as $attr ) {
			$name     = $attr['name'];
			$slug     = sanitize_title( strtolower( $name ) );
			$taxonomy = 'pa_' . $slug;

			// Use existing WC attribute or create a new one.
			$existing = null;
			foreach ( wc_get_attribute_taxonomies() as $wc_attr ) {
				if ( $wc_attr->attribute_name === $slug ) {
					$existing = $wc_attr;
					break;
				}
			}

			if ( ! $existing ) {
				wc_create_attribute( [
					'name' => $name,
					'slug' => $slug,
					'type' => 'select',
				] );
			}

			// Ensure taxonomy is available in the current request.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				wc_register_attribute_taxonomies();
			}

			$values = [];
			foreach ( $attr['values'] as $value ) {
				$term = get_term_by( 'name', $value['label'], $taxonomy );

				if ( $term instanceof \WP_Term ) {
					$term_id   = $term->term_id;
					$term_slug = $term->slug;
				} else {
					$result   = wp_insert_term( $value['label'], $taxonomy );
					$term_id  = $result['term_id'];
					$term_obj = get_term( $term_id, $taxonomy );
					$term_slug = ( $term_obj instanceof \WP_Term ) ? $term_obj->slug : sanitize_title( $value['label'] );
				}

				$values[] = [
					'term_id' => $term_id,
					'slug'    => $term_slug,
					'label'   => $value['label'],
					'price'   => $value['price'],
				];
			}

			$attr_data[ $name ] = [
				'taxonomy' => $taxonomy,
				'label'    => $name,
				'values'   => $values,
			];
		}

		return $attr_data;
	}

	/**
	 * @param  string                              $title
	 * @param  array<string, array<string, mixed>> $attr_data
	 * @return int|\WP_Error
	 */
	private static function createBlueprint( string $title, array $attr_data ) {
		$blueprint_id = wp_insert_post(
			[
				'post_type'   => 'price_blueprint',
				'post_title'  => '[Demo] ' . $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $blueprint_id ) ) {
			return $blueprint_id;
		}

		$rules = [];

		foreach ( $attr_data as $attr ) {
			foreach ( $attr['values'] as $value ) {
				$rules[] = [
					'attribute'       => $attr['taxonomy'],
					'attribute_label' => $attr['label'],
					'value_ids'       => [ $value['term_id'] ],
					'value_slugs'     => [ $value['slug'] ],
					'value_labels'    => [ $value['label'] ],
					'price'           => (string) $value['price'],
					'operator'        => '+',
				];
			}
		}

		update_post_meta( $blueprint_id, 'prbp_template_rules', wp_json_encode( $rules ) );

		return $blueprint_id;
	}

	/**
	 * @param  int                                 $blueprint_id
	 * @param  array<string, array<string, mixed>> $attr_data
	 * @return int|\WP_Error
	 */
	private static function createProduct( int $blueprint_id, array $attr_data ) {
		$product_id = wp_insert_post(
			[
				'post_type'   => 'product',
				'post_title'  => '[Demo] Wood Chair',
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		// Set product type directly — wc_get_product() cannot reliably instantiate
		// a custom type for a brand-new post inside an AJAX request.
		wp_set_object_terms( $product_id, 'prbp_configurable_product', 'product_type' );

		// WooCommerce reads price from these two meta keys.
		update_post_meta( $product_id, '_price',         '25.00' );
		update_post_meta( $product_id, '_regular_price', '25.00' );

		$product_attrs = [];
		$position      = 0;

		foreach ( $attr_data as $attr ) {
			$taxonomy = $attr['taxonomy'];
			$term_ids = array_column( $attr['values'], 'term_id' );

			wp_set_object_terms( $product_id, $term_ids, $taxonomy );

			$product_attrs[ $taxonomy ] = [
				'name'         => $taxonomy,
				'value'        => '',
				'position'     => $position++,
				'is_visible'   => 1,
				'is_variation' => 0,
				'is_taxonomy'  => 1,
			];
		}

		update_post_meta( $product_id, '_product_attributes', $product_attrs );
		update_post_meta( $product_id, 'prbp_template_id',    $blueprint_id );
		wc_delete_product_transients( $product_id );

		return $product_id;
	}

	/**
	 * @param  int $blueprint_id
	 * @param  int $product_id
	 * @return array<string, string>
	 */
	private static function buildUrls( int $blueprint_id, int $product_id ): array {
		$product = wc_get_product( $product_id );

		return [
			'blueprint_edit_url' => (string) get_edit_post_link( $blueprint_id, 'raw' ),
			'product_edit_url'   => (string) get_edit_post_link( $product_id, 'raw' ),
			'product_url'        => $product ? $product->get_permalink() : (string) get_permalink( $product_id ),
		];
	}
}
