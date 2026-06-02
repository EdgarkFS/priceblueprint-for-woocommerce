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

	/**
	 * Search published products by title. Requires at least 2 characters.
	 *
	 * @param  {string} term
	 * @return {Promise<Array<{id:number, title:string}>>}
	 */
	searchProducts( term ) {
		return fetch( prbpAdmin.ajax_url, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    new URLSearchParams( {
				action: 'prbp_search_products',
				nonce:  prbpAdmin.nonce,
				term,
			} ).toString(),
		} )
			.then( r => r.json() )
			.then( d => d.success ? d.data : [] )
			.catch( () => [] );
	}

	/**
	 * Fetch global (pa_*) attributes with pre-assigned term values for a product.
	 * Returns null on network/server error; empty array if product has no global attrs.
	 *
	 * @param  {number|string} productId
	 * @return {Promise<Array<{slug:string,label:string,value_ids:string[],value_slugs:string[],value_labels:string[]}>|null>}
	 */
	getProductAttributes( productId ) {
		return fetch( prbpAdmin.ajax_url, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    new URLSearchParams( {
				action:     'prbp_get_product_attributes',
				nonce:      prbpAdmin.nonce,
				product_id: productId,
			} ).toString(),
		} )
			.then( r => r.json() )
			.then( d => d.success ? d.data : null )
			.catch( () => null );
	}
}
