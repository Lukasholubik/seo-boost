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

	// ── CSV Import – 2-krokový flow (Náhled → Potvrdit) ─────────────────────
	var csvFile      = document.getElementById( 'seob-csv-file' );
	var httpCode     = document.getElementById( 'seob-http-code' );
	var previewBtn   = document.getElementById( 'seob-preview-csv' );
	var step2        = document.getElementById( 'seob-csv-step2' );
	var previewBody  = document.getElementById( 'seob-preview-body' );
	var previewSum   = document.getElementById( 'seob-preview-summary' );
	var confirmBtn   = document.getElementById( 'seob-confirm-import' );
	var cancelBtn    = document.getElementById( 'seob-cancel-preview' );
	var importStatus = document.getElementById( 'seob-import-status' );
	var importResult = document.getElementById( 'seob-import-result' );

	if ( ! previewBtn ) { return; }

	// Krok 1 – Náhled
	previewBtn.addEventListener( 'click', function () {
		if ( ! csvFile || ! csvFile.files || ! csvFile.files[0] ) {
			alert( 'Nejprve vyberte CSV soubor.' );
			return;
		}

		previewBtn.disabled = true;
		previewBtn.textContent = 'Načítám náhled…';
		step2.style.display = 'none';
		importResult.style.display = 'none';

		var fd = new FormData();
		fd.append( 'action', 'seob_redirect_preview_csv' );
		fd.append( 'nonce', seobData.nonce );
		fd.append( 'csv_file', csvFile.files[0] );

		fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
		.then( function ( r ) { return r.json(); } )
		.then( function ( res ) {
			previewBtn.disabled = false;
			previewBtn.textContent = 'Náhled párování';

			if ( ! res.success ) {
				importResult.style.display = 'block';
				importResult.innerHTML = '<span style="color:#d63638;">' + ( res.data && res.data.message ? res.data.message : 'Chyba při načítání.' ) + '</span>';
				return;
			}

			var rows   = res.data.rows;
			var total  = res.data.total;
			var valid  = rows.filter( function ( r ) { return r.valid; } ).length;
			var bad    = rows.filter( function ( r ) { return ! r.valid; } ).length;

			// Přehledový souhrn
			previewSum.innerHTML =
				'Celkem <strong>' + total + '</strong> řádků &nbsp;|&nbsp; ' +
				'<span style="color:#00a32a;">✔ Platných: <strong>' + valid + '</strong></span>' +
				( bad ? ' &nbsp;|&nbsp; <span style="color:#d63638;">✘ Chybných: <strong>' + bad + '</strong></span>' : '' ) +
				( total > 500 ? ' &nbsp;— zobrazeno prvních 500' : '' );

			// Tabulka náhledu
			previewBody.innerHTML = '';
			rows.forEach( function ( r ) {
				var tr = document.createElement( 'tr' );
				tr.style.background = r.valid ? '' : '#fff5f5';
				tr.innerHTML =
					'<td style="color:#999;">' + r.line + '</td>' +
					'<td style="font-family:monospace;font-size:11px;word-break:break-all;">' + esc( r.source || r.raw_source ) + '</td>' +
					'<td style="font-family:monospace;font-size:11px;word-break:break-all;">' + esc( r.target || r.raw_target ) + '</td>' +
					'<td>' + ( r.valid
						? '<span style="color:#00a32a;">✔ OK</span>'
						: '<span style="color:#d63638;" title="' + esc( r.error ) + '">✘ ' + esc( r.error ) + '</span>'
					) + '</td>';
				previewBody.appendChild( tr );
			} );

			confirmBtn.textContent = 'Potvrdit a importovat (' + valid + ' přesměrování)';
			confirmBtn.disabled = valid === 0;
			importStatus.textContent = '';
			step2.style.display = 'block';
		} )
		.catch( function () {
			previewBtn.disabled = false;
			previewBtn.textContent = 'Náhled párování';
			importResult.style.display = 'block';
			importResult.innerHTML = '<span style="color:#d63638;">Chyba sítě.</span>';
		} );
	} );

	// Krok 2 – Potvrdit import
	confirmBtn && confirmBtn.addEventListener( 'click', function () {
		if ( ! csvFile || ! csvFile.files || ! csvFile.files[0] ) { return; }

		confirmBtn.disabled = true;
		importStatus.textContent = 'Importuji…';

		var fd = new FormData();
		fd.append( 'action', 'seob_redirect_import_csv' );
		fd.append( 'nonce', seobData.nonce );
		fd.append( 'csv_file', csvFile.files[0] );
		fd.append( 'http_code', httpCode ? httpCode.value : '301' );

		fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
		.then( function ( r ) { return r.json(); } )
		.then( function ( res ) {
			confirmBtn.disabled = false;
			importStatus.textContent = '';
			step2.style.display = 'none';
			importResult.style.display = 'block';

			if ( res.success ) {
				var d = res.data;
				var codeLabel = httpCode ? httpCode.options[ httpCode.selectedIndex ].text : '301';
				var html = '<div style="padding:12px 16px;background:#edfaef;border-left:4px solid #00a32a;border-radius:2px;">';
				html += '<strong>✅ Import dokončen</strong> <span style="color:#666;font-size:12px;">(' + codeLabel + ')</span><br style="margin-bottom:6px;">';
				html += '✅ Vytvořeno: <strong>' + d.created + '</strong> &nbsp;|&nbsp; ';
				html += '🔄 Aktualizováno: <strong>' + d.updated + '</strong> &nbsp;|&nbsp; ';
				html += '⏭ Přeskočeno: <strong>' + d.skipped + '</strong>';
				if ( d.errors && d.errors.length ) {
					html += '<details style="margin-top:8px;"><summary style="cursor:pointer;color:#666;font-size:12px;">Zobrazit chyby (' + d.errors.length + ')</summary>';
					html += '<ul style="margin:6px 0 0 16px;font-size:11px;color:#666;">';
					d.errors.forEach( function ( e ) { html += '<li>' + esc( e ) + '</li>'; } );
					html += '</ul></details>';
				}
				html += '</div>';
				importResult.innerHTML = html;
				loadList();
			} else {
				importResult.innerHTML = '<div style="padding:10px 14px;background:#fcf0f1;border-left:4px solid #d63638;border-radius:2px;color:#d63638;">' +
					( res.data && res.data.message ? esc( res.data.message ) : 'Import selhal.' ) + '</div>';
			}
		} )
		.catch( function () {
			confirmBtn.disabled = false;
			importStatus.textContent = '';
			importResult.style.display = 'block';
			importResult.innerHTML = '<span style="color:#d63638;">Chyba sítě.</span>';
		} );
	} );

	// Zrušit náhled
	cancelBtn && cancelBtn.addEventListener( 'click', function () {
		step2.style.display = 'none';
		importResult.style.display = 'none';
	} );

	// HTML escape helper
	function esc( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
}() );
