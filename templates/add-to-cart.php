<?php
/**
 * Add-to-cart template for prbp_configurable_product.
 *
 * Mirrors WooCommerce simple.php. Attribute selects are injected via the
 * woocommerce_before_add_to_cart_button hook (ProductPage::renderSelects).
 *
 * Variable available from Frontend\ProductPage::renderAddToCart():
 *   $product  WC_Product  Current configurable product.
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<div class="prbp-configurator" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">

	<form class="cart"
	      method="post"
	      action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>">

		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>
		<?php woocommerce_quantity_input( [], $product ); ?>
		<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>

		<?php
		$prbp_btn_class = implode( ' ', array_filter( [
			'single_add_to_cart_button',
			'button',
			'alt',
			function_exists( 'wc_wp_theme_get_element_class_name' ) ? wc_wp_theme_get_element_class_name( 'button' ) : '',
		] ) );
		?>
		<button type="submit"
		        name="add-to-cart"
		        value="<?php echo esc_attr( $product->get_id() ); ?>"
		        class="<?php echo esc_attr( $prbp_btn_class ); ?>">
			<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
		</button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	</form>

</div>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
