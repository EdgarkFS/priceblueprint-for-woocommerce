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
	return str.replace( /%(?:(\d+)\$)?s/g, ( _m, pos ) => {
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
				terms.forEach( t => {
					ts.addOption( { id: String( t.id ), name: t.name, slug: t.slug } );
				} );

				// Pre-select saved values silently (no onChange side-effects).
				savedIds.forEach( id => {
					if ( ts.options[ id ] ) { ts.addItem( id, true ); }
				} );

				// Sync Alpine state to what Tom Select actually accepted.
				const validIds   = savedIds.filter( id => !! ts.options[ id ] );
				rule.value_ids   = validIds;
				rule.value_slugs = validIds.map( id => ts.options[ id ]?.slug ?? '' );
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
		ts.disable();
		this._requests.loadTerms( attribute ).then( terms => {
			ts.clear( true );
			ts.clearOptions();
			terms.forEach( t => {
				ts.addOption( { id: String( t.id ), name: t.name, slug: t.slug } );
			} );
			ts.enable();
		} );
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
			errorMsg: '',
			attrs:    attrsData || [],

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

			get displayRules() {
				const q   = this.query.toLowerCase();
				let   pos = 0;
				return this.rules.map( rule => {
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

				rule.attribute
					? ctrl.loadTermsForSelect( ts, rule.attribute )
					: ts.disable();
			},

			// ── Tom Select init  (called from x-init in the template) ─────────

			initValueSelect( el, rule ) {
				ctrl.initValueSelect( el, rule );
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
