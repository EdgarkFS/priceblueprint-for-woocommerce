/**
 * DomController — DOM / UI concerns for the pricing-rules repeater.
 *
 * Responsibilities:
 *   - Section and row object factories.
 *   - Tom Select instance lifecycle: create, populate, destroy.
 *   - Alpine component registration.
 *
 * Depends on RequestsController for term fetching; never calls fetch() itself.
 *
 * @module dom-controller
 * @package PriceBlueprint
 */

/* global prbpAdmin, prbpCurrencySymbol, Alpine, TomSelect */

// ---------------------------------------------------------------------------
// Module-level utility
// ---------------------------------------------------------------------------

/**
 * Minimal sprintf — supports %s and positional %1$s … %9$s placeholders.
 *
 * @param  {string}    str
 * @param  {...string} args
 * @return {string}
 */
function sprintf( str, ...args ) {
	let i = 0;
	return str.replace( /%(?:(\d+)\$)?[sd]/g, ( _m, pos ) => {
		const idx = pos ? parseInt( pos, 10 ) - 1 : i++;
		return args[ idx ] !== undefined ? String( args[ idx ] ) : '';
	} );
}

/**
 * Build the "N value(s) · price" summary shown in a section's collapsed header.
 * Assumes section.rows has at least one row (guaranteed by makeSection()).
 *
 * @param  {Object} section
 * @param  {string} currencySymbol
 * @param  {string} valueCountFormat  i18n string with one %d placeholder.
 * @return {string}
 */
function formatSectionSummary( section, currencySymbol, valueCountFormat ) {
	const count  = section.rows.length;
	const prices = section.rows.map( row => parseFloat( row.price ) || 0 );
	const min    = Math.min( ...prices );
	const max    = Math.max( ...prices );

	const priceText = min === max
		? `${ currencySymbol }${ min.toFixed( 2 ) }`
		: `${ currencySymbol }${ min.toFixed( 2 ) }–${ currencySymbol }${ max.toFixed( 2 ) }`;

	return `${ sprintf( valueCountFormat, count ) } · ${ priceText }`;
}

// ---------------------------------------------------------------------------
// Class
// ---------------------------------------------------------------------------

export class DomController {

	/**
	 * @param {import('./requests-controller.js').RequestsController} requests
	 */
	constructor( requests ) {
		this._requests     = requests;
		/** @type {Map<number, TomSelect>} row._uid → instance */
		this._tomSelectMap = new Map();
		this._uid          = 0;
		this._productTs    = null;
	}

	// ── Object factories ──────────────────────────────────────────────────────

	/**
	 * Create a row object merged with sane defaults.
	 *
	 * _uid is a stable internal key used by x-for (:key) and _tomSelectMap.
	 * It is never serialised to the JSON payload sent to PHP.
	 *
	 * @param  {Object} section
	 * @param  {Object} [data]
	 * @return {Object}
	 */
	makeRow( section, data = {} ) {
		return Object.assign(
			{
				_uid:            ++this._uid,
				attribute:       section.attribute || '',
				attribute_label: section.attribute_label || '',
				value_ids:       [],
				value_slugs:     [],
				value_labels:    [],
				price:           '0',
			},
			data,
			{
				attribute:       section.attribute || '',
				attribute_label: section.attribute_label || '',
			}
		);
	}

	/**
	 * Create an attribute section with at least one row.
	 *
	 * @param  {Object} [data]
	 * @return {Object}
	 */
	makeSection( data = {} ) {
		const section = Object.assign(
			{
				_uid:            ++this._uid,
				attribute:       '',
				attribute_label: '',
				rows:            [],
				expanded:        false,
			},
			data
		);

		section.rows = Array.isArray( section.rows )
			? section.rows.map( row => this.makeRow( section, {
				value_ids:    Array.isArray( row.value_ids )    ? row.value_ids.map( String )    : [],
				value_slugs:  Array.isArray( row.value_slugs )  ? row.value_slugs.map( String )  : [],
				value_labels: Array.isArray( row.value_labels ) ? row.value_labels.map( String ) : [],
				price:        row.price || '0',
			} ) )
			: [];

		if ( section.rows.length === 0 ) {
			section.rows.push( this.makeRow( section ) );
		}

		return section;
	}

