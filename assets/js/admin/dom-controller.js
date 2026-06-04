/**
 * DomController — all DOM / UI concerns for the pricing-rules repeater.
 *
 * Responsibilities:
 *   - Rule object factory (makeRule).
 *   - Tom Select instance lifecycle: create, populate, destroy.
 *   - Alpine component registration (register).
 *
 * Depends on RequestsController for term fetching; never calls fetch() itself.
 *
 * @module dom-controller
 * @package PriceBlueprint
 */

/* global prbpAdmin, Alpine, TomSelect */

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

// ---------------------------------------------------------------------------
// Class
// ---------------------------------------------------------------------------

export class DomController {

	/**
	 * @param {import('./requests-controller.js').RequestsController} requests
	 */
	constructor( requests ) {
		this._requests     = requests;
		/** @type {Map<number, TomSelect>}  rule._uid → instance */
		this._tomSelectMap = new Map();
		this._uid          = 0;
		this._productTs    = null;
	}

	// ── Rule factory ──────────────────────────────────────────────────────────

	/**
	 * Create a plain rule object merged with sane defaults.
	 *
	 * _uid is a stable internal key used by x-for (:key) and _tomSelectMap.
	 * It is never serialised to the JSON payload sent to PHP.
	 *
	 * @param  {Object} [data]
	 * @return {Object}
	 */
	makeRule( data = {} ) {
		return Object.assign(
			{
				_uid:            ++this._uid,
				attribute:       '',
				attribute_label: '',
				// Parallel arrays kept in sync by Tom Select onChange.
				value_ids:   [],
				value_slugs: [],
				value_labels: [],
				price:    '0',
				operator: '+',
				status:   'active',
				v:        1,
			},
			data
		);
	}

	// ── Tom Select lifecycle ──────────────────────────────────────────────────

