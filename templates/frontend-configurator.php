<?php
/**
 * Frontend template: product configurator.
 *
 * Variables available from Frontend\ProductPage::render():
 *   $product        WC_Product  Current configurable product.
 *   $product_id     int         Product post ID.
 *   $template_id    int         Pricing template post ID.
 *   $grouped_rules  array       Rules grouped by attribute slug.
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum total: base price + cheapest option per attribute.
$prbp_min_total = (float) $product->get_price();
foreach ( $grouped_rules as $prbp_attr_rules ) {
	$prbp_prices      = array_column( $prbp_attr_rules, 'price' );
	$prbp_min_total  += (float) min( $prbp_prices );
}
?>
<div class="prbp-configurator" data-product-id="<?php echo esc_attr( $product_id ); ?>">

	<form class="prbp-form cart"
	      method="post"
	      action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>">

		<?php foreach ( $grouped_rules as $prbp_attribute => $prbp_rules ) : ?>
			<?php
			// Pre-select the cheapest option for this attribute.
			$prbp_cheapest_slug = '';
			$prbp_min_price     = null;
			foreach ( $prbp_rules as $prbp_rule ) {
				$prbp_price = (float) $prbp_rule['price'];
				if ( $prbp_min_price === null || $prbp_price < $prbp_min_price ) {
					$prbp_min_price     = $prbp_price;
					$prbp_cheapest_slug = (string) ( $prbp_rule['value_slugs'][0] ?? '' );
				}
			}
			?>
		<div class="prbp-attribute-group" data-attribute="<?php echo esc_attr( $prbp_attribute ); ?>">
			<label class="prbp-attribute-label"
			       for="prbp_sel_<?php echo esc_attr( $prbp_attribute ); ?>">
				<?php echo esc_html( $prbp_rules[0]['attribute_label'] ); ?>
			</label>
			<select name="prbp_selections[<?php echo esc_attr( $prbp_attribute ); ?>]"
			        id="prbp_sel_<?php echo esc_attr( $prbp_attribute ); ?>"
			        class="prbp-attribute-select"
			        data-attribute="<?php echo esc_attr( $prbp_attribute ); ?>"
			        required>
				<?php foreach ( $prbp_rules as $prbp_rule ) : ?>
					<?php
					$prbp_slugs  = (array) ( $prbp_rule['value_slugs']  ?? [] );
					$prbp_labels = (array) ( $prbp_rule['value_labels'] ?? [] );
					foreach ( $prbp_slugs as $prbp_vi => $prbp_slug ) :
						$prbp_label = $prbp_labels[ $prbp_vi ] ?? $prbp_slug;
					?>
					<option value="<?php echo esc_attr( $prbp_slug ); ?>"
					        data-price="<?php echo esc_attr( $prbp_rule['price'] ); ?>"
					        <?php selected( $prbp_slug === $prbp_cheapest_slug ); ?>>
						<?php echo esc_html( $prbp_label ); ?>
					</option>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</select>
			<span class="prbp-field-error" aria-live="polite"></span>
		</div>
		<?php endforeach; ?>

		<div class="prbp-price-display">
			<span class="prbp-price-label">
				<?php esc_html_e( 'Total:', 'priceblueprint-for-woocommerce' ); ?>
			</span>
			<span class="prbp-total-price"
			      data-min-price="<?php echo esc_attr( $prbp_min_total ); ?>">
				<?php echo wp_kses_post( wc_price( $prbp_min_total ) ); ?>
			</span>
		</div>

		<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>">
		<input type="hidden" name="quantity"    value="1">

		<button type="submit" class="prbp-add-to-cart single_add_to_cart_button button alt">
			<?php esc_html_e( 'Add to cart', 'priceblueprint-for-woocommerce' ); ?>
		</button>

	</form>

</div>
