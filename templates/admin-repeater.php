<?php
/**
 * Admin template: Pricing Rules repeater table.
 *
 * Variables available from RulesRepeater::render():
 *   $post                   WP_Post  Current price_blueprint post.
 *   $rules                  array    Decoded rule objects.
 *   $attribute_taxonomies   array    WC global attribute taxonomy objects.
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_nonce_field( 'prbp_rules_nonce', 'prbp_rules_nonce_field' );
?>

<script>
/* Data injected by PHP — consumed by the rulesRepeater Alpine component. */
var prbpRulesData = <?php echo wp_json_encode( $rules ?: [] ); ?>;
var prbpAttrsData = <?php echo wp_json_encode(
	array_values(
		array_map(
			function ( $tax ) {
				return [
					'slug'  => wc_attribute_taxonomy_name( $tax->attribute_name ),
					'label' => $tax->attribute_label,
				];
			},
			$attribute_taxonomies
		)
	)
); ?>;
</script>

<div class="prbp-admin-wrap"
     x-data="rulesRepeater(prbpRulesData, prbpAttrsData)"
     x-cloak>

	<!-- ── Error banner ───────────────────────────────────────────────────── -->
	<div class="prbp-error-banner"
	     x-show="errorMsg"
	     x-text="errorMsg"
	     style="display:none;"></div>

	<!-- ── Filter bar ────────────────────────────────────────────────────── -->
	<div class="prbp-filter-bar">
		<input type="text"
		       x-model.debounce.200ms="query"
		       placeholder="<?php esc_attr_e( 'Filter by attribute or value…', 'priceblueprint-for-woocommerce' ); ?>"
		       autocomplete="off">
		<span class="prbp-rules-count" x-text="countLabel"></span>
	</div>

	<!-- ── Rules table ───────────────────────────────────────────────────── -->
	<table class="prbp-rules-table widefat">
		<thead>
			<tr>
				<th class="prbp-col-index">#</th>
				<th class="prbp-col-attribute"><?php esc_html_e( 'Attribute', 'priceblueprint-for-woocommerce' ); ?></th>
				<th class="prbp-col-value"><?php esc_html_e( 'Value', 'priceblueprint-for-woocommerce' ); ?></th>
				<th class="prbp-col-price"><?php esc_html_e( 'Price', 'priceblueprint-for-woocommerce' ); ?></th>
				<th class="prbp-col-actions"><?php esc_html_e( 'Actions', 'priceblueprint-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>

			<template x-for="entry in displayRules" :key="entry.rule._uid">
				<tr x-show="entry.inDom"
				    :class="{'prbp-row--deleted': entry.rule.status === 'deleted'}">

					<!-- # -->
					<td class="prbp-col-index">
						<span x-text="entry.pos || ''"></span>
					</td>

					<!-- Attribute ──────────────────────────────────────── -->
					<td class="prbp-col-attribute">
						<select @change="onAttributeChange(entry.rule, $event)">
							<option value=""><?php esc_html_e( '— Select attribute —', 'priceblueprint-for-woocommerce' ); ?></option>
							<?php foreach ( $attribute_taxonomies as $prbp_tax ) : ?>
								<?php $prbp_slug = wc_attribute_taxonomy_name( $prbp_tax->attribute_name ); ?>
								<option value="<?php echo esc_attr( $prbp_slug ); ?>"
								        :selected="entry.rule.attribute === '<?php echo esc_js( $prbp_slug ); ?>'">
									<?php echo esc_html( $prbp_tax->attribute_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>

					<!-- Value (Tom Select) ──────────────────────────────── -->
					<td class="prbp-col-value">
						<!--
						  x-init fires once when Alpine creates this element.
						  initValueSelect wires up Tom Select, loads terms via AJAX,
						  and pre-selects any saved values.
						-->
						<select multiple
						        class="prbp-value-select"
						        x-init="initValueSelect($el, entry.rule)">
						</select>
					</td>

					<!-- Price ──────────────────────────────────────────── -->
					<td class="prbp-col-price">
						<span class="prbp-price-wrap">
							<input type="number"
							       class="prbp-price-input"
							       x-model="entry.rule.price"
							       step="0.01"
							       min="0"
							       placeholder="0.00">
							<span class="prbp-price-currency" aria-hidden="true"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						</span>
					</td>

					<!-- Actions ────────────────────────────────────────── -->
					<td class="prbp-col-actions">

						<!-- Delete (shown for active rows) -->
						<button type="button"
						        class="prbp-delete-btn button button-small"
						        title="<?php esc_attr_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?>"
						        x-show="entry.rule.status !== 'deleted'"
						        @click="deleteRule(entry.rule)">
							<span class="dashicons dashicons-trash"></span>
						</button>

						<!-- Restore (shown for deleted rows) -->
						<button type="button"
						        class="prbp-restore-btn button button-small"
						        title="<?php esc_attr_e( 'Restore', 'priceblueprint-for-woocommerce' ); ?>"
						        x-show="entry.rule.status === 'deleted'"
						        style="display:none;"
						        @click="restoreRule(entry.rule)">
							<span class="dashicons dashicons-undo"></span>
						</button>

					</td>

				</tr>
			</template>

		</tbody>
	</table>

	<!-- ── Add rule ──────────────────────────────────────────────────────── -->
	<p>
		<button type="button" class="button button-secondary" @click="addRule()">
			<?php esc_html_e( '+ Add Rule', 'priceblueprint-for-woocommerce' ); ?>
		</button>
	</p>

	<!-- JSON payload — written by onSubmit immediately before the form POSTs -->
	<input type="hidden" name="prbp_rules_json" id="prbp-rules-json" value="">

</div><!-- /.prbp-admin-wrap -->
