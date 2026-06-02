<?php
/**
 * Recalculates prices from fresh CPT rules before WooCommerce calculates totals.
 *
 * This ensures price changes in Price Blueprints take effect immediately,
 * even for items already sitting in the cart.
 *
 * @package PRBP\Cart
 */

namespace PRBP\Cart;

use PRBP\Utils\RulesCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PriceRecalculator {

	public static function register(): void {
		add_action( 'woocommerce_before_calculate_totals', [ self::class, 'recalculate' ], 10, 1 );
	}

	public static function recalculate( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			/** @var \WC_Product $product */
			$product = $cart_item['data'];

			if ( $product->get_type() !== 'prbp_configurable_product' ) {
				continue;
			}

			$template_id = $cart_item['prbp_template_id'] ?? 0;
			$selections  = $cart_item['prbp_selections']  ?? [];
			$base        = $cart_item['prbp_base_price']  ?? 0.0;

			if ( ! $template_id || ! $selections ) {
				continue;
			}

			// Load FRESH rules from CPT — never from stale cart data.
			$fresh_rules = RulesCache::get( $template_id );

			$additions = 0.0;
			foreach ( $selections as $sel ) {
				foreach ( $fresh_rules as $rule ) {
					if ( $rule['attribute'] === $sel['attribute']
						&& $rule['value_slug'] === $sel['value_slug']
					) {
						$additions += (float) $rule['price'];
						break;
					}
				}
			}

			$product->set_price( $base + $additions );
		}
	}
}
