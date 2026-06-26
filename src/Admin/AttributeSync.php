<?php
/**
 * Syncs WooCommerce attributes from a price blueprint to the product.
 *
 * Runs after the template ID is saved so every attribute used in the
 * template's active rules appears in the product's Attributes tab.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

use PRBP\Utils\BlueprintType;
use PRBP\Utils\RulesCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttributeSync {

	public static function register(): void {
		// Priority 20 — runs after ProductMetaBox::save (priority 10) has written prbp_template_id.
		add_action( 'woocommerce_process_product_meta', [ self::class, 'sync' ], 20, 1 );
	}

	/**
	 * Sync template attributes to the product on save.
	 *
	 * @param int $post_id
	 */
	public static function sync( int $post_id ): void {
		// nonce already verified by WooCommerce before this hook fires.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product_type = isset( $_POST['product-type'] ) ? sanitize_key( wp_unslash( $_POST['product-type'] ) ) : '';
		if ( 'prbp_configurable_product' !== $product_type ) {
			return;
		}

		$template_id = (int) get_post_meta( $post_id, 'prbp_template_id', true );
		if ( ! $template_id ) {
			return;
		}

		if ( BlueprintType::isInformational( $template_id ) ) {
			self::syncInformational( $post_id );
			return;
		}

		$rules = RulesCache::get( $template_id, true );
		if ( empty( $rules ) ) {
			return;
		}

		// Collect term IDs grouped by attribute taxonomy from active rules.
		$attr_map = [];
		foreach ( $rules as $rule ) {
			$taxonomy   = $rule['attribute'] ?? '';
			$value_ids  = (array) ( $rule['value_ids'] ?? [] );
			foreach ( $value_ids as $term_id ) {
				$term_id = (int) $term_id;
				if ( $taxonomy && $term_id ) {
					$attr_map[ $taxonomy ][] = $term_id;
				}
			}
		}

		if ( empty( $attr_map ) ) {
			return;
		}

		$product_attrs = (array) get_post_meta( $post_id, '_product_attributes', true );
		$position      = count( $product_attrs );

		foreach ( $attr_map as $taxonomy => $term_ids ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Merge template terms with any already assigned to the product.
			$current = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $current ) ) {
				$current = [];
			}
			wp_set_object_terms(
				$post_id,
				array_values( array_unique( array_merge( $current, $term_ids ) ) ),
				$taxonomy
			);

			// Register the attribute on the product if it is not yet listed.
			if ( ! isset( $product_attrs[ $taxonomy ] ) ) {
				$product_attrs[ $taxonomy ] = [
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				];
			}
		}

		update_post_meta( $post_id, '_product_attributes', $product_attrs );
		wc_delete_product_transients( $post_id );
	}

	private static function syncInformational( int $post_id ): void {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		$product_attrs = (array) get_post_meta( $post_id, '_product_attributes', true );
		$position      = count( $product_attrs );

		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$term_ids = wp_list_pluck( $terms, 'term_id' );
			$current  = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $current ) ) {
				$current = [];
			}

			wp_set_object_terms(
				$post_id,
				array_values( array_unique( array_merge( $current, array_map( 'intval', $term_ids ) ) ) ),
				$taxonomy
			);

			if ( ! isset( $product_attrs[ $taxonomy ] ) ) {
				$product_attrs[ $taxonomy ] = [
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				];
			}
		}

		update_post_meta( $post_id, '_product_attributes', $product_attrs );
		wc_delete_product_transients( $post_id );
	}
}
