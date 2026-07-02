/* global jQuery, prbpFrontend */

function findMatchingSlide( configurator ) {
	let slideIndex = null;
	configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( select ) {
		if ( slideIndex !== null ) return;
		const option = select.options[ select.selectedIndex ];
		if ( option && option.dataset.prbpSlide !== undefined ) {
			slideIndex = parseInt( option.dataset.prbpSlide, 10 );
		}
	} );
	return slideIndex;
}

function goToSlide( slideIndex ) {
	if ( ! window.jQuery ) return;
	const gallery = document.querySelector( '.woocommerce-product-gallery' );
	if ( ! gallery || ! gallery.querySelector( '.flex-viewport' ) ) return;
	window.jQuery( gallery ).flexslider( slideIndex );
}

export function updateImage( configurator ) {
	const slideIndex = findMatchingSlide( configurator );
	if ( slideIndex !== null ) {
		goToSlide( slideIndex );
		return;
	}
	if ( window.prbpFrontend?.has_base_slide ) {
		goToSlide( 0 );
	}
}

export function initImageSync() {
	if ( ! window.jQuery ) return;
	window.jQuery( document ).on( 'wc-product-gallery-after-init', function () {
		document.querySelectorAll( '.prbp-configurator' ).forEach( function ( configurator ) {
			updateImage( configurator );
		} );
	} );
}
