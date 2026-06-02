/**
 * PriceBlueprint Admin — Product edit screen
 *
 * Teaches WooCommerce's show/hide engine about the prbp_configurable_product type
 * so General and Inventory tabs display correctly.
 *
 * @package PriceBlueprint
 */

/* global prbpAdminProduct */

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
			// WooCommerce's own handler already ran and restored the correct state.
			// Only clean up our own UI artifacts.
			$( '#_regular_price' ).removeClass( 'prbp-field--error' );
			$( '#prbp-price-error' ).remove();
			$( '#prbp_template_id' ).removeClass( 'prbp-field--error' );
			$( '#prbp-template-error' ).remove();
			return;
		}

		// ── prbp_configurable_product ─────────────────────────────────────────────
		// WooCommerce hid these because it ran show_and_hide_panels() before
		// our content was visible. Show the tab <li> elements after showing
		// their content so WooCommerce's tab-click handler can reach the panels.

		// General tab.
		$( '.general_tab' ).show();
		$( '#general_product_data .options_group.pricing' ).show();
		$( '#general_product_data ._regular_price_field,' +
		   '#general_product_data ._sale_price_field' ).show();
		$( '.sale_price_dates_fields' ).show();
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

			/* ----------------------------------------------------------------
			 * Run once after WooCommerce's own DOM-ready handler has fired,
			 * then again on every product-type change.
			 * ---------------------------------------------------------------- */

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
				}, 50 );
			} );

			/* ----------------------------------------------------------------
			 * Regular price + template validation on submit
			 * ---------------------------------------------------------------- */

			$( '#post' ).on( 'submit', function () {
				if ( $( '#product-type' ).val() !== 'prbp_configurable_product' ) {
					return;
				}

				// Price indicator — visual only, PHP handles server-side validation.
				if ( $( '#_regular_price' ).val() === '' ) {
					$( '#_regular_price' ).addClass( 'prbp-field--error' );
					if ( ! $( '#prbp-price-error' ).length ) {
						$( '<span id="prbp-price-error" class="prbp-price-error">' + prbpAdminProduct.i18n.price_required + '</span>' )
							.insertAfter( '#_regular_price' );
					}
				} else {
					$( '#_regular_price' ).removeClass( 'prbp-field--error' );
					$( '#prbp-price-error' ).remove();
				}

				// Template indicator — visual only, product saves fine without one.
				if ( ! $( '#prbp_template_id' ).val() ) {
					$( '#prbp_template_id' ).addClass( 'prbp-field--error' );
					if ( ! $( '#prbp-template-error' ).length ) {
						$( '<span id="prbp-template-error" class="prbp-price-error">' + prbpAdminProduct.i18n.template_required + '</span>' )
							.insertAfter( '#prbp_template_id' );
					}
				} else {
					$( '#prbp_template_id' ).removeClass( 'prbp-field--error' );
					$( '#prbp-template-error' ).remove();
				}
			} );

			// Clear price error as soon as the user types a value.
			$( '#_regular_price' ).on( 'input', function () {
				if ( $( this ).val() !== '' ) {
					$( this ).removeClass( 'prbp-field--error' );
					$( '#prbp-price-error' ).remove();
				}
			} );

			// Clear template error as soon as the user picks a template.
			$( '#prbp_template_id' ).on( 'change', function () {
				if ( $( this ).val() ) {
					$( this ).removeClass( 'prbp-field--error' );
					$( '#prbp-template-error' ).remove();
				}
			} );
		} );
	}
}

PrbpAdminProduct.init();
