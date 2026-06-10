<?php
/**
 * Configurator selects template.
 *
 * Rendered inside WooCommerce's form.cart via the woocommerce_before_add_to_cart_button hook.
 *
 * Variables available from Frontend\ProductPage::renderSelects():
 *   $product            WC_Product      Current configurable product.
 *   $product_id         int             Product post ID.
 *   $grouped_rules      array           Rules grouped by attribute slug.
 *   $preselected        array           Attribute slug → value slug from valid GET params.
 *   $precomputed_price  float|null      Total price when all attrs are preselected, else null.
 *   $is_on_sale         bool            Whether the product currently has an active sale price.
 *   $regular_min_total  float           Minimum total using _regular_price as base (for strikethrough).
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum total: base price + cheapest option per attribute.
// Always computed — used as fallback when not all attrs are preselected,
// and stored in data attributes so JS can revert to it when selections are cleared.
$prbp_min_total = (float) $product->get_price();
foreach ( $grouped_rules as $prbp_attr_rules ) {
	$prbp_prices     = array_column( $prbp_attr_rules, 'price' );
	$prbp_min_total += (float) min( $prbp_prices );
}

// Price to display on initial render.
$prbp_initial_price = $precomputed_price ?? $prbp_min_total;
?>
<?php foreach ( $grouped_rules as $prbp_attribute => $prbp_rules ) : ?>
	<?php
	// Cheapest option slug — used as fallback when no valid GET param exists.
	$prbp_cheapest_slug = '';
	$prbp_min_price     = null;
	foreach ( $prbp_rules as $prbp_rule ) {
		$prbp_price = (float) $prbp_rule['price'];
		if ( $prbp_min_price === null || $prbp_price < $prbp_min_price ) {
			$prbp_min_price     = $prbp_price;
			$prbp_cheapest_slug = (string) ( $prbp_rule['value_slugs'][0] ?? '' );
		}
	}

	// GET param takes priority; fall back to cheapest.
	$prbp_selected_slug = $preselected[ $prbp_attribute ] ?? $prbp_cheapest_slug;
	?>
<div class="prbp-attribute-group" data-attribute="<?php echo esc_attr( $prbp_attribute ); ?>">
	<label class="prbp-attribute-label"
	       for="prbp_sel_<?php echo esc_attr( $prbp_attribute ); ?>">
		<?php echo esc_html( $prbp_rules[0]['attribute_label'] ); ?>
	</label>
	<div class="prbp-select-wrapper">
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
				        <?php selected( $prbp_slug === $prbp_selected_slug ); ?>>
					<?php echo esc_html( $prbp_label ); ?>
				</option>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</select>
	</div>
	<span class="prbp-field-error" aria-live="polite"></span>
</div>
<?php endforeach; ?>

<?php
$prbp_min_price_html = $is_on_sale
	? wc_format_sale_price( $regular_min_total, $prbp_min_total )
	: wc_price( $prbp_min_total );

$prbp_initial_html = $is_on_sale
	? wc_format_sale_price( $regular_min_total, $prbp_initial_price )
	: wc_price( $prbp_initial_price );
?>
<p class="price prbp-total-price"
   data-min-price="<?php echo esc_attr( $prbp_min_total ); ?>"
   data-min-price-html="<?php echo esc_attr( $prbp_min_price_html ); ?>">
	<?php echo wp_kses_post( $prbp_initial_html ); ?>
</p>
