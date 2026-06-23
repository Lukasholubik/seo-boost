( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var runBtn              = document.getElementById( 'seob-links-run' );
	var meta                = document.getElementById( 'seob-links-meta' );
	var progressWrap        = document.getElementById( 'seob-links-progress' );
	var progressFill        = document.getElementById( 'seob-links-progress-fill' );
	var progressText        = document.getElementById( 'seob-links-progress-text' );
	var spinner             = document.getElementById( 'seob-links-spinner' );
	var summaryEl           = document.getElementById( 'seob-links-summary' );
	var summaryTotal        = document.getElementById( 'seob-links-summary-total' );
	var summaryOrphans      = document.getElementById( 'seob-links-summary-orphans' );
	var summaryOrphansDelta = document.getElementById( 'seob-links-summary-orphans-delta' );
	var summaryAvg          = document.getElementById( 'seob-links-summary-avg' );
	var summaryAvgDelta     = document.getElementById( 'seob-links-summary-avg-delta' );
	var orphanGroupsEl      = document.getElementById( 'seob-links-orphan-groups' );
	var pageGroupsEl        = document.getElementById( 'seob-links-page-groups' );

	// Bulk bar refs
	var bulkBar          = document.getElementById( 'seob-links-bulk-bar' );
	var bulkCount        = document.getElementById( 'seob-links-bulk-count' );
	var bulkMaxEl        = document.getElementById( 'seob-links-bulk-max' );
	var bulkNofollowEl   = document.getElementById( 'seob-links-bulk-nofollow' );
	var bulkNewWinEl     = document.getElementById( 'seob-links-bulk-newwindow' );
	var bulkBtn          = document.getElementById( 'seob-links-bulk-btn' );
	var bulkClearBtn     = document.getElementById( 'seob-links-bulk-clear' );
	var bulkResultsEl    = document.getElementById( 'seob-links-bulk-results' );
	var selectOrphansBtn = document.getElementById( 'seob-links-select-orphans' );

	if ( ! runBtn || ! orphanGroupsEl || ! pageGroupsEl ) {
		return;
	}

	// ── Bulk výběr a akce ────────────────────────────────────────────────────

	function getCheckedIds() {
		return Array.from( document.querySelectorAll( '.seob-links-row-check:checked' ) )
			.map( function ( cb ) { return parseInt( cb.dataset.id, 10 ); } );
	}

	function updateBulkBar() {
		var ids = getCheckedIds();
		if ( ids.length > 0 ) {
			bulkBar.style.display = '';
			bulkCount.textContent = 'Vybráno: ' + ids.length + ' stránek';
		} else {
			bulkBar.style.display = 'none';
			bulkResultsEl.style.display = 'none';
		}
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s );
		return d.innerHTML;
	}

	if ( bulkBtn ) {
		bulkBtn.addEventListener( 'click', function () {
			var ids = getCheckedIds();
			if ( ! ids.length ) { return; }

			bulkBtn.disabled    = true;
			bulkBtn.textContent = '⏳ Vkládám…';
			bulkResultsEl.style.display = 'none';

			var fd = new FormData();
			fd.append( 'action', 'seob_links_bulk_insert' );
			fd.append( 'nonce', seobData.nonce );
			fd.append( 'max_links', bulkMaxEl ? bulkMaxEl.value : '3' );
			fd.append( 'nofollow',   bulkNofollowEl && bulkNofollowEl.checked ? '1' : '0' );
			fd.append( 'new_window', bulkNewWinEl   && bulkNewWinEl.checked   ? '1' : '0' );
			ids.forEach( function ( id ) { fd.append( 'post_ids[]', id ); } );

			fetch( seobData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( d ) {
					bulkBtn.disabled    = false;
					bulkBtn.textContent = '🔗 Vložit linky do vybraných';

					if ( ! d.success ) {
						bulkResultsEl.innerHTML = '<span style="color:#d63638">✗ ' + esc( d.data && d.data.message ? d.data.message : 'Chyba.' ) + '</span>';
						bulkResultsEl.style.display = '';
						return;
					}

					var html = '<strong>✅ Hotovo: vloženo ' + d.data.total_inserted + ' odkazů do ' + d.data.total_posts + ' stránek</strong><ul style="margin:6px 0 0 16px;padding:0">';
					d.data.results.forEach( function ( r ) {
						var icon = r.error ? '✗' : ( r.inserted > 0 ? '✓' : '—' );
						var color = r.error ? '#d63638' : ( r.inserted > 0 ? '#2d7738' : '#646970' );
						html += '<li style="color:' + color + '">' + icon + ' ' + esc( r.title ) + ': ' +
							( r.error ? esc( r.error ) : r.inserted + ' vloženo, ' + r.skipped + ' přeskočeno' ) + '</li>';
					} );
					html += '</ul>';

					bulkResultsEl.innerHTML    = html;
					bulkResultsEl.style.display = '';

					// Obnovit výsledky po vložení
					setTimeout( function () { loadResults(); }, 500 );
				} )
				.catch( function () {
					bulkBtn.disabled    = false;
					bulkBtn.textContent = '🔗 Vložit linky do vybraných';
					bulkResultsEl.innerHTML = '<span style="color:#d63638">✗ Chyba sítě.</span>';
					bulkResultsEl.style.display = '';
				} );
		} );
	}

	if ( bulkClearBtn ) {
		bulkClearBtn.addEventListener( 'click', function () {
			document.querySelectorAll( '.seob-links-row-check' ).forEach( function ( cb ) { cb.checked = false; } );
			updateBulkBar();
		} );
	}

	if ( selectOrphansBtn ) {
		selectOrphansBtn.addEventListener( 'click', function () {
			document.querySelectorAll( '.seob-links-row-check[data-is-orphan="1"]' ).forEach( function ( cb ) {
				cb.checked = true;
			} );
			updateBulkBar();
		} );
	}


	function makeCheckboxCell( pageId, isOrphan ) {
		var td = document.createElement( 'td' );
		td.style.width = '28px';
		td.style.paddingRight = '4px';
		var cb = document.createElement( 'input' );
		cb.type = 'checkbox';
		cb.className = 'seob-links-row-check';
		cb.dataset.id = pageId;
		cb.dataset.isOrphan = isOrphan ? '1' : '0';
		cb.addEventListener( 'change', updateBulkBar );
		td.appendChild( cb );
		return td;
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

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function setProgress( done, total, busy ) {
		progressWrap.hidden = false;
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressFill.style.width = pct + '%';
		progressFill.classList.toggle( 'is-busy', !! busy );
		spinner.classList.toggle( 'is-active', !! busy );
		progressText.textContent = done + ' / ' + total;
	}

	function formatDelta( delta, lowerIsBetter ) {
		if ( null === delta || undefined === delta || 0 === delta ) {
			return { text: delta === 0 ? '(0)' : '', cls: delta === 0 ? 'is-flat' : '' };
		}

		var improved = lowerIsBetter ? delta < 0 : delta > 0;
		var sign     = delta > 0 ? '+' : '';

		return { text: '(' + sign + delta + ')', cls: improved ? 'is-up' : 'is-down' };
	}

	function applyDelta( el, delta, lowerIsBetter ) {
		var formatted = formatDelta( delta, lowerIsBetter );
		el.textContent = formatted.text;
		el.classList.remove( 'is-up', 'is-down', 'is-flat' );
		if ( formatted.cls ) {
			el.classList.add( formatted.cls );
		}
	}

	function renderSuggestions( cell, suggestions ) {
		cell.innerHTML = '';

		if ( ! suggestions || ! suggestions.length ) {
			cell.textContent = '–';
			return;
		}

		var list = document.createElement( 'ul' );

		suggestions.forEach( function ( suggestion ) {
			var li   = document.createElement( 'li' );
			var link = document.createElement( 'a' );
			link.href      = suggestion.edit_link || '#';
			link.target    = '_blank';
			link.rel       = 'noopener';
			link.textContent = suggestion.title || ( 'ID ' + suggestion.id );
			li.appendChild( link );
			list.appendChild( li );
		} );

		cell.appendChild( list );
	}

	/**
	 * Vytvoří skupinový kontejner s toggle tlačítkem.
	 * Vrátí { groupEl, tbody }.
	 */
	function createGroup( label, countText, colHeaders ) {
		var groupEl  = document.createElement( 'div' );
		groupEl.className = 'seob-audit-group';

		var toggleBtn = document.createElement( 'button' );
		toggleBtn.type      = 'button';
		toggleBtn.className = 'seob-group-toggle is-expanded';
		toggleBtn.innerHTML =
			'<span class="seob-group-arrow">&#9658;</span>' +
			'<span class="seob-group-title">' + escHtml( label ) + '</span>' +
			'<span class="seob-group-count">' + escHtml( countText ) + '</span>';
		groupEl.appendChild( toggleBtn );

		var body = document.createElement( 'div' );
		body.className = 'seob-group-body';

		var table  = document.createElement( 'table' );
		table.className = 'wp-list-table widefat striped seob-audit-table';

		var thead = document.createElement( 'thead' );
		var trHead = document.createElement( 'tr' );
		colHeaders.forEach( function ( h ) {
			var th = document.createElement( 'th' );
			th.textContent = h;
			trHead.appendChild( th );
		} );
		thead.appendChild( trHead );
		table.appendChild( thead );

		var tbody = document.createElement( 'tbody' );
		table.appendChild( tbody );
		body.appendChild( table );
		groupEl.appendChild( body );

		toggleBtn.addEventListener( 'click', function () {
			var exp = toggleBtn.classList.toggle( 'is-expanded' );
			body.hidden = ! exp;
		} );

		return { groupEl: groupEl, tbody: tbody };
	}

	function renderOrphanGroups( groups ) {
		orphanGroupsEl.innerHTML = '';

		if ( ! groups || ! groups.length ) {
			orphanGroupsEl.innerHTML = '<div class="seob-empty-groups">Žádné osamocené stránky.</div>';
			return;
		}

		groups.forEach( function ( group ) {
			var g = createGroup(
				group.label,
				group.items.length + ' osamocených',
				[ '', 'Stránka', 'Stav odchozích odkazů', 'Návrhy na prolinkování (odkázat z)' ]
			);

			if ( ! group.items.length ) {
				var emptyRow = document.createElement( 'tr' );
				emptyRow.innerHTML = '<td colspan="4">Žádné osamocené stránky v této skupině.</td>';
				g.tbody.appendChild( emptyRow );
			} else {
				group.items.forEach( function ( page ) {
					var tr = document.createElement( 'tr' );

					var tdTitle = document.createElement( 'td' );
					var viewLink = document.createElement( 'a' );
					viewLink.href        = page.view_link || '#';
					viewLink.target      = '_blank';
					viewLink.rel         = 'noopener';
					viewLink.textContent = page.title || ( 'ID ' + page.id );
					tdTitle.appendChild( viewLink );

					if ( page.edit_link ) {
						tdTitle.appendChild( document.createTextNode( ' ' ) );
						var editLink = document.createElement( 'a' );
						editLink.href        = page.edit_link;
						editLink.textContent = '(upravit)';
						editLink.style.color = '#646970';
						editLink.style.fontSize = '12px';
						tdTitle.appendChild( editLink );
					}

					var tdSugg = document.createElement( 'td' );
					renderSuggestions( tdSugg, page.suggestions );

					tr.appendChild( makeCheckboxCell( page.id, true ) );
					tr.appendChild( tdTitle );
					tr.appendChild( linkStatusCell( page ) );
					tr.appendChild( tdSugg );
					g.tbody.appendChild( tr );
				} );
			}

			orphanGroupsEl.appendChild( g.groupEl );
		} );

		if ( selectOrphansBtn ) { selectOrphansBtn.style.display = ''; }
	}

	function renderPageGroups( groups ) {
		pageGroupsEl.innerHTML = '';

		if ( ! groups || ! groups.length ) {
			pageGroupsEl.innerHTML = '<div class="seob-empty-groups">Žádná data.</div>';
			return;
		}

		groups.forEach( function ( group ) {
			var orphanPart = group.orphans_in_group > 0
				? ' · ' + group.orphans_in_group + ' osamocených'
				: '';

			var problemCount = group.items.filter( function ( p ) {
				return p.link_status === 'low' || p.link_status === 'high';
			} ).length;
			var countText = group.items.length + ' stránek' + orphanPart;
			if ( problemCount > 0 ) {
				countText += ' · ⚠ ' + problemCount + ' problém';
			}

			var g = createGroup(
				group.label,
				countText,
				[ '', 'Stránka', 'Příchozí', 'Odchozí', 'Stav odkazů' ]
			);

			if ( ! group.items.length ) {
				var emptyRow = document.createElement( 'tr' );
				emptyRow.innerHTML = '<td colspan="5">Žádná data.</td>';
				g.tbody.appendChild( emptyRow );
			} else {
				var statusOrder = { low: 0, high: 1, ok: 2 };
				var sorted = group.items.slice().sort( function ( a, b ) {
					return ( statusOrder[ a.link_status ] || 2 ) - ( statusOrder[ b.link_status ] || 2 );
				} );
				sorted.forEach( function ( page ) {
					var tr = document.createElement( 'tr' );

					var tdTitle = document.createElement( 'td' );
					var viewLink = document.createElement( 'a' );
					viewLink.href        = page.view_link || '#';
					viewLink.target      = '_blank';
					viewLink.rel         = 'noopener';
					viewLink.textContent = page.title || ( 'ID ' + page.id );
					tdTitle.appendChild( viewLink );

					if ( page.edit_link ) {
						tdTitle.appendChild( document.createTextNode( ' ' ) );
						var editLink = document.createElement( 'a' );
						editLink.href        = page.edit_link;
						editLink.textContent = '(upravit)';
						editLink.style.color = '#646970';
						editLink.style.fontSize = '12px';
						tdTitle.appendChild( editLink );
					}

					var tdIn  = document.createElement( 'td' );
					tdIn.textContent = page.inlinks;
					var tdOut = document.createElement( 'td' );
					tdOut.textContent = page.outlinks;

					tr.appendChild( makeCheckboxCell( page.id, false ) );
					tr.appendChild( tdTitle );
					tr.appendChild( tdIn );
					tr.appendChild( tdOut );
					tr.appendChild( linkStatusCell( page ) );
					g.tbody.appendChild( tr );
				} );
			}

			pageGroupsEl.appendChild( g.groupEl );
		} );
	}

	function linkStatusCell( page ) {
		var td = document.createElement( 'td' );
		td.style.cssText = 'font-size:11px;white-space:nowrap';

		if ( ! page.link_status || page.link_status === 'ok' ) {
			td.style.color = '#00a32a';
			td.textContent = '✓ V pořádku';
		} else if ( page.link_status === 'low' ) {
			var missing = ( page.link_min || 1 ) - page.outlinks;
			td.style.color = '#d63638';
			td.textContent = '↑ Chybí ' + missing + ' (min. ' + ( page.link_min || 1 ) + ')';
		} else {
			var excess = page.outlinks - ( page.link_max || 2 );
			td.style.color = '#996800';
			td.textContent = '↓ Přebývá ' + excess + ' (max. ' + ( page.link_max || 2 ) + ')';
		}

		return td;
	}

	function renderResults( result ) {
		if ( ! result ) {
			return;
		}

		summaryEl.hidden = false;
		summaryTotal.textContent   = result.posts_total;
		summaryOrphans.textContent = result.orphans_count;
		summaryAvg.textContent     = result.avg_inlinks;

		applyDelta( summaryOrphansDelta, result.trends ? result.trends.orphans_count : null, true );
		applyDelta( summaryAvgDelta,     result.trends ? result.trends.avg_inlinks   : null, false );

		if ( result.run && result.run.finished_at ) {
			meta.textContent = 'Poslední reindex: ' + result.run.finished_at;
		}

		var healthEl = document.getElementById( 'seob-links-summary-health' );
		if ( healthEl && typeof result.link_health_score !== 'undefined' ) {
			var score = result.link_health_score;
			healthEl.textContent = score + ' %';
			healthEl.style.color = score >= 80 ? '#00a32a' : score >= 50 ? '#996800' : '#d63638';
		}
		var healthDetailEl = document.getElementById( 'seob-links-summary-health-detail' );
		if ( healthDetailEl && typeof result.link_ok_count !== 'undefined' ) {
			healthDetailEl.innerHTML =
				'<span style="color:#00a32a">✓ ' + result.link_ok_count + ' ok</span>' +
				'&ensp;<span style="color:#d63638">↑ ' + result.link_low_count + ' málo</span>' +
				'&ensp;<span style="color:#996800">↓ ' + result.link_high_count + ' příliš</span>';
		}

		renderOrphanGroups( result.orphan_groups );
		renderPageGroups( result.page_groups );
	}

	function loadResults() {
		ajax( 'seob_links_results', {} ).then( function ( response ) {
			if ( response.success ) {
				renderResults( response.data.result );
			}
		} );
	}

	function runBatch( runId, done, total ) {
		setProgress( done, total, true );

		ajax( 'seob_links_batch', { run_id: runId } ).then( function ( response ) {
			if ( ! response.success ) {
				runBtn.disabled     = false;
				progressWrap.hidden = true;
				return;
			}

			var data = response.data;

			if ( data.finished ) {
				setProgress( data.done, data.total, false );
				progressWrap.hidden = true;
				runBtn.disabled     = false;
				loadResults();
			} else {
				runBatch( runId, data.done, data.total );
			}
		} ).catch( function () {
			runBtn.disabled     = false;
			progressWrap.hidden = true;
		} );
	}

	runBtn.addEventListener( 'click', function () {
		runBtn.disabled     = true;
		progressWrap.hidden = false;
		setProgress( 0, 1, true );

		ajax( 'seob_links_start', {} ).then( function ( response ) {
			if ( ! response.success ) {
				runBtn.disabled     = false;
				progressWrap.hidden = true;
				window.alert( response.data && response.data.message ? response.data.message : 'Chyba.' );
				return;
			}

			if ( 0 === response.data.items_total ) {
				runBtn.disabled     = false;
				progressWrap.hidden = true;
				loadResults();
				return;
			}

			runBatch( response.data.run_id, 0, response.data.items_total );
		} );
	} );

	ajax( 'seob_links_active', {} ).then( function ( response ) {
		var activeRun = response.success ? response.data.run : null;

		if ( activeRun ) {
			runBtn.disabled = true;
			runBatch( activeRun.run_id, activeRun.done, activeRun.total );
		}

		loadResults();
	} ).catch( function () {
		loadResults();
	} );
}() );
