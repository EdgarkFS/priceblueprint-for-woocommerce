/**
 * PriceBlueprint Frontend JS
 *
 * Handles dynamic price updates on the single product configurator page.
 * Vanilla JS only — no jQuery.
 *
 * @package PriceBlueprint
 */

/* global prbpFrontend */

'use strict';

class FpFrontend {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Simple sprintf-style placeholder replacement.
	 *
	 * @param {string} str
	 * @param {...string} args
	 * @returns {string}
	 */
	static sprintf( str ) {
		const args = Array.prototype.slice.call( arguments, 1 );
		let i      = 0;
		return str.replace( /%(?:\d+\$)?s/g, function () {
			return args[ i++ ] !== undefined ? args[ i - 1 ] : '';
		} );
	}

	// -------------------------------------------------------------------------
	// Collect selections
	// -------------------------------------------------------------------------

	static collectSelections( configurator ) {
		const selections = {};
		let allSelected  = true;

		configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( sel ) {
			const attr = sel.dataset.attribute;
			if ( sel.value ) {
				selections[ attr ] = sel.value;
			} else {
				allSelected = false;
			}
		} );

		return { selections: selections, allSelected: allSelected };
	}

	// -------------------------------------------------------------------------
	// Update total price display
	// -------------------------------------------------------------------------

	static updatePrice( configurator ) {
		const priceEl   = configurator.querySelector( '.prbp-total-price' );
		const minPrice  = parseFloat( priceEl ? priceEl.dataset.minPrice : 0 ) || 0;
		const collected = FpFrontend.collectSelections( configurator );
		const qtyEl     = configurator.querySelector( 'input.qty' );
		const qty       = parseInt( qtyEl ? qtyEl.value : 1, 10 ) || 1;

		if ( ! collected.allSelected ) {
			if ( priceEl ) priceEl.innerHTML = priceEl.dataset.minPriceHtml;
			return;
		}

		if ( priceEl ) priceEl.innerHTML = '<span class="prbp-loader" aria-label="' + prbpFrontend.i18n.loading + '"><span></span><span></span><span></span></span>';

		const body = new URLSearchParams( {
			action:     'prbp_calculate_price',
			nonce:      prbpFrontend.nonce,
			product_id: prbpFrontend.product_id,
		} );

		Object.keys( collected.selections ).forEach( function ( attr ) {
			body.append( 'selections[' + attr + ']', collected.selections[ attr ] );
		} );

		body.append( 'quantity', qty );

		fetch( prbpFrontend.ajax_url, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				if ( ! data.success || ! priceEl ) return;
				priceEl.innerHTML = data.data.formatted;
			} )
			.catch( function () {
				// Silently fail — user can still add to cart.
			} );
	}

	// -------------------------------------------------------------------------
	// Bind selects
	// -------------------------------------------------------------------------

	static bindSelects( configurator ) {
		configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				FpFrontend.updatePrice( configurator );
			} );
		} );

		const qtyInput = configurator.querySelector( 'input.qty' );
		if ( qtyInput ) {
			qtyInput.addEventListener( 'change', function () {
				FpFrontend.updatePrice( configurator );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Form submit validation
	// -------------------------------------------------------------------------

	static bindFormSubmit( configurator ) {
		const form = configurator.querySelector( 'form.cart' );
		if ( ! form ) return;

		form.addEventListener( 'submit', function ( event ) {
			let valid = true;

			configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( sel ) {
				const errorEl = sel.parentElement.querySelector( '.prbp-field-error' );
				if ( ! sel.value ) {
					valid = false;
					if ( errorEl ) {
						errorEl.textContent = prbpFrontend.i18n.select_all;
					}
					sel.classList.add( 'prbp-select--error' );
				} else {
					if ( errorEl ) errorEl.textContent = '';
					sel.classList.remove( 'prbp-select--error' );
				}
			} );

			if ( ! valid ) {
				event.preventDefault();
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	static init() {
		document.addEventListener( 'DOMContentLoaded', function () {
			document.querySelectorAll( '.prbp-configurator' ).forEach( function ( configurator ) {
				FpFrontend.bindSelects( configurator );
				FpFrontend.bindFormSubmit( configurator );
			} );
		} );
	}
}

FpFrontend.init();
