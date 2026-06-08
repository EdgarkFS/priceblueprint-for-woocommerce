/**
 * Reads every .prbp-attribute-select inside the configurator wrapper.
 *
 * @param {HTMLElement} configurator
 * @returns {{ selections: Object.<string,string>, defaults: Object.<string,string>, allSelected: boolean }}
 */
export function collectSelections( configurator ) {
	const selections = {};
	const defaults   = {};
	let allSelected  = true;

	configurator.querySelectorAll( '.prbp-attribute-select' ).forEach( function ( select ) {
		const attribute = select.dataset.attribute;
		defaults[ attribute ] = select.dataset.defaultSlug;

		if ( select.value ) {
			selections[ attribute ] = select.value;
		} else {
			allSelected = false;
		}
	} );

	return { selections, defaults, allSelected };
}