	// ── Tom Select lifecycle ──────────────────────────────────────────────────

	/**
	 * Create a Tom Select instance on a <select multiple> element.
	 * Called once per row from x-init in the Alpine template.
	 *
	 * @param {HTMLSelectElement} el  The target <select> element.
	 * @param {Object}            row Alpine-reactive row object.
	 */
	initValueSelect( el, row ) {
		const savedIds = row.value_ids.slice();

		let ts;
		ts = new TomSelect( el, {
			plugins:        [ 'remove_button' ],
			valueField:     'id',
			labelField:     'name',
			searchField:    [ 'name' ],
			options:        [],
			items:          [],
			placeholder:    prbpAdmin.i18n.select_term,
			create:         false,
			dropdownParent: 'body',

			shouldLoad: ( query ) => {
				const total = Object.keys( ts.options ).length;
				if ( total > 0 && ts.items.length >= total ) return true;
				return query.length > 0;
			},

			render: {
				no_results: () => {
					const total = Object.keys( ts.options ).length;
					if ( total > 0 && ts.items.length >= total ) {
						return `<div class="no-results">${ prbpAdmin.i18n.all_terms_selected }</div>`;
					}
					return `<div class="no-results">${ prbpAdmin.i18n.no_results }</div>`;
				},
			},

			onItemAdd: () => {
				ts.settings.placeholder      = '';
				ts.control_input.placeholder = '';
			},

			onItemRemove: () => {
				if ( ts.items.length === 0 ) {
					ts.settings.placeholder      = prbpAdmin.i18n.select_term;
					ts.control_input.placeholder = prbpAdmin.i18n.select_term;
				}
			},

			onChange: ( values ) => {
				if ( ! Array.isArray( values ) ) {
					values = values ? [ values ] : [];
				}
				row.value_ids    = values.slice();
				row.value_slugs  = values.map( id => ts.options[ id ]?.slug ?? '' );
				row.value_labels = values.map( id => ts.options[ id ]?.name ?? '' );
			},
		} );

		this._tomSelectMap.set( row._uid, ts );

		if ( row.attribute ) {
			ts.disable();
			this._requests.loadTerms( row.attribute ).then( terms => {
				if ( terms.length === 0 ) {
					this._showNoValuesMsg( ts, row.attribute );
					return;
				}

				terms.forEach( t => {
					ts.addOption( { id: String( t.id ), name: t.name, slug: t.slug } );
				} );

				savedIds.forEach( id => {
					if ( ts.options[ id ] ) { ts.addItem( id, true ); }
				} );

				const validIds    = savedIds.filter( id => !! ts.options[ id ] );
				row.value_ids     = validIds;
				row.value_slugs   = validIds.map( id => ts.options[ id ]?.slug ?? '' );
				row.value_labels  = validIds.map( id => ts.options[ id ]?.name ?? '' );

				ts.enable();
			} );
		} else {
			ts.disable();
		}
	}

	/**
	 * Remove any existing no-values message and unhide the Tom Select wrapper.
	 *
	 * @param {TomSelect} ts
	 */
	_clearNoValuesMsg( ts ) {
		ts.wrapper.style.display = '';
		const next = ts.wrapper.nextElementSibling;
		if ( next && next.classList.contains( 'prbp-no-values-msg' ) ) {
			next.remove();
		}
	}

