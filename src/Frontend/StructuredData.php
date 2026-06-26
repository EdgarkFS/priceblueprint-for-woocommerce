<?php
/**
 * Schema.org structured data override for prbp_configurable_product.
 *
 * Replaces WooCommerce's unreliable offers block with one based on
 * the product's base price, so search engines receive consistent data.
 *
 * @package PRBP\Frontend
 */

namespace PRBP\Frontend;

use PRBP\Utils\BlueprintType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StructuredData {

	public static function register(): void {
		add_filter( 'woocommerce_structured_data_product', [ self::class, 'override' ], 10, 2 );
	}

	/**
	 * @param array       $markup  Structured data array built by WooCommerce.
	 * @param \WC_Product $product Current product.
	 * @return array
	 */
	public static function override( array $markup, \WC_Product $product ): array {
		if ( $product->get_type() !== 'prbp_configurable_product' ) {
			return $markup;
		}

		$template_id = (int) get_post_meta( $product->get_id(), 'prbp_template_id', true );
		if ( ! $template_id || BlueprintType::isInformational( $template_id ) ) {
			return $markup;
		}

		$base_price = ProductPage::getMinPrice( $product->get_id() );
		$currency   = get_woocommerce_currency();

		$markup['offers'] = [
			'@type'              => 'Offer',
			'price'              => $base_price,
			'priceCurrency'      => $currency,
			'availability'       => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'url'                => get_permalink( $product->get_id() ),
			'priceSpecification' => [
				'@type'         => 'PriceSpecification',
				'price'         => $base_price,
				'priceCurrency' => $currency,
				'description'   => __( 'Price from', 'priceblueprint-for-woocommerce' ),
			],
		];

		return $markup;
	}
}
