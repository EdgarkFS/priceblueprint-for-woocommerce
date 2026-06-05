/* global prbpWelcome */
'use strict';

document.addEventListener( 'DOMContentLoaded', function () {
	var btn  = document.getElementById( 'prbp-import-demo' );
	var wrap = document.getElementById( 'prbp-demo-wrap' );

	if ( ! btn || ! wrap ) {
		return;
	}

	var originalLabel = btn.textContent.trim();

	btn.addEventListener( 'click', function () {
		btn.disabled  = true;
		btn.innerHTML = '<span class="prbp-demo-spinner"></span>';

		var body = new FormData();
		body.append( 'action', 'prbp_import_demo' );
		body.append( 'nonce',  prbpWelcome.nonce );

		fetch( prbpWelcome.ajax_url, { method: 'POST', body: body } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( ! data.success ) {
					throw new Error();
				}

				var d = data.data;

				wrap.innerHTML =
					'<p class="prbp-welcome-steps-title prbp-demo-ready-label">' + prbpWelcome.i18n.demo_ready + '</p>' +
					'<div class="prbp-demo-links">' +
					'<a href="' + d.blueprint_edit_url + '">' + prbpWelcome.i18n.edit_blueprint + '</a>' +
					'<a href="' + d.product_edit_url   + '">' + prbpWelcome.i18n.edit_product   + '</a>' +
					'<a href="' + d.product_url + '" target="_blank" rel="noopener">' + prbpWelcome.i18n.view_product + '</a>' +
					'</div>';
			} )
			.catch( function () {
				btn.disabled    = false;
				btn.textContent = originalLabel;

				if ( ! wrap.querySelector( '.prbp-demo-error' ) ) {
					var err       = document.createElement( 'span' );
					err.className = 'prbp-demo-error';
					err.textContent = prbpWelcome.i18n.error;
					wrap.appendChild( err );
				}
			} );
	} );
} );
