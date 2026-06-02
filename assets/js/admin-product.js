/**
 * PriceBlueprint Admin — Product edit screen
 *
 * Teaches WooCommerce's show/hide engine about the prbp_configurable_product type
 * so General and Inventory tabs display correctly.
 *
 * @package PriceBlueprint
 */

'use strict';

class PrbpAdminProduct {

	// -------------------------------------------------------------------------
	// Visibility
	// -------------------------------------------------------------------------

	/**
	 * Apply visibility rules for the current product type.
	 *
	 * For prbp_configurable_product: force-show tabs and fields that WooCommerce hid
	 * because it does not know this custom type.
	 *
	 * For every other type: replay WooCommerce's standard show/hide logic so
	 * anything our configurable branch showed is correctly restored.
	 *
	 * @param {jQuery} $
	 */
	static applyVisibility( $ ) {
		const type = $( '#product-type' ).val();

		if ( type !== 'prbp_configurable_product' ) {
			$( '#_regular_price' ).prop( 'required', false );
			$( '#prbp_template_id' ).prop( 'required', false );
			return;
		}

		// ── prbp_configurable_product ─────────────────────────────────────────────
		// WooCommerce hid these because it ran show_and_hide_panels() before
		// our content was visible. Show the tab <li> elements after showing
		// their content so WooCommerce's tab-click handler can reach the panels.

		$( '#_regular_price' ).prop( 'required', true );
		$( '#prbp_template_id' ).prop( 'required', true );

		// General tab.
		$( '.general_tab' ).show();
		$( '#general_product_data .options_group.pricing' ).show();
		$( '#general_product_data ._regular_price_field' ).show();
		$( '#general_product_data ._sale_price_field' ).hide();
		$( '.sale_price_dates_fields' ).hide();
		$( '#general_product_data ._tax_status_field,' +
		   '#general_product_data ._tax_class_field' ).show();

		// Inventory tab.
		$( '.inventory_tab' ).show();
		$( '#inventory_product_data .options_group' ).show();
		$( '.stock_fields' ).show();
		$( '.inventory_sold_individually' ).show();
		$( '#inventory_product_data ._manage_stock_field,' +
		   '#inventory_product_data ._stock_status_field,' +
		   '#inventory_product_data .stock_status_field,' +
		   '#inventory_product_data ._sold_individually_field,' +
		   '#inventory_product_data ._sku_field' ).show();
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	static init() {
		jQuery( function ( $ ) {

			// Switch to the tab containing the first invalid required field before
			// the browser runs constraint validation, so its popup lands on a visible element.
			$( document ).on( 'click.prbp-tab', '#publish, #save-post', function () {
				if ( $( '#product-type' ).val() !== 'prbp_configurable_product' ) {
					return;
				}

				const priceEl    = document.getElementById( '_regular_price' );
				const templateEl = document.getElementById( 'prbp_template_id' );

				if ( priceEl && ! priceEl.checkValidity() ) {
					$( '.general_tab a' ).trigger( 'click' );
				} else if ( templateEl && ! templateEl.checkValidity() ) {
					$( 'a[href="#priceblueprint_product_data"]' ).trigger( 'click' );
				}
			} );

			// On page load, only force-apply for our custom type.
			// For all other types the server-rendered state is already correct.
			if ( $( '#product-type' ).val() === 'prbp_configurable_product' ) {
				setTimeout( function () {
					$( '#product-type' ).trigger( 'change' );
					setTimeout( function () {
						PrbpAdminProduct.applyVisibility( $ );
					}, 50 );
				}, 0 );
			}

			$( '#product-type' ).on( 'change', function () {
				setTimeout( function () {
					PrbpAdminProduct.applyVisibility( $ );
					$( '.general_tab a' ).trigger( 'click' );
				}, 50 );
			} );
		} );
	}
}

PrbpAdminProduct.init();
