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

	saveBtn.addEventListener( 'click', function () {
		var formData = new FormData( form );
		formData.append( 'action', 'seob_save_settings' );
		formData.append( 'nonce', seobData.nonce );

		// Checkboxy, které nejsou zaškrtnuté, FormData neobsahuje – doplníme je jako "0".
		form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( checkbox ) {
			if ( ! checkbox.checked ) {
				formData.set( checkbox.name, '0' );
			}
		} );

		status.textContent = 'Ukládám…';

		fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( response ) {
			status.textContent = response.success ? 'Uloženo.' : 'Chyba při ukládání.';
		} ).catch( function () {
			status.textContent = 'Chyba při ukládání.';
		} );
	} );
}() );
