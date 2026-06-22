( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var saveBtn = document.getElementById( 'seob-save-pdf-settings' );
	var status  = document.getElementById( 'seob-pdf-settings-status' );
	var form    = document.getElementById( 'seob-pdf-settings-form' );

	if ( ! saveBtn || ! form ) {
		return;
	}

	var logoButton  = document.getElementById( 'seob-pdf-company-logo-button' );
	var logoRemove  = document.getElementById( 'seob-pdf-company-logo-remove' );
	var logoIdInput = document.getElementById( 'seob-pdf-company-logo-id' );
	var logoPreview = document.getElementById( 'seob-pdf-company-logo-preview' );
	var logoImg     = logoPreview ? logoPreview.querySelector( 'img' ) : null;

	if ( logoButton && typeof wp !== 'undefined' && wp.media ) {
		var mediaFrame = null;

		logoButton.addEventListener( 'click', function () {
			if ( ! mediaFrame ) {
				mediaFrame = wp.media( {
					title: 'Vybrat logo',
					button: { text: 'Použít logo' },
					library: { type: 'image' },
					multiple: false
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
					var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;

					logoIdInput.value = attachment.id;

					if ( logoImg ) {
						logoImg.src = url;
					}

					if ( logoPreview ) {
						logoPreview.style.display = '';
					}

					if ( logoRemove ) {
						logoRemove.style.display = '';
					}
				} );
			}

			mediaFrame.open();
		} );
	}

	if ( logoRemove ) {
		logoRemove.addEventListener( 'click', function () {
			logoIdInput.value = '0';

			if ( logoPreview ) {
				logoPreview.style.display = 'none';
			}

			logoRemove.style.display = 'none';
		} );
	}

	saveBtn.addEventListener( 'click', function () {
		var formData = new FormData( form );
		formData.append( 'action', 'seob_save_pdf_settings' );
		formData.append( 'nonce', seobData.nonce );

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
