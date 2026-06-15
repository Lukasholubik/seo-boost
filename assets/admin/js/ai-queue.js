( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var body         = document.getElementById( 'seob-ai-queue-body' );
	var statusSelect = document.getElementById( 'seob-ai-queue-status' );
	var template     = document.getElementById( 'seob-ai-queue-row-template' );

	if ( ! body || ! statusSelect || ! template ) {
		return;
	}

	function ajax( action, data ) {
		var formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', seobData.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			formData.append( key, data[ key ] );
		} );

		return fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function renderItems( items, status ) {
		body.innerHTML = '';

		if ( ! items.length ) {
			body.innerHTML = '<tr class="seob-empty-row"><td colspan="5">' + 'Žádné položky.' + '</td></tr>';
			return;
		}

		items.forEach( function ( item ) {
			var row = template.content.cloneNode( true );
			var tr  = row.querySelector( 'tr' );

			tr.querySelector( '.seob-aiq-field' ).textContent = item.field_label || '';

			var editLink = tr.querySelector( '.seob-aiq-edit-link' );
			editLink.textContent = item.object_title || '';
			editLink.href = item.edit_link || '#';

			var preview = tr.querySelector( '.seob-aiq-preview' );

			if ( item.preview_url ) {
				preview.src = item.preview_url;
				preview.style.display = '';
			}

			tr.querySelector( '.seob-aiq-current' ).textContent = item.current_value || '';
			tr.querySelector( '.seob-aiq-suggestion' ).textContent = item.suggestion || '';

			var approveBtn = tr.querySelector( '.seob-aiq-approve' );
			var rejectBtn  = tr.querySelector( '.seob-aiq-reject' );

			if ( 'pending' !== status ) {
				approveBtn.remove();
				rejectBtn.remove();
			} else {
				approveBtn.addEventListener( 'click', function () {
					ajax( 'seob_ai_queue_approve', { id: item.id } ).then( function ( response ) {
						if ( response.success ) {
							loadList();
						} else {
							window.alert( response.data && response.data.message ? response.data.message : 'Chyba.' );
						}
					} );
				} );

				rejectBtn.addEventListener( 'click', function () {
					ajax( 'seob_ai_queue_reject', { id: item.id } ).then( function ( response ) {
						if ( response.success ) {
							loadList();
						} else {
							window.alert( response.data && response.data.message ? response.data.message : 'Chyba.' );
						}
					} );
				} );
			}

			body.appendChild( row );
		} );
	}

	function loadList() {
		var status = statusSelect.value;

		body.innerHTML = '<tr class="seob-empty-row"><td colspan="5">Načítám…</td></tr>';

		fetch( seobData.ajaxUrl + '?action=seob_ai_queue_list&status=' + encodeURIComponent( status ) + '&nonce=' + encodeURIComponent( seobData.nonce ), {
			credentials: 'same-origin'
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( response ) {
			if ( ! response.success ) {
				body.innerHTML = '<tr class="seob-empty-row"><td colspan="5">Chyba při načítání.</td></tr>';
				return;
			}

			renderItems( response.data.items || [], status );
		} );
	}

	statusSelect.addEventListener( 'change', loadList );

	loadList();
}() );
