<?php
/**
 * Saves PriceBlueprint selections to order items and displays them in admin and emails.
 *
 * Internal data is stored under _fp_* keys (WooCommerce hides these by default
 * because they start with an underscore — no manual hiding needed).
 *
 * Human-readable entries are stored with plain English keys so WooCommerce
 * renders them natively everywhere: order admin, customer emails, order-received
 * page, and My Account.
 *
 * @package PRBP\Cart
 */

namespace PRBP\Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderMetaDisplay {

	public static function register(): void {
		add_filter( 'woocommerce_hidden_order_itemmeta',              [ self::class, 'hideInternalKeys' ] );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ self::class, 'hideFrontendMeta' ], 10, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item',    [ self::class, 'saveToOrder' ], 10, 3 );
	}

	/**
	 * Remove internal prbp meta from the formatted list used by frontend templates
	 * (order-received, My Account, emails). Complements hideInternalKeys() which
	 * only covers the WP admin meta box.
	 *
	 * @param  \stdClass[] $formatted_meta
	 * @return \stdClass[]
	 */
	public static function hideFrontendMeta( array $formatted_meta ): array {
		$hidden = [ 'prbp_selections', 'prbp_template_id', 'prbp_base_price' ];
		return array_values(
			array_filter(
				$formatted_meta,
				static fn( \stdClass $meta ): bool => ! in_array( $meta->key, $hidden, true )
			)
		);
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function hideInternalKeys( array $keys ): array {
		$keys[] = 'prbp_selections';
		$keys[] = 'prbp_template_id';
		$keys[] = 'prbp_base_price';
		return $keys;
	}

	public static function saveToOrder(
		\WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values
	): void {
		if ( empty( $values['prbp_selections'] ) ) {
			return;
		}

		// Internal data — used by PriceRecalculator; hidden via hideInternalKeys().
		$item->add_meta_data( 'prbp_selections',  wp_json_encode( $values['prbp_selections'] ), true );
		$item->add_meta_data( 'prbp_template_id', $values['prbp_template_id'],                   true );
		$item->add_meta_data( 'prbp_base_price',  $values['prbp_base_price'],                    true );

		// One row per selected option: "Color: Red".
		foreach ( $values['prbp_selections'] as $rule ) {
			$label = ! empty( $rule['attribute_label'] )
				? $rule['attribute_label']
				: wc_attribute_label( $rule['attribute'] );

			$value = ! empty( $rule['value_label'] )
				? $rule['value_label']
				: $rule['value_slug'];

			$item->add_meta_data( $label, $value );
		}
	}
}
