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

if ( empty( $attribute_taxonomies ) ) : ?>
<div class="prbp-admin-wrap">
	<div class="prbp-quick-setup">
		<div class="prbp-qs-panels">
			<div class="prbp-qs-panel">
				<h3 class="prbp-qs-panel-title">
					<?php esc_html_e( 'No WooCommerce attributes found', 'priceblueprint-for-woocommerce' ); ?>
				</h3>
				<p class="prbp-qs-panel-desc">
					<?php esc_html_e( 'Price Blueprints require WooCommerce global attributes. Create at least one attribute and its values before building rules.', 'priceblueprint-for-woocommerce' ); ?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ); ?>"
				   class="prbp-qs-btn">
					<?php esc_html_e( 'Create attributes in WooCommerce →', 'priceblueprint-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
<?php return; endif;

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
var prbpCurrencySymbol = <?php echo wp_json_encode( html_entity_decode( get_woocommerce_currency_symbol() ) ); ?>;
</script>

<div class="prbp-admin-wrap"
     x-data="rulesRepeater(prbpRulesData, prbpAttrsData)"
     x-cloak>

	<!-- ── Quick Setup (visible only when no sections exist) ────────────── -->
	<div class="prbp-quick-setup"
	     x-show="sections.length === 0"
	     style="display:none;">

		<div class="prbp-qs-panels">

			<!-- Generate from a product -->
			<div class="prbp-qs-panel">
				<div class="prbp-qs-chip">&#9889; <?php esc_html_e( 'Quick Setup', 'priceblueprint-for-woocommerce' ); ?></div>
				<h3 class="prbp-qs-panel-title">
					<?php esc_html_e( 'Generate rules from a product', 'priceblueprint-for-woocommerce' ); ?>
				</h3>
				<p class="prbp-qs-panel-desc">
					<?php esc_html_e( 'Pick a product and its WooCommerce attributes will pre-fill this blueprint. Prices start at 0 — set them as needed. The product stays exactly as it is.', 'priceblueprint-for-woocommerce' ); ?>
				</p>

				<div class="prbp-qs-controls">
					<select class="prbp-qs-product-select"
					        x-init="initProductSelect($el)">
					</select>
					<button type="button"
					        class="prbp-qs-btn"
					        :disabled="!quickSetupProductId || quickSetupLoading"
					        @click="importFromProduct()">
						<span x-show="quickSetupLoading">&#8230;</span>
						<span x-show="!quickSetupLoading">&#9889; <?php esc_html_e( 'Generate', 'priceblueprint-for-woocommerce' ); ?></span>
					</button>
				</div>

				<!-- No-attributes notice -->
				<p class="prbp-qs-notice"
				   x-show="quickSetupError === 'no_attrs'"
				   style="display:none;">
					<?php esc_html_e( 'This product has no WooCommerce attributes.', 'priceblueprint-for-woocommerce' ); ?>
					<a :href="productEditUrl(quickSetupProductId)"
					   target="_blank" rel="noopener">
						<?php esc_html_e( 'Add them in WooCommerce →', 'priceblueprint-for-woocommerce' ); ?>
					</a>
				</p>

				<!-- Fetch-error notice -->
				<p class="prbp-qs-notice prbp-qs-notice--error"
				   x-show="quickSetupError === 'fetch_error'"
				   style="display:none;">
					<?php esc_html_e( 'Could not load attributes. Please try again.', 'priceblueprint-for-woocommerce' ); ?>
				</p>
			</div>

		</div><!-- /.prbp-qs-panels -->

	</div><!-- /.prbp-quick-setup -->

	<!-- ── Error banner ───────────────────────────────────────────────────── -->
	<div class="prbp-error-banner"
	     x-show="errorMsg"
	     x-text="errorMsg"
	     style="display:none;"></div>

	<!-- ── Filter bar ────────────────────────────────────────────────────── -->
	<div class="prbp-filter-bar"
	     x-show="activeSectionsCount > 0"
	     style="display:none;">
		<input type="text"
		       x-model.debounce.200ms="query"
		       placeholder="<?php esc_attr_e( 'Filter by attribute or term…', 'priceblueprint-for-woocommerce' ); ?>"
		       autocomplete="off">
		<span class="prbp-rules-count" x-text="countLabel"></span>
	</div>

	<!-- ── Sections ──────────────────────────────────────────────────────── -->
	<div class="prbp-sections" x-show="sections.length > 0" style="display:none;">

		<template x-for="entry in displaySections" :key="entry.section._uid">
			<div class="prbp-section" x-show="entry.sectionInDom">

				<div class="prbp-section-header">
					<button type="button"
					        class="prbp-section-toggle"
					        @click="toggleSection(entry.section)">
						<span class="prbp-section-chevron" :class="{ 'prbp-section-chevron--open': entry.expanded }" aria-hidden="true"></span>
						<h4 class="prbp-section-title" x-text="entry.section.attribute_label || entry.section.attribute"></h4>
						<span class="prbp-section-summary" x-text="sectionSummary(entry.section)"></span>
					</button>

					<div class="prbp-section-actions">
						<button type="button"
						        class="prbp-reset-btn prbp-btn-labeled button button-small"
						        title="<?php esc_attr_e( 'Reset', 'priceblueprint-for-woocommerce' ); ?>"
						        @click="resetSection(entry.section)">
							<span class="dashicons dashicons-update"></span>
							<span class="prbp-btn-label"><?php esc_html_e( 'Reset', 'priceblueprint-for-woocommerce' ); ?></span>
						</button>

						<button type="button"
						        class="prbp-delete-btn prbp-btn-labeled button button-small"
						        title="<?php esc_attr_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?>"
						        @click="deleteSection(entry.section)">
							<span class="dashicons dashicons-trash"></span>
							<span class="prbp-btn-label"><?php esc_html_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?></span>
						</button>
					</div>
				</div>

				<table class="prbp-rules-table prbp-section-table widefat" x-show="entry.expanded">
					<tbody>

						<template x-for="rowEntry in entry.rows" :key="rowEntry.row._uid">
							<tr x-show="rowEntry.inDom">

								<td class="prbp-col-index">
									<span x-text="rowEntry.pos || ''"></span>
								</td>

								<td class="prbp-col-value">
									<select multiple
									        class="prbp-value-select"
									        x-init="initValueSelect($el, rowEntry.row)">
									</select>
								</td>

								<td class="prbp-col-price">
									<span class="prbp-price-wrap">
										<input type="number"
										       class="prbp-price-input"
										       x-model="rowEntry.row.price"
										       step="0.01"
										       min="0"
										       placeholder="0.00">
										<span class="prbp-price-currency" aria-hidden="true"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									</span>
								</td>

								<td class="prbp-col-actions">
									<div class="prbp-row-actions">
										<button type="button"
										        class="prbp-reset-btn button button-small"
										        title="<?php esc_attr_e( 'Reset', 'priceblueprint-for-woocommerce' ); ?>"
										        @click="resetRow(rowEntry.row)">
											<span class="dashicons dashicons-update"></span>
										</button>

										<button type="button"
										        class="prbp-delete-btn button button-small"
										        title="<?php esc_attr_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?>"
										        @click="deleteRow(entry.section, rowEntry.row)">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</div>
								</td>

							</tr>
						</template>

						<tr class="prbp-empty-row"
						    x-show="!entry.rows.some((rowEntry) => rowEntry.inDom)">
							<td colspan="4">
								<?php esc_html_e( 'No active terms in this section.', 'priceblueprint-for-woocommerce' ); ?>
							</td>
						</tr>

					</tbody>
				</table>

				<p class="prbp-add-row" x-show="entry.expanded">
					<button type="button" class="button button-secondary button-small" @click="addRow(entry.section)">
						<?php esc_html_e( '+ Add term', 'priceblueprint-for-woocommerce' ); ?>
					</button>
				</p>

			</div>
		</template>

	</div><!-- /.prbp-sections -->

	<p class="prbp-no-results"
	   x-show="activeSectionsCount > 0 && ! hasFilterResults"
	   style="display:none;">
		<?php esc_html_e( 'No attributes or values match your filter.', 'priceblueprint-for-woocommerce' ); ?>
	</p>

	<!-- ── Add section ───────────────────────────────────────────────────── -->
	<p class="prbp-add-section" x-show="availableAttributes.length > 0">
		<label for="prbp-add-section-select" class="screen-reader-text">
			<?php esc_html_e( 'Add attribute section', 'priceblueprint-for-woocommerce' ); ?>
		</label>
		<select id="prbp-add-section-select" @change="addSection($event)">
			<option value=""><?php esc_html_e( '+ Add attribute section', 'priceblueprint-for-woocommerce' ); ?></option>
			<template x-for="attr in availableAttributes" :key="attr.slug">
				<option :value="attr.slug" x-text="attr.label"></option>
			</template>
		</select>
	</p>

	<!-- JSON payload — written by onSubmit immediately before the form POSTs -->
	<input type="hidden" name="prbp_rules_json" id="prbp-rules-json" value="">

</div><!-- /.prbp-admin-wrap -->
