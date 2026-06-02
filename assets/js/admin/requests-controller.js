/**
 * RequestsController — all outbound HTTP for the admin repeater.
 *
 * Single responsibility: fetch WC attribute terms from the server and cache
 * the result Promise so repeated calls for the same attribute are free.
 *
 * @module requests-controller
 * @package PriceBlueprint
 */

/* global prbpAdmin */

export class RequestsController {

	constructor() {
		/** @type {Map<string, Promise<Array<{id:number, slug:string, name:string}>>>} */
		this._cache = new Map();
	}

	/**
	 * Return a cached Promise resolving to the term list for the attribute.
	 * N rows sharing the same attribute produce exactly one network request.
	 *
	 * @param  {string} attribute  Taxonomy slug, e.g. "pa_color".
	 * @return {Promise<Array<{id:number, slug:string, name:string}>>}
	 */
	loadTerms( attribute ) {
		if ( ! attribute ) {
			return Promise.resolve( [] );
		}

		if ( ! this._cache.has( attribute ) ) {
			this._cache.set(
				attribute,
				fetch( prbpAdmin.ajax_url, {
					method:  'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body:    new URLSearchParams( {
						action:    'prbp_get_terms',
						nonce:     prbpAdmin.nonce,
						attribute,
					} ).toString(),
				} )
					.then( r => r.json() )
					.then( d => d.success ? d.data : [] )
					.catch( () => [] )
			);
		}

		return this._cache.get( attribute );
	}
}
