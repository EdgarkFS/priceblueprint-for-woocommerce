/**
 * PriceBlueprint — single-product page coordinator.
 *
 * ES module: imports focused modules from ./single-product/ and wires
 * event listeners on every .prbp-configurator element.
 *
 * Modules are deferred by the browser, so the DOM is fully parsed
 * before this code runs — no DOMContentLoaded wrapper needed.
 *
 * @package PriceBlueprint
 */

/* global prbpFrontend */

import { collectSelections }  from './single-product/collect-selections.js';
import { updatePrice }        from './single-product/update-price.js';
import { syncToUrl }          from './single-product/url-sync.js';
import { bindFormValidation } from './single-product/validate-form.js';

document.querySelectorAll( '.prbp-configurator' ).forEach( function ( configurator ) {

	configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			const { selections } = collectSelections( configurator );
			updatePrice( configurator );
			syncToUrl( selections );
		} );
	} );

	const quantityInput = configurator.querySelector( 'input.qty' );
	if ( quantityInput ) {
		quantityInput.addEventListener( 'change', function () {
			updatePrice( configurator );
		} );
	}

	bindFormValidation( configurator );
} );
