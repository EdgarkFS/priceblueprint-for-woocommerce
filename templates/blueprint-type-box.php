<?php
/**
 * Admin template: Blueprint Settings sidebar meta box.
 *
 * Variables available from RulesRepeater::renderSidebarBox():
 *   $post                   WP_Post  Current price_blueprint post.
 *   $is_informational       bool     Whether the blueprint is informational.
 *   $info_attributes        array    Selected taxonomy slugs.
 *   $attribute_taxonomies   array    WC global attribute taxonomy objects.
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script>
var prbpIsInformational = <?php echo wp_json_encode( $is_informational ); ?>;
</script>

<div class="prbp-blueprint-settings"
     x-data="blueprintTypeBox(prbpIsInformational)"
     x-cloak>

	<label class="prbp-is-informational-label">
		<input type="checkbox"
		       name="prbp_is_informational"
		       value="1"
		       <?php checked( $is_informational ); ?>
		       x-model="isInformational">
		<strong><?php esc_html_e( 'Informational blueprint', 'priceblueprint-for-woocommerce' ); ?></strong>
	</label>

	<p class="prbp-is-informational-desc">
		<?php esc_html_e( 'When checked, linked products will not show any customization options or select boxes. All attributes are synced to every linked product for filtering and display only — pricing rules are ignored.', 'priceblueprint-for-woocommerce' ); ?>
	</p>

</div>
