/* global prbpFrontend */

/**
 * Binds add-to-cart form submit validation.
 * Shows inline errors for any attribute select left empty.
 *
 * @param {HTMLElement} configurator
 */
export function bindFormValidation( configurator ) {
	const form = configurator.querySelector( 'form.cart' );
	if ( ! form ) { return; }

	form.addEventListener( 'submit', function ( event ) {
		let valid = true;

		configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( select ) {
			const wrapper = select.closest( '.prbp-select-wrapper' );
			const group   = select.closest( '.prbp-attribute-group' );
			const errorEl = group ? group.querySelector( '.prbp-field-error' ) : null;

			if ( ! select.value ) {
				valid = false;
				if ( errorEl ) { errorEl.textContent = prbpFrontend.i18n.select_all; }
				if ( wrapper ) { wrapper.classList.add( 'prbp-select--error' ); }
			} else {
				if ( errorEl ) { errorEl.textContent = ''; }
				if ( wrapper ) { wrapper.classList.remove( 'prbp-select--error' ); }
			}
		} );

		if ( ! valid ) {
			event.preventDefault();
		}
	} );
}
