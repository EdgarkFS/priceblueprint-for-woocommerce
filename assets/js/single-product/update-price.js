/* global prbpFrontend */

import { collectSelections } from './collect-selections.js';

/**
 * Fires an AJAX request to recalculate the total price
 * and updates the .prbp-total-price element.
 *
 * @param {HTMLElement} configurator
 */
export function updatePrice( configurator ) {
	const priceElement          = configurator.querySelector( '.prbp-total-price' );
	const { selections, allSelected } = collectSelections( configurator );
	const quantityInput         = configurator.querySelector( 'input.qty' );
	const quantity              = parseInt( quantityInput ? quantityInput.value : 1, 10 ) || 1;

	if ( ! allSelected ) {
		if ( priceElement ) {
			priceElement.innerHTML = priceElement.dataset.minPriceHtml;
		}
		return;
	}

	if ( priceElement ) {
		priceElement.innerHTML =
			'<span class="prbp-loader" aria-label="' + prbpFrontend.i18n.loading + '">' +
			'<span></span><span></span><span></span></span>';
	}

	const body = new URLSearchParams( {
		action:     'prbp_calculate_price',
		nonce:      prbpFrontend.nonce,
		product_id: prbpFrontend.product_id,
	} );

	Object.keys( selections ).forEach( function ( attribute ) {
		body.append( 'selections[' + attribute + ']', selections[ attribute ] );
	} );

	body.append( 'quantity', quantity );

	fetch( prbpFrontend.ajax_url, {
		method:  'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body:    body.toString(),
	} )
		.then( function ( response ) { return response.json(); } )
		.then( function ( data ) {
			if ( ! data.success || ! priceElement ) { return; }
			priceElement.innerHTML = data.data.formatted;
		} )
		.catch( function () {
			// Silently fail — user can still add to cart.
		} );
}