	/**
	 * Hide the Tom Select wrapper and insert a sibling no-values message.
	 *
	 * @param {TomSelect} ts
	 * @param {string}    attribute Taxonomy slug, e.g. "pa_color".
	 */
	_showNoValuesMsg( ts, attribute ) {
		ts.wrapper.style.display = 'none';
		const span     = document.createElement( 'span' );
		span.className = 'prbp-no-values-msg';
		span.appendChild( document.createTextNode( prbpAdmin.i18n.no_terms_msg + ' ' ) );
		const link       = document.createElement( 'a' );
		link.href        = prbpAdmin.wc_terms_url + attribute + '&post_type=product';
		link.target      = '_blank';
		link.rel         = 'noopener';
		link.textContent = prbpAdmin.i18n.no_terms_link;
		span.appendChild( link );
		ts.wrapper.insertAdjacentElement( 'afterend', span );
	}

	/**
	 * Create a single-select Tom Select for the Quick Setup product search.
	 *
	 * @param {HTMLSelectElement} el
	 * @param {Object}            component Alpine component instance.
	 */
	initProductSelect( el, component ) {
		const requests = this._requests;
		const ts = new TomSelect( el, {
			valueField:     'id',
			labelField:     'title',
			searchField:    [ 'title' ],
			maxItems:       1,
			options:        [],
			items:          [],
			placeholder:    prbpAdmin.i18n.qs_search_prompt,
			create:         false,
			sortField:      { field: 'text', direction: 'asc' },
			preload:        'focus',
			dropdownParent: 'body',
			load( query, callback ) {
				requests.searchProducts( query ).then( callback );
			},
			onChange( value ) {
				component.quickSetupProductId = value || null;
				component.quickSetupError     = '';
			},
		} );
		this._productTs = ts;
	}

	/**
	 * Return the Tom Select instance for a row, or null.
	 *
	 * @param  {number} uid row._uid
	 * @return {TomSelect|null}
	 */
	getTomSelect( uid ) {
		return this._tomSelectMap.get( uid ) ?? null;
	}

	/**
	 * Clear the visual Tom Select selection for a row without removing options.
	 *
	 * @param {Object} row
	 */
	resetRowSelect( row ) {
		const ts = this.getTomSelect( row._uid );
		if ( ! ts ) { return; }

		this._clearNoValuesMsg( ts );
		ts.clear( true );
	}

	/**
	 * Destroy a single Tom Select instance before removing its row from Alpine state.
	 *
	 * @param {Object} row
	 */
	destroyRowSelect( row ) {
		const ts = this.getTomSelect( row._uid );
		if ( ! ts ) { return; }

		ts.destroy();
		this._tomSelectMap.delete( row._uid );
	}

	/**
	 * Destroy every Tom Select instance.
	 */
	destroyAll() {
		this._tomSelectMap.forEach( ts => ts.destroy() );
		this._tomSelectMap.clear();
		if ( this._productTs ) {
			this._productTs.destroy();
			this._productTs = null;
		}
	}

	/**
	 * Delegates to RequestsController.
	 *
	 * @param  {number|string} productId
	 * @return {Promise<Array|null>}
	 */
	loadProductAttributes( productId ) {
		return this._requests.getProductAttributes( productId );
	}

	// ── Alpine component registration ─────────────────────────────────────────

