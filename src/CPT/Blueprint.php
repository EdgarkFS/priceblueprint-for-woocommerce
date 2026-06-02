<?php
/**
 * Registers the price_blueprint custom post type.
 *
 * @package PRBP\CPT
 */

namespace PRBP\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blueprint {

	public static function register(): void {
		add_action( 'init',               [ self::class, 'registerCPT' ],  10 );
		add_action( 'before_delete_post', [ self::class, 'handleRemoval' ], 10, 1 );
		add_action( 'wp_trash_post',      [ self::class, 'handleRemoval' ], 10, 1 );
	}

	public static function registerCPT(): void {
		$labels = [
			'name'               => esc_html__( 'Price Blueprints',          'priceblueprint-for-woocommerce' ),
			'singular_name'      => esc_html__( 'Price Blueprint',           'priceblueprint-for-woocommerce' ),
			'menu_name'          => esc_html__( 'Price Blueprints',          'priceblueprint-for-woocommerce' ),
			'admin_bar_name'     => esc_html__( 'Price Blueprint',           'priceblueprint-for-woocommerce' ),
			'add_new'            => esc_html__( 'Add Template',               'priceblueprint-for-woocommerce' ),
			'add_new_item'       => esc_html__( 'Add New Price Blueprint',   'priceblueprint-for-woocommerce' ),
			'edit_item'          => esc_html__( 'Edit Price Blueprint',      'priceblueprint-for-woocommerce' ),
			'view_item'          => esc_html__( 'View Price Blueprint',      'priceblueprint-for-woocommerce' ),
			'search_items'       => esc_html__( 'Search Price Blueprints',   'priceblueprint-for-woocommerce' ),
			'not_found'          => esc_html__( 'No price blueprints found', 'priceblueprint-for-woocommerce' ),
			'not_found_in_trash' => esc_html__( 'No price blueprints found in Trash', 'priceblueprint-for-woocommerce' ),
		];

		$args = [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=product',
			'supports'     => [ 'title' ],
			'menu_icon'    => 'dashicons-tag',
			'rewrite'      => false,
		];

		register_post_type( 'price_blueprint', $args );
	}

	/**
	 * When a blueprint is trashed or permanently deleted, move all linked products
	 * to draft and clear their price, attributes, and blueprint reference.
	 *
	 * @param int $post_id
	 */
	public static function handleRemoval( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'price_blueprint' ) {
			return;
		}

		$linked_ids = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'prbp_template_id',
					'value' => $post_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		foreach ( $linked_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$product->set_status( 'draft' );
			$product->set_regular_price( '' );
			$product->set_price( '' );
			$product->set_sale_price( '' );
			$product->set_attributes( [] );
			$product->save();

			delete_post_meta( $product_id, 'prbp_template_id' );
		}
	}
}
