/**
 * Writes attribute selections to the URL as GET parameters.
 *
 * Strips the pa_ prefix from attribute keys so internal WooCommerce
 * taxonomy names (pa_size) become clean URL params (size).
 *
 * Uses replaceState so no browser history entry is created —
 * the back button goes to the previous page, not through each selection.
 *
 * @param {Object.<string,string>} selections  e.g. { pa_size: 'xl', pa_color: 'red' }
 */
export function syncToUrl( selections ) {
	const params = new URLSearchParams();

	Object.keys( selections ).forEach( function ( attribute ) {
		const paramKey = attribute.replace( /^pa_/, '' );
		params.set( paramKey, selections[ attribute ] );
	} );

	history.replaceState( null, '', '?' + params.toString() );
}
