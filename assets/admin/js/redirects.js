( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var redirectsBody = document.getElementById( 'seob-redirects-body' );
	var notFoundBody  = document.getElementById( 'seob-404-body' );
	var addBtn        = document.getElementById( 'seob-add-redirect' );
	var addStatus     = document.getElementById( 'seob-add-status' );
	var sourceInput   = document.getElementById( 'seob-new-source' );
	var targetInput   = document.getElementById( 'seob-new-target' );

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

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text || '';
		return div.innerHTML;
	}

	function renderRedirects( redirects ) {
		redirectsBody.innerHTML = '';

		if ( ! redirects.length ) {
			redirectsBody.innerHTML = '<tr class="seob-empty-row"><td colspan="4">Žádná přesměrování zatím nejsou nastavena.</td></tr>';
			return;
		}

		redirects.forEach( function ( item ) {
			var tr = document.createElement( 'tr' );
			tr.innerHTML =
				'<td>' + escapeHtml( item.target_url ) + '</td>' +
				'<td>' + escapeHtml( item.redirect_to ) + '</td>' +
				'<td>' + ( item.http_status || 301 ) + '</td>' +
				'<td><button type="button" class="button seob-delete-redirect">Smazat</button></td>';

			tr.querySelector( '.seob-delete-redirect' ).addEventListener( 'click', function () {
				if ( ! window.confirm( 'Opravdu smazat toto přesměrování?' ) ) {
					return;
				}
				ajax( 'seob_redirect_delete', { id: item.id } ).then( function ( response ) {
					if ( response.success ) {
						loadList();
					}
				} );
			} );

			redirectsBody.appendChild( tr );
		} );
	}

	function renderNotFound( items ) {
		notFoundBody.innerHTML = '';

		if ( ! items.length ) {
			notFoundBody.innerHTML = '<tr class="seob-empty-row"><td colspan="5">Zatím nebyly zaznamenány žádné 404 chyby.</td></tr>';
			return;
		}

		items.forEach( function ( item ) {
			var tr = document.createElement( 'tr' );
			tr.innerHTML =
				'<td>' + escapeHtml( item.target_url ) + '</td>' +
				'<td>' + item.hits_404 + '</td>' +
				'<td>' + escapeHtml( item.last_checked || '' ) + '</td>' +
				'<td><input type="text" class="seob-redirect-target" placeholder="/nova-adresa/"></td>' +
				'<td>' +
					'<button type="button" class="button button-primary seob-create-from-404">Vytvořit přesměrování</button> ' +
					'<button type="button" class="button seob-dismiss-404">Smazat</button>' +
				'</td>';

			tr.querySelector( '.seob-create-from-404' ).addEventListener( 'click', function () {
				var targetVal = tr.querySelector( '.seob-redirect-target' ).value.trim();

				if ( ! targetVal ) {
					window.alert( 'Zadejte cílovou adresu.' );
					return;
				}

				ajax( 'seob_redirect_save', {
					id: item.id,
					target_url: item.target_url,
					redirect_to: targetVal
				} ).then( function ( response ) {
					if ( response.success ) {
						loadList();
					} else {
						window.alert( response.data && response.data.message ? response.data.message : 'Chyba.' );
					}
				} );
			} );

			tr.querySelector( '.seob-dismiss-404' ).addEventListener( 'click', function () {
				ajax( 'seob_redirect_delete', { id: item.id } ).then( function ( response ) {
					if ( response.success ) {
						loadList();
					}
				} );
			} );

			notFoundBody.appendChild( tr );
		} );
	}

	function loadList() {
		ajax( 'seob_redirect_list', {} ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			renderRedirects( response.data.redirects || [] );
			renderNotFound( response.data.not_found || [] );
		} );
	}

	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () {
			var source = sourceInput.value.trim();
			var target = targetInput.value.trim();

			if ( ! source || ! target ) {
				addStatus.textContent = 'Vyplňte obě pole.';
				return;
			}

			addStatus.textContent = 'Ukládám…';

			ajax( 'seob_redirect_save', { target_url: source, redirect_to: target } ).then( function ( response ) {
				if ( response.success ) {
					addStatus.textContent = 'Uloženo.';
					sourceInput.value = '';
					targetInput.value = '';
					loadList();
				} else {
					addStatus.textContent = response.data && response.data.message ? response.data.message : 'Chyba.';
				}
			} );
		} );
	}

	loadList();
}() );