	/**
	 * Create a Tom Select instance on a <select multiple> element.
	 * Called once per row from x-init in the Alpine template.
	 *
	 * Loads terms via AJAX if the rule already has an attribute, then
	 * silently pre-selects the previously saved values.
	 *
	 * @param {HTMLSelectElement} el    The target <select> element.
	 * @param {Object}            rule  Alpine-reactive rule object.
	 */
	initValueSelect( el, rule ) {
		// Snapshot saved IDs before onChange could overwrite them.
		const savedIds = rule.value_ids.slice();

		let ts;
		ts = new TomSelect( el, {
			plugins:        [ 'remove_button' ],
			valueField:     'id',
			labelField:     'name',
			searchField:    [ 'name' ],
			options:        [],
			items:          [],
			placeholder:    prbpAdmin.i18n.select_value,
			create:         false,
			dropdownParent: null,

			shouldLoad: ( query ) => {
				const total = Object.keys( ts.options ).length;
				if ( total > 0 && ts.items.length >= total ) return true;
				return query.length > 0;
			},

			render: {
				no_results: () => {
					const total = Object.keys( ts.options ).length;
					if ( total > 0 && ts.items.length >= total ) {
						return `<div class="no-results">${ prbpAdmin.i18n.all_values_selected }</div>`;
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
					ts.settings.placeholder      = prbpAdmin.i18n.select_value;
					ts.control_input.placeholder = prbpAdmin.i18n.select_value;
				}
			},

			onChange: ( values ) => {
				if ( ! Array.isArray( values ) ) {
					values = values ? [ values ] : [];
				}
				rule.value_ids   = values.slice();
				rule.value_slugs = values.map( id => ts.options[ id ]?.slug ?? '' );
				rule.value_labels = values.map( id => ts.options[ id ]?.name ?? '' );
			},
		} );

		this._tomSelectMap.set( rule._uid, ts );

		if ( rule.attribute ) {
			ts.disable();
			this._requests.loadTerms( rule.attribute ).then( terms => {
				if ( terms.length === 0 ) {
					this._showNoValuesMsg( ts, rule.attribute );
					return;
				}

				terms.forEach( t => {
					ts.addOption( { id: String( t.id ), name: t.name, slug: t.slug } );
				} );

				// Pre-select saved values silently (no onChange side-effects).
				savedIds.forEach( id => {
					if ( ts.options[ id ] ) { ts.addItem( id, true ); }
				} );

				// Sync Alpine state to what Tom Select actually accepted.
				const validIds    = savedIds.filter( id => !! ts.options[ id ] );
				rule.value_ids    = validIds;
				rule.value_slugs  = validIds.map( id => ts.options[ id ]?.slug ?? '' );
				rule.value_labels = validIds.map( id => ts.options[ id ]?.name ?? '' );

				ts.enable();
			} );
		} else {
			ts.disable();
		}
	}

	/**
	 * Re-populate an existing Tom Select for a newly chosen attribute.
	 * Clears the previous selection — the user changed the attribute
	 * intentionally so no prior value should carry over.
	 *
	 * @param {TomSelect} ts
	 * @param {string}    attribute
	 */
	loadTermsForSelect( ts, attribute ) {
		this._clearNoValuesMsg( ts );
		ts.disable();
		this._requests.loadTerms( attribute ).then( terms => {
			ts.clear( true );
			ts.clearOptions();
			if ( terms.length === 0 ) {
				this._showNoValuesMsg( ts, attribute );
				return;
			}
			terms.forEach( t => {
				ts.addOption( { id: String( t.id ), name: t.name, slug: t.slug } );
			} );
			ts.enable();
		} );
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
	 * Hide the Tom Select wrapper and insert a sibling no-values message with
	 * a link to the WP term management page for the given attribute.
	 *
	 * @param {TomSelect} ts
	 * @param {string}    attribute  Taxonomy slug, e.g. "pa_color".
	 */
	_showNoValuesMsg( ts, attribute ) {
		ts.wrapper.style.display = 'none';
		const span     = document.createElement( 'span' );
		span.className = 'prbp-no-values-msg';
		span.appendChild( document.createTextNode( prbpAdmin.i18n.no_values_msg + ' ' ) );
		const link       = document.createElement( 'a' );
		link.href        = prbpAdmin.wc_terms_url + attribute + '&post_type=product';
		link.target      = '_blank';
		link.rel         = 'noopener';
		link.textContent = prbpAdmin.i18n.no_values_link;
		span.appendChild( link );
		ts.wrapper.insertAdjacentElement( 'afterend', span );
	}

	/**
	 * Public proxy so the Alpine component can call this without touching private methods.
	 *
	 * @param {TomSelect} ts
	 */
	clearNoValuesMsg( ts ) {
		this._clearNoValuesMsg( ts );
	}

	/**
	 * Create a single-select Tom Select for the Quick Setup product search.
	 * Uses the load callback to hit prbp_search_products (min 2 chars).
	 * Called from the Alpine component's initProductSelect(el) proxy.
	 *
	 * @param {HTMLSelectElement} el
	 * @param {Object}            component  Alpine component instance (this).
	 */
	initProductSelect( el, component ) {
		const requests = this._requests;
		const ts = new TomSelect( el, {
			valueField:    'id',
			labelField:    'title',
			searchField:   [ 'title' ],
			maxItems:      1,
			options:       [],
			items:         [],
			placeholder:   prbpAdmin.i18n.qs_search_prompt,
			create:        false,
			sortField:     { field: 'text', direction: 'asc' },
			preload:       'focus',
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
	 * Return the Tom Select instance for a rule, or null.
	 *
	 * @param  {number} uid  rule._uid
	 * @return {TomSelect|null}
	 */
	getTomSelect( uid ) {
		return this._tomSelectMap.get( uid ) ?? null;
	}

	/**
	 * Destroy every Tom Select instance.
	 * Called from the Alpine component's destroy() hook.
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
	 * Delegates to RequestsController. Keeps Alpine component methods from
	 * touching _requests directly (consistent with existing DomController API).
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
	 * Must be called inside an alpine:init event listener.
	 */
	register() {
		const ctrl = this;

		Alpine.data( 'rulesRepeater', ( rulesData, attrsData ) => ( {

			rules:    [],
			query:    '',
			sortDir:  null,   // null | 'asc' | 'desc'
			errorMsg: '',
			attrs:    attrsData || [],

			quickSetupProductId: null,
			quickSetupLoading:   false,
			quickSetupError:     '',

			// ── Lifecycle ─────────────────────────────────────────────────────

			init() {
				( rulesData || [] ).forEach( r => {
					this.rules.push( ctrl.makeRule( {
						attribute:       r.attribute       || '',
						attribute_label: r.attribute_label || '',
						value_ids:    Array.isArray( r.value_ids )    ? r.value_ids.map( String ) : [],
						value_slugs:  Array.isArray( r.value_slugs )  ? r.value_slugs.map( String )  : [],
						value_labels: Array.isArray( r.value_labels ) ? r.value_labels.map( String ) : [],
						price:    r.price    || '0',
						operator: r.operator || '+',
						status:   'active',
					} ) );
				} );

				// Hook WP's post form — it wraps the entire page, so
				// @submit on our meta-box div alone is not enough.
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

			get activeRulesCount() {
				return this.rules.filter( r => r.status !== 'deleted' ).length;
			},

			get displayRules() {
				const q      = this.query.toLowerCase();
				let   pos    = 0;
				let   source = this.rules.slice();

				if ( this.sortDir ) {
					source.sort( ( a, b ) => {
						const la = ( a.attribute_label || a.attribute ).toLowerCase();
						const lb = ( b.attribute_label || b.attribute ).toLowerCase();
						return this.sortDir === 'asc'
							? la.localeCompare( lb )
							: lb.localeCompare( la );
					} );
				}

				return source.map( rule => {
					const isDeleted = rule.status === 'deleted';
					const matches   = ! q || this._matchesQuery( rule, q );
					const inDom     = ! isDeleted && matches;
					return { rule, inDom, pos: inDom ? ++pos : null };
				} );
			},

			get countLabel() {
				const q = this.query.toLowerCase();
				const n = this.rules.filter(
					r => r.status !== 'deleted' && ( ! q || this._matchesQuery( r, q ) )
				).length;
				return sprintf( prbpAdmin.i18n.rules_count, n );
			},

			// ── Sorting ───────────────────────────────────────────────────────

			toggleSort() {
				if ( this.sortDir === null )  { this.sortDir = 'asc';  return; }
				if ( this.sortDir === 'asc' ) { this.sortDir = 'desc'; return; }
				this.sortDir = null;
			},

			// ── Rule CRUD ─────────────────────────────────────────────────────

			addRule() {
				// Tom Select is wired up by x-init on the new row's <select>.
				this.rules.push( ctrl.makeRule( {} ) );
			},

			deleteRule( rule ) {
				rule.status = 'deleted';
			},

			restoreRule( rule ) {
				rule.attribute       = '';
				rule.attribute_label = '';
				rule.value_ids       = [];
				rule.value_slugs     = [];
				rule.value_labels    = [];
				rule.price           = '0';
				rule.status          = 'active';

				const ts = ctrl.getTomSelect( rule._uid );
				if ( ts ) {
					ctrl.clearNoValuesMsg( ts );
					ts.clear( true );
					ts.clearOptions();
					ts.disable();
				}
			},

			// ── Attribute select ──────────────────────────────────────────────

			onAttributeChange( rule, event ) {
				const sel            = event.target;
				const opt            = sel.options[ sel.selectedIndex ];
				rule.attribute       = sel.value;
				rule.attribute_label = opt ? opt.text.trim() : '';
				rule.value_ids       = [];
				rule.value_slugs     = [];
				rule.value_labels    = [];

				const ts = ctrl.getTomSelect( rule._uid );
				if ( ! ts ) { return; }

				ts.clear( true );
				ts.clearOptions();

				if ( rule.attribute ) {
					ctrl.loadTermsForSelect( ts, rule.attribute );
				} else {
					ctrl.clearNoValuesMsg( ts );
					ts.disable();
				}
			},

			// ── Tom Select init  (called from x-init in the template) ─────────

			initValueSelect( el, rule ) {
				ctrl.initValueSelect( el, rule );
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
						this.rules.push( ctrl.makeRule( {
							attribute:       attr.slug,
							attribute_label: attr.label,
							value_ids:       attr.value_ids    || [],
							value_slugs:     attr.value_slugs  || [],
							value_labels:    attr.value_labels || [],
							price:           '0',
						} ) );
					} );
				} finally {
					this.quickSetupLoading = false;
				}
				// activeRulesCount > 0 now — Quick Setup block hides reactively.
				// Each new row's x-init fires initValueSelect which loads and pre-selects value_ids.
			},

			// ── Form serialisation ────────────────────────────────────────────

			onSubmit( event ) {
				const payload = [];

				this.rules.forEach( rule => {
					if ( rule.status === 'deleted' ) { return; }

					const ts          = ctrl.getTomSelect( rule._uid );
					let   selectedIds = ts ? ts.getValue() : rule.value_ids;
					if ( ! Array.isArray( selectedIds ) ) {
						selectedIds = selectedIds ? [ selectedIds ] : [];
					}

					payload.push( {
						attribute:       rule.attribute,
						attribute_label: rule.attribute_label,
						value_ids:       selectedIds,
						value_slugs:     selectedIds.map( id => ts?.options[ id ]?.slug  ?? '' ),
						value_labels:    selectedIds.map( id => ts?.options[ id ]?.name  ?? '' ),
						price:           rule.price,
						operator:        rule.operator,
					} );
				} );

				// Duplicate check — same (attribute, value_slug) pair across all rules.
				const seen = Object.create( null );
				for ( const r of payload ) {
					if ( ! r.attribute ) { continue; }
					for ( let i = 0; i < r.value_slugs.length; i++ ) {
						const slug = r.value_slugs[ i ];
						if ( ! slug ) { continue; }
						const key = `${ r.attribute }|${ slug }`;
						if ( seen[ key ] ) {
							event.preventDefault();
							this.errorMsg = sprintf(
								prbpAdmin.i18n.duplicate_msg,
								r.attribute_label || r.attribute,
								r.value_labels[ i ] || slug
							);
							setTimeout( () => {
								this.$el?.querySelector( '.prbp-error-banner' )
									?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
							}, 0 );
							return;
						}
						seen[ key ] = true;
					}
				}

				this.errorMsg = '';
				const jsonField = document.getElementById( 'prbp-rules-json' );
				if ( jsonField ) {
					jsonField.value = JSON.stringify( payload );
				}
			},

			// ── Filter ────────────────────────────────────────────────────────

			_matchesQuery( rule, q ) {
				const a = ( rule.attribute_label || rule.attribute || '' ).toLowerCase();
				const v = rule.value_labels.join( ' ' ).toLowerCase();
				return a.includes( q ) || v.includes( q );
			},

		} ) );
	}
}