	/**
	 * Register the rulesRepeater Alpine component.
	 */
	register() {
		const ctrl = this;

		Alpine.data( 'rulesRepeater', ( rulesData, attrsData ) => ( {

			sections: [],
			query:    '',
			sortDir:  null,   // null | 'asc' | 'desc'
			errorMsg: '',
			attrs:    attrsData || [],

			quickSetupProductId: null,
			quickSetupLoading:   false,
			quickSetupError:     '',

			// ── Lifecycle ─────────────────────────────────────────────────────

			init() {
				( rulesData || [] ).forEach( section => {
					this.sections.push( ctrl.makeSection( {
						attribute:       section.attribute || '',
						attribute_label: section.attribute_label || '',
						rows:            Array.isArray( section.rows ) ? section.rows : [],
					} ) );
				} );

				const form = this.$el.closest( 'form' );
				if ( form ) {
					this._submitHandler = e => this.onSubmit( e );
					form.addEventListener( 'submit', this._submitHandler );
				}
			},

			destroy() {
				this.$el?.closest( 'form' )
					?.removeEventListener( 'submit', this._submitHandler );
				ctrl.destroyAll();
			},

			// ── Computed ──────────────────────────────────────────────────────

			get activeSectionsCount() {
				return this.sections.length;
			},

			get availableAttributes() {
				const used = new Set( this.sections.map( section => section.attribute ) );
				return this.attrs.filter( attr => ! used.has( attr.slug ) );
			},

			get displaySections() {
				const q      = this.query.toLowerCase();
				let   source = this.sections.slice();

				if ( this.sortDir ) {
					source.sort( ( a, b ) => {
						const la = ( a.attribute_label || a.attribute ).toLowerCase();
						const lb = ( b.attribute_label || b.attribute ).toLowerCase();
						return this.sortDir === 'asc'
							? la.localeCompare( lb )
							: lb.localeCompare( la );
					} );
				}

				return source.map( section => {
					const attributeMatches = this._attributeMatchesQuery( section, q );
					let pos = 0;

					const rows = section.rows.map( row => {
						const inDom = ! q || attributeMatches || this._rowMatchesQuery( row, q );
						return { row, inDom, pos: inDom ? ++pos : null };
					} );

					const hasVisibleRows = rows.some( rowEntry => rowEntry.inDom );
					const sectionInDom   = ! q || attributeMatches || hasVisibleRows;
					const forceExpand    = !! q && ! attributeMatches && hasVisibleRows;

					return { section, rows, sectionInDom, forceExpand, expanded: section.expanded || forceExpand };
				} );
			},

			get countLabel() {
				const n = this.displaySections.filter( entry => entry.sectionInDom ).length;
				return sprintf( prbpAdmin.i18n.rules_count, n );
			},

			get hasFilterResults() {
				return this.displaySections.some( entry => entry.sectionInDom );
			},

			sectionSummary( section ) {
				return formatSectionSummary( section, prbpCurrencySymbol, prbpAdmin.i18n.section_term_count );
			},

			// ── Sorting ───────────────────────────────────────────────────────

			toggleSort() {
				if ( this.sortDir === null )  { this.sortDir = 'asc';  return; }
				if ( this.sortDir === 'asc' ) { this.sortDir = 'desc'; return; }
				this.sortDir = null;
			},

			toggleSection( section ) {
				section.expanded = ! section.expanded;
			},

			// ── Section / row CRUD ────────────────────────────────────────────

			addSection( event ) {
				const select = event.target;
				const slug   = select.value;
				if ( ! slug ) { return; }

				const attr = this.attrs.find( item => item.slug === slug );
				if ( ! attr ) { return; }

				this.sections.push( ctrl.makeSection( {
					attribute:       attr.slug,
					attribute_label: attr.label,
					rows:            [ {} ],
					expanded:        true,
				} ) );

				select.value = '';
			},

			deleteSection( section ) {
				const label = section.attribute_label || section.attribute;
				if ( ! window.confirm( sprintf( prbpAdmin.i18n.confirm_delete_section, label ) ) ) {
					return;
				}
				section.rows.forEach( row => ctrl.destroyRowSelect( row ) );
				this.sections = this.sections.filter( item => item !== section );
			},

			addRow( section ) {
				section.rows.push( ctrl.makeRow( section ) );
			},

			deleteRow( section, row ) {
				ctrl.destroyRowSelect( row );
				section.rows = section.rows.filter( item => item !== row );
			},

			resetRow( row ) {
				row.value_ids = [];
				row.value_slugs = [];
				row.value_labels = [];
				row.price = '0';
				ctrl.resetRowSelect( row );
			},

			resetSection( section ) {
				const firstRow = section.rows[0] || ctrl.makeRow( section );
				section.rows.slice( 1 ).forEach( row => ctrl.destroyRowSelect( row ) );
				section.rows = [ firstRow ];
				this.resetRow( firstRow );
			},

			// ── Tom Select init  (called from x-init in the template) ─────────

			initValueSelect( el, row ) {
				ctrl.initValueSelect( el, row );
			},

			initProductSelect( el ) {
				ctrl.initProductSelect( el, this );
			},

			productEditUrl( id ) {
				return prbpAdmin.product_edit_url + id + '&action=edit#product_attributes';
			},

			async importFromProduct() {
				if ( ! this.quickSetupProductId || this.quickSetupLoading ) { return; }
				this.quickSetupLoading = true;
				this.quickSetupError   = '';

				const attrs = await ctrl.loadProductAttributes( this.quickSetupProductId );

				if ( attrs === null ) {
					this.quickSetupError   = 'fetch_error';
					this.quickSetupLoading = false;
					return;
				}
				if ( attrs.length === 0 ) {
					this.quickSetupError   = 'no_attrs';
					this.quickSetupLoading = false;
					return;
				}

				try {
					attrs.forEach( attr => {
						if ( this.sections.some( section => section.attribute === attr.slug ) ) {
							return;
						}

						this.sections.push( ctrl.makeSection( {
							attribute:       attr.slug,
							attribute_label: attr.label,
							rows:            [ {
								value_ids:    attr.value_ids || [],
								value_slugs:  attr.value_slugs || [],
								value_labels: attr.value_labels || [],
								price:        '0',
							} ],
						} ) );
					} );
				} finally {
					this.quickSetupLoading = false;
				}
			},

			// ── Form serialisation ────────────────────────────────────────────

			onSubmit( event ) {
				const payload = [];

				this.sections.forEach( section => {
					const rows = [];

					section.rows.forEach( row => {
						const ts          = ctrl.getTomSelect( row._uid );
						let   selectedIds = ts ? ts.getValue() : row.value_ids;
						if ( ! Array.isArray( selectedIds ) ) {
							selectedIds = selectedIds ? [ selectedIds ] : [];
						}

						rows.push( {
							value_ids:    selectedIds,
							value_slugs:  selectedIds.map( id => ts?.options[ id ]?.slug ?? '' ),
							value_labels: selectedIds.map( id => ts?.options[ id ]?.name ?? '' ),
							price:        row.price,
						} );
					} );

					payload.push( {
						attribute:       section.attribute,
						attribute_label: section.attribute_label,
						rows,
					} );
				} );

				for ( let s = 0; s < payload.length; s++ ) {
					const section = payload[ s ];
					const seen    = Object.create( null );
					for ( const row of section.rows ) {
						for ( let i = 0; i < row.value_slugs.length; i++ ) {
							const slug = row.value_slugs[ i ];
							if ( ! slug ) { continue; }
							if ( seen[ slug ] ) {
								this.sections[ s ].expanded = true;
								event.preventDefault();
								this.errorMsg = sprintf(
									prbpAdmin.i18n.duplicate_msg,
									section.attribute_label || section.attribute,
									row.value_labels[ i ] || slug
								);
								setTimeout( () => {
									this.$el?.querySelector( '.prbp-error-banner' )
										?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
								}, 0 );
								return;
							}
							seen[ slug ] = true;
						}
					}
				}

				this.errorMsg = '';
				const jsonField = document.getElementById( 'prbp-rules-json' );
				if ( jsonField ) {
					jsonField.value = JSON.stringify( payload );
				}
			},

			// ── Filter ────────────────────────────────────────────────────────

			_attributeMatchesQuery( section, q ) {
				if ( ! q ) { return true; }
				const label = ( section.attribute_label || section.attribute || '' ).toLowerCase();
				return label.includes( q );
			},

			_rowMatchesQuery( row, q ) {
				if ( ! q ) { return true; }
				const values = row.value_labels.join( ' ' ).toLowerCase();
				return values.includes( q );
			},

		} ) );
	}
}
