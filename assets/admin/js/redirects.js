( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	// ── DOM refs ──────────────────────────────────────────────────────────────
	var redirectsBody  = document.getElementById( 'seob-redirects-body' );
	var notFoundBody   = document.getElementById( 'seob-404-body' );
	var addBtn         = document.getElementById( 'seob-add-redirect' );
	var addStatus      = document.getElementById( 'seob-add-status' );
	var sourceInput    = document.getElementById( 'seob-new-source' );
	var targetInput    = document.getElementById( 'seob-new-target' );

	// Rychlé cíle
	var loadPagesBtn   = document.getElementById( 'seob-load-pages' );
	var pagesList      = document.getElementById( 'seob-pages-list' );

	// Export
	var exportCsvBtn   = document.getElementById( 'seob-export-csv' );
	var exportHtaBtn   = document.getElementById( 'seob-export-htaccess' );

	// Bulk – přesměrování
	var redirectsCheckAll = document.getElementById( 'seob-redirects-check-all' );
	var redirectsBulkBar  = document.getElementById( 'seob-redirects-bulk-bar' );
	var redirectsBulkCnt  = document.getElementById( 'seob-redirects-bulk-count' );
	var redirectsBulkDel  = document.getElementById( 'seob-redirects-bulk-delete' );

	// Bulk – 404
	var f404CheckAll   = document.getElementById( 'seob-404-check-all' );
	var f404BulkBar    = document.getElementById( 'seob-404-bulk-bar' );
	var f404BulkCnt    = document.getElementById( 'seob-404-bulk-count' );
	var f404BulkTarget = document.getElementById( 'seob-404-bulk-target' );
	var f404BulkSave   = document.getElementById( 'seob-404-bulk-save' );
	var f404BulkDel    = document.getElementById( 'seob-404-bulk-delete' );

	// 404 threshold filtr
	var f404MinHits    = document.getElementById( 'seob-404-min-hits' );
	var f404FilterBtn  = document.getElementById( 'seob-404-filter-btn' );
	var f404FilterInfo = document.getElementById( 'seob-404-filter-info' );

	// Cache všech načtených dat
	var allRedirects = [];
	var all404s      = [];

	// ── Helpers ───────────────────────────────────────────────────────────────

	function ajax( action, data ) {
		var formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', seobData.nonce );
		Object.keys( data || {} ).forEach( function ( k ) { formData.append( k, data[ k ] ); } );
		return fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
			.then( function ( r ) { return r.json(); } );
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s || '';
		return d.innerHTML;
	}

	function downloadBlob( content, filename ) {
		var bom  = '﻿'; // UTF-8 BOM pro Excel
		var blob = new Blob( [ bom + content ], { type: 'text/plain;charset=utf-8' } );
		var url  = URL.createObjectURL( blob );
		var a    = document.createElement( 'a' );
		a.href     = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	}

	// ── Bulk helper – přesměrování ────────────────────────────────────────────

	function getCheckedRedirectIds() {
		return Array.from( redirectsBody.querySelectorAll( '.seob-r-check:checked' ) )
			.map( function ( cb ) { return cb.dataset.id; } );
	}

	function updateRedirectsBulkBar() {
		var ids = getCheckedRedirectIds();
		if ( ids.length > 0 ) {
			redirectsBulkBar.style.display = 'flex';
			redirectsBulkCnt.textContent   = 'Vybráno: ' + ids.length;
		} else {
			redirectsBulkBar.style.display = 'none';
		}
	}

	// ── Bulk helper – 404 ─────────────────────────────────────────────────────

	function getChecked404Ids() {
		return Array.from( notFoundBody.querySelectorAll( '.seob-404-check:checked' ) )
			.map( function ( cb ) { return cb.dataset.id; } );
	}

	function update404BulkBar() {
		var ids = getChecked404Ids();
		if ( ids.length > 0 ) {
			f404BulkBar.style.display = 'block';
			f404BulkCnt.textContent   = 'Vybráno: ' + ids.length;
		} else {
			f404BulkBar.style.display = 'none';
		}
	}

	// ── Renderování tabulky přesměrování ──────────────────────────────────────

	function renderRedirects( redirects ) {
		allRedirects         = redirects;
		redirectsBody.innerHTML = '';

		if ( ! redirects.length ) {
			redirectsBody.innerHTML = '<tr class="seob-empty-row"><td colspan="5">Žádná přesměrování zatím nejsou nastavena.</td></tr>';
			if ( redirectsCheckAll ) redirectsCheckAll.checked = false;
			redirectsBulkBar.style.display = 'none';
			return;
		}

		redirects.forEach( function ( item ) {
			var tr = document.createElement( 'tr' );
			tr.innerHTML =
				'<td><input type="checkbox" class="seob-r-check" data-id="' + esc( item.id ) + '"></td>' +
				'<td>' + esc( item.target_url ) + '</td>' +
				'<td>' + esc( item.redirect_to ) + '</td>' +
				'<td>' + ( item.http_status || 301 ) + '</td>' +
				'<td><button type="button" class="button seob-delete-redirect">Smazat</button></td>';

			tr.querySelector( '.seob-r-check' ).addEventListener( 'change', updateRedirectsBulkBar );

			tr.querySelector( '.seob-delete-redirect' ).addEventListener( 'click', function () {
				if ( ! window.confirm( 'Opravdu smazat toto přesměrování?' ) ) { return; }
				ajax( 'seob_redirect_delete', { id: item.id } ).then( function ( r ) {
					if ( r.success ) { loadList(); }
				} );
			} );

			redirectsBody.appendChild( tr );
		} );

		if ( redirectsCheckAll ) redirectsCheckAll.checked = false;
		redirectsBulkBar.style.display = 'none';
	}

	// ── Renderování tabulky 404 ───────────────────────────────────────────────

	function renderNotFound( items, minHits ) {
		all404s               = items;
		notFoundBody.innerHTML = '';
		minHits               = minHits || 1;

		var visible = items.filter( function ( i ) { return ( +i.hits_404 || 0 ) >= minHits; } );
		var hidden  = items.length - visible.length;

		if ( f404FilterInfo ) {
			f404FilterInfo.textContent = hidden > 0
				? '(skryto ' + hidden + ' záznamů s méně než ' + minHits + ' zásahy)'
				: '';
		}

		if ( ! visible.length ) {
			notFoundBody.innerHTML = '<tr class="seob-empty-row"><td colspan="6">' +
				( items.length ? 'Všechny záznamy jsou skryty filtrem (min. ' + minHits + ' zásahů).' : 'Zatím nebyly zaznamenány žádné 404 chyby.' ) +
				'</td></tr>';
			if ( f404CheckAll ) f404CheckAll.checked = false;
			f404BulkBar.style.display = 'none';
			return;
		}

		visible.forEach( function ( item ) {
			var hits   = +item.hits_404 || 0;
			var badge  = hits >= 50 ? ' 🔴' : ( hits >= 10 ? ' 🟡' : '' );
			var tr     = document.createElement( 'tr' );
			tr.innerHTML =
				'<td><input type="checkbox" class="seob-404-check" data-id="' + esc( item.id ) + '"></td>' +
				'<td>' + esc( item.target_url ) + '</td>' +
				'<td>' + hits + badge + '</td>' +
				'<td style="font-size:11px;color:#666">' + esc( item.last_checked || '' ) + '</td>' +
				'<td><input type="text" class="seob-redirect-target" placeholder="/nova-adresa/" value="/" list="seob-common-targets"></td>' +
				'<td>' +
					'<button type="button" class="button button-primary seob-create-from-404" style="font-size:11px;padding:2px 8px;height:auto">Přesměrovat</button> ' +
					'<button type="button" class="button seob-dismiss-404" style="font-size:11px;padding:2px 8px;height:auto">Smazat</button>' +
				'</td>';

			tr.querySelector( '.seob-404-check' ).addEventListener( 'change', update404BulkBar );

			tr.querySelector( '.seob-create-from-404' ).addEventListener( 'click', function () {
				var val = tr.querySelector( '.seob-redirect-target' ).value.trim();
				if ( ! val ) { window.alert( 'Zadejte cílovou adresu.' ); return; }
				ajax( 'seob_redirect_save', { id: item.id, target_url: item.target_url, redirect_to: val } )
					.then( function ( r ) {
						if ( r.success ) { loadList(); }
						else { window.alert( r.data && r.data.message ? r.data.message : 'Chyba.' ); }
					} );
			} );

			tr.querySelector( '.seob-dismiss-404' ).addEventListener( 'click', function () {
				ajax( 'seob_redirect_delete', { id: item.id } ).then( function ( r ) {
					if ( r.success ) { loadList(); }
				} );
			} );

			notFoundBody.appendChild( tr );
		} );

		if ( f404CheckAll ) f404CheckAll.checked = false;
		f404BulkBar.style.display = 'none';
	}

	// ── Načtení dat ───────────────────────────────────────────────────────────

	function loadList() {
		ajax( 'seob_redirect_list', {} ).then( function ( r ) {
			if ( ! r.success ) { return; }
			var minHits = f404MinHits ? ( +f404MinHits.value || 1 ) : 1;
			renderRedirects( r.data.redirects || [] );
			renderNotFound( r.data.not_found || [], minHits );
		} );
	}

	// ── Přidání nového přesměrování ───────────────────────────────────────────

	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () {
			var source = sourceInput.value.trim();
			var target = targetInput.value.trim() || '/';
			if ( ! source ) { addStatus.textContent = 'Vyplňte zdrojovou adresu.'; return; }
			addStatus.textContent = 'Ukládám…';
			ajax( 'seob_redirect_save', { target_url: source, redirect_to: target } ).then( function ( r ) {
				if ( r.success ) {
					addStatus.textContent = 'Uloženo.';
					sourceInput.value = '';
					targetInput.value = '/';
					loadList();
				} else {
					addStatus.textContent = r.data && r.data.message ? r.data.message : 'Chyba.';
				}
			} );
		} );
	}

	// ── Rychlé cíle ──────────────────────────────────────────────────────────

	document.querySelectorAll( '.seob-quick-target' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( targetInput ) { targetInput.value = btn.dataset.url; }
			if ( f404BulkTarget ) { f404BulkTarget.value = btn.dataset.url; }
		} );
	} );

	if ( loadPagesBtn ) {
		loadPagesBtn.addEventListener( 'click', function () {
			loadPagesBtn.disabled    = true;
			loadPagesBtn.textContent = '⏳ Načítám…';
			ajax( 'seob_redirect_get_pages', {} ).then( function ( r ) {
				loadPagesBtn.style.display = 'none';
				if ( ! r.success || ! r.data.length ) { return; }
				var datalist = document.getElementById( 'seob-common-targets' );
				r.data.forEach( function ( page ) {
					// Tlačítko v quick-fill baru
					var btn = document.createElement( 'button' );
					btn.type      = 'button';
					btn.className = 'button seob-quick-target';
					btn.dataset.url = page.url;
					btn.style.cssText = 'font-size:12px;padding:2px 10px;height:auto';
					btn.textContent = page.label.length > 24 ? page.label.substring( 0, 24 ) + '…' : page.label;
					btn.addEventListener( 'click', function () {
						if ( targetInput ) { targetInput.value = page.url; }
						if ( f404BulkTarget ) { f404BulkTarget.value = page.url; }
					} );
					pagesList.appendChild( btn );
					// Přidej i do datalistu pro autocomplete
					if ( datalist && page.url !== '/' ) {
						var opt = document.createElement( 'option' );
						opt.value = page.url;
						opt.label = page.label;
						datalist.appendChild( opt );
					}
				} );
			} ).catch( function () {
				loadPagesBtn.disabled    = false;
				loadPagesBtn.textContent = '↓ Načíst stránky webu';
			} );
		} );
	}

	// ── Export ────────────────────────────────────────────────────────────────

	function doExport( format ) {
		ajax( 'seob_redirect_export', { format: format } ).then( function ( r ) {
			if ( r.success ) {
				downloadBlob( r.data.content, r.data.filename );
			} else {
				window.alert( 'Export selhal.' );
			}
		} );
	}

	if ( exportCsvBtn ) { exportCsvBtn.addEventListener( 'click', function () { doExport( 'csv' ); } ); }
	if ( exportHtaBtn ) { exportHtaBtn.addEventListener( 'click', function () { doExport( 'htaccess' ); } ); }

	// ── Bulk – přesměrování ───────────────────────────────────────────────────

	if ( redirectsCheckAll ) {
		redirectsCheckAll.addEventListener( 'change', function () {
			redirectsBody.querySelectorAll( '.seob-r-check' ).forEach( function ( cb ) {
				cb.checked = redirectsCheckAll.checked;
			} );
			updateRedirectsBulkBar();
		} );
	}

	if ( redirectsBulkDel ) {
		redirectsBulkDel.addEventListener( 'click', function () {
			var ids = getCheckedRedirectIds();
			if ( ! ids.length ) { return; }
			if ( ! window.confirm( 'Smazat ' + ids.length + ' přesměrování?' ) ) { return; }
			var fd = new FormData();
			fd.append( 'action', 'seob_redirect_bulk_delete' );
			fd.append( 'nonce', seobData.nonce );
			ids.forEach( function ( id ) { fd.append( 'ids[]', id ); } );
			fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) { if ( r.success ) { loadList(); } } );
		} );
	}

	// ── Bulk – 404 ────────────────────────────────────────────────────────────

	if ( f404CheckAll ) {
		f404CheckAll.addEventListener( 'change', function () {
			notFoundBody.querySelectorAll( '.seob-404-check' ).forEach( function ( cb ) {
				cb.checked = f404CheckAll.checked;
			} );
			update404BulkBar();
		} );
	}

	if ( f404BulkSave ) {
		f404BulkSave.addEventListener( 'click', function () {
			var ids    = getChecked404Ids();
			var target = f404BulkTarget ? f404BulkTarget.value.trim() : '/';
			if ( ! ids.length ) { return; }
			if ( ! target ) { window.alert( 'Zadejte cílovou adresu.' ); return; }
			var fd = new FormData();
			fd.append( 'action', 'seob_redirect_bulk_save' );
			fd.append( 'nonce', seobData.nonce );
			fd.append( 'redirect_to', target );
			ids.forEach( function ( id ) { fd.append( 'ids[]', id ); } );
			fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) { if ( r.success ) { loadList(); } } );
		} );
	}

	if ( f404BulkDel ) {
		f404BulkDel.addEventListener( 'click', function () {
			var ids = getChecked404Ids();
			if ( ! ids.length ) { return; }
			if ( ! window.confirm( 'Smazat ' + ids.length + ' záznamů 404?' ) ) { return; }
			var fd = new FormData();
			fd.append( 'action', 'seob_redirect_bulk_delete' );
			fd.append( 'nonce', seobData.nonce );
			ids.forEach( function ( id ) { fd.append( 'ids[]', id ); } );
			fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) { if ( r.success ) { loadList(); } } );
		} );
	}

	// ── 404 threshold filtr ───────────────────────────────────────────────────

	function applyFilter() {
		var minHits = f404MinHits ? ( +f404MinHits.value || 1 ) : 1;
		renderNotFound( all404s, minHits );
	}

	if ( f404FilterBtn ) {
		f404FilterBtn.addEventListener( 'click', applyFilter );
	}
	if ( f404MinHits ) {
		f404MinHits.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) { applyFilter(); }
		} );
	}

	// ── Spuštění ──────────────────────────────────────────────────────────────

	loadList();

	// ── CSV Import – 2-krokový flow ───────────────────────────────────────────
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

	previewBtn.addEventListener( 'click', function () {
		if ( ! csvFile || ! csvFile.files || ! csvFile.files[0] ) {
			alert( 'Nejprve vyberte CSV soubor.' ); return;
		}
		previewBtn.disabled    = true;
		previewBtn.textContent = 'Načítám náhled…';
		step2.style.display    = 'none';
		importResult.style.display = 'none';

		var fd = new FormData();
		fd.append( 'action', 'seob_redirect_preview_csv' );
		fd.append( 'nonce', seobData.nonce );
		fd.append( 'csv_file', csvFile.files[0] );

		fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
		.then( function ( r ) { return r.json(); } )
		.then( function ( res ) {
			previewBtn.disabled    = false;
			previewBtn.textContent = 'Náhled párování';

			if ( ! res.success ) {
				importResult.style.display = 'block';
				importResult.innerHTML = '<span style="color:#d63638;">' + ( res.data && res.data.message ? res.data.message : 'Chyba při načítání.' ) + '</span>';
				return;
			}

			var rows  = res.data.rows;
			var total = res.data.total;
			var valid = rows.filter( function ( r ) { return r.valid; } ).length;
			var bad   = rows.filter( function ( r ) { return ! r.valid; } ).length;

			previewSum.innerHTML =
				'Celkem <strong>' + total + '</strong> řádků &nbsp;|&nbsp; ' +
				'<span style="color:#00a32a;">✔ Platných: <strong>' + valid + '</strong></span>' +
				( bad ? ' &nbsp;|&nbsp; <span style="color:#d63638;">✘ Chybných: <strong>' + bad + '</strong></span>' : '' ) +
				( total > 500 ? ' &nbsp;— zobrazeno prvních 500' : '' );

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
			confirmBtn.disabled    = valid === 0;
			importStatus.textContent = '';
			step2.style.display    = 'block';
		} )
		.catch( function () {
			previewBtn.disabled    = false;
			previewBtn.textContent = 'Náhled párování';
			importResult.style.display = 'block';
			importResult.innerHTML = '<span style="color:#d63638;">Chyba sítě.</span>';
		} );
	} );

	confirmBtn && confirmBtn.addEventListener( 'click', function () {
		if ( ! csvFile || ! csvFile.files || ! csvFile.files[0] ) { return; }
		confirmBtn.disabled      = true;
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
			step2.style.display      = 'none';
			importResult.style.display = 'block';

			if ( res.success ) {
				var d         = res.data;
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
			confirmBtn.disabled      = false;
			importStatus.textContent = '';
			importResult.style.display = 'block';
			importResult.innerHTML   = '<span style="color:#d63638;">Chyba sítě.</span>';
		} );
	} );

	cancelBtn && cancelBtn.addEventListener( 'click', function () {
		step2.style.display        = 'none';
		importResult.style.display = 'none';
	} );

}() );
