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

	// ── CSV Import ────────────────────────────────────────────────────────────
	var importBtn    = document.getElementById( 'seob-import-csv' );
	var importFile   = document.getElementById( 'seob-csv-file' );
	var importResult = document.getElementById( 'seob-import-result' );

	if ( importBtn && importFile ) {
		importBtn.addEventListener( 'click', function () {
			if ( ! importFile.files || ! importFile.files[0] ) {
				importResult.style.display = 'block';
				importResult.innerHTML = '<span style="color:#d63638;">Nejprve vyberte CSV soubor.</span>';
				return;
			}

			importBtn.disabled = true;
			importBtn.textContent = 'Importuji…';
			importResult.style.display = 'none';

			var formData = new FormData();
			formData.append( 'action', 'seob_redirect_import_csv' );
			formData.append( 'nonce', seobData.nonce );
			formData.append( 'csv_file', importFile.files[0] );

			fetch( seobData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( response ) {
				importBtn.disabled = false;
				importBtn.textContent = 'Importovat';
				importResult.style.display = 'block';

				if ( response.success ) {
					var d = response.data;
					var html = '<div style="padding:10px 14px;background:#edfaef;border-left:4px solid #00a32a;border-radius:2px;">';
					html += '<strong>Import dokončen</strong><br>';
					html += '✅ Vytvořeno: <strong>' + d.created + '</strong> &nbsp;|&nbsp; ';
					html += '🔄 Aktualizováno: <strong>' + d.updated + '</strong> &nbsp;|&nbsp; ';
					html += '⏭ Přeskočeno: <strong>' + d.skipped + '</strong>';
					if ( d.errors && d.errors.length ) {
						html += '<details style="margin-top:8px;"><summary style="cursor:pointer;color:#666;">Zobrazit chyby (' + d.errors.length + ')</summary>';
						html += '<ul style="margin:6px 0 0 16px;font-size:12px;color:#666;">';
						d.errors.forEach( function ( e ) { html += '<li>' + e + '</li>'; } );
						html += '</ul></details>';
					}
					html += '</div>';
					importResult.innerHTML = html;
					loadList(); // obnov seznam přesměrování
				} else {
					importResult.innerHTML = '<span style="color:#d63638;">' +
						( response.data && response.data.message ? response.data.message : 'Import selhal.' ) +
						'</span>';
				}
			} )
			.catch( function () {
				importBtn.disabled = false;
				importBtn.textContent = 'Importovat';
				importResult.style.display = 'block';
				importResult.innerHTML = '<span style="color:#d63638;">Chyba sítě. Zkuste to znovu.</span>';
			} );
		} );
	}
}() );
