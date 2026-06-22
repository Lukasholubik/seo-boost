( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var saveBtn = document.getElementById( 'seob-save-settings' );
	var status  = document.getElementById( 'seob-settings-status' );
	var form    = document.getElementById( 'seob-settings-form' );

	if ( ! saveBtn || ! form ) {
		return;
	}

	function buildFormData( action ) {
		var formData = new FormData( form );
		formData.set( 'action', action );
		formData.set( 'nonce', seobData.nonce );

		// Checkboxy, které nejsou zaškrtnuté, FormData neobsahuje – doplníme je jako "0".
		form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( checkbox ) {
			if ( ! checkbox.checked ) {
				formData.set( checkbox.name, '0' );
			}
		} );

		return formData;
	}

	function postAction( action ) {
		return fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: buildFormData( action )
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	saveBtn.addEventListener( 'click', function () {
		status.textContent = 'Ukládám…';

		Promise.all( [
			postAction( 'seob_save_settings' ),
			postAction( 'seob_save_ai_settings' ),
			postAction( 'seob_save_pagespeed_settings' )
		] ).then( function ( responses ) {
			var success = responses.every( function ( response ) {
				return response && response.success;
			} );

			status.textContent = success ? 'Uloženo.' : 'Chyba při ukládání.';
		} ).catch( function () {
			status.textContent = 'Chyba při ukládání.';
		} );
	} );
}() );
