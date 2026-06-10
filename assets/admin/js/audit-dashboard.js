( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var ISSUE_LABELS = {
		title_missing: 'Chybí SERP title',
		title_too_long: 'Title je příliš dlouhý',
		description_missing: 'Chybí meta description',
		description_too_long: 'Description je příliš dlouhý',
		duplicate_title: 'Duplicitní title (shoduje se s jinou stránkou)',
		duplicate_description: 'Duplicitní meta description',
		h1_missing: 'Chybí nadpis H1',
		h1_duplicate: 'Více než jeden H1',
		heading_hierarchy: 'Přeskočená úroveň nadpisů (např. H2 → H4)',
		missing_alt: 'Obrázky bez alt textu',
		schema_missing: 'Chybí strukturovaná data (schema)',
		noindex_set: 'Stránka je nastavena jako noindex',
		thin_content: 'Málo obsahu (thin content)',
		focus_keyword_missing: 'Není nastaveno klíčové slovo (focus keyword)'
	};

	var SEVERITY_LABELS = {
		critical: 'kritické',
		warning: 'varování',
		recommendation: 'doporučení'
	};

	var TITLE_LIMIT_PX = 580;
	var DESCRIPTION_LIMIT_PX = 920;

	var runBtn        = document.getElementById( 'seob-run-scan' );
	var scanMeta      = document.getElementById( 'seob-scan-meta' );
	var progressWrap  = document.getElementById( 'seob-progress' );
	var progressFill  = document.getElementById( 'seob-progress-fill' );
	var progressText  = document.getElementById( 'seob-progress-text' );
	var summaryEl     = document.getElementById( 'seob-summary' );
	var tbody         = document.getElementById( 'seob-results-body' );
	var rowTemplate   = document.getElementById( 'seob-row-template' );
	var filterSeverity = document.getElementById( 'seob-filter-severity' );
	var filterSearch  = document.getElementById( 'seob-filter-search' );

	var currentRows = [];

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

	function pixelWidth( text ) {
		var canvas = pixelWidth.canvas || ( pixelWidth.canvas = document.createElement( 'canvas' ) );
		var ctx = canvas.getContext( '2d' );
		ctx.font = '14px Arial, sans-serif';
		return ctx.measureText( text || '' ).width;
	}

	function updatePixelMeter( panel, type, text, limit ) {
		var meter = panel.querySelector( '.seob-pixel-' + type );
		var fill  = meter.querySelector( '.seob-pixel-fill' );
		var label = meter.parentElement.querySelector( '.seob-pixel-label' );
		var width = pixelWidth( text );
		var pct   = Math.min( 100, ( width / limit ) * 100 );

		fill.style.width = pct + '%';
		fill.classList.toggle( 'seob-pixel-over', width > limit );
		label.textContent = Math.round( width ) + ' / ' + limit + ' px';
	}

	function updateSerpPreview( panel, title, description ) {
		panel.querySelector( '.seob-serp-title' ).textContent = title || '(bez titulku)';
		panel.querySelector( '.seob-serp-description' ).textContent = description || '(bez popisu)';
	}

	function statusIcon( ok, text ) {
		var span = document.createElement( 'span' );
		span.className = 'seob-status-icon ' + ( ok === true ? 'seob-ok' : ok === false ? 'seob-bad' : 'seob-warn' );
		span.textContent = ok === true ? '✔' : ok === false ? '✖' : '⚠';
		if ( text ) {
			span.appendChild( document.createTextNode( ' ' + text ) );
		}
		return span;
	}

	function findIssue( issues, type ) {
		for ( var i = 0; i < issues.length; i++ ) {
			if ( issues[ i ].type === type ) {
				return issues[ i ];
			}
		}
		return null;
	}

	function renderSummary( summary ) {
		if ( ! summary || ! summary.id ) {
			summaryEl.innerHTML = '';
			scanMeta.textContent = '';
			return;
		}

		var counts = summary.counts || { critical: 0, warning: 0, recommendation: 0 };
		var date   = summary.finished_at || summary.started_at || '';

		scanMeta.textContent = 'Poslední scan: ' + date + ' · ' + summary.urls_total + ' URL';

		summaryEl.innerHTML =
			'<div class="seob-score-overview">' +
				'<div class="seob-score-total">Celkové skóre webu: <strong>' + ( summary.score_avg ?? '–' ) + '/100</strong></div>' +
				'<div class="seob-score-counts">' +
					'<span class="seob-count-critical">● ' + counts.critical + ' kritických</span>' +
					'<span class="seob-count-warning">● ' + counts.warning + ' varování</span>' +
					'<span class="seob-count-recommendation">● ' + counts.recommendation + ' doporučení</span>' +
				'</div>' +
			'</div>';
	}

	function renderRows() {
		var severity = filterSeverity.value;
		var search   = filterSearch.value.trim().toLowerCase();

		tbody.innerHTML = '';

		var filtered = currentRows.filter( function ( row ) {
			if ( severity ) {
				var hasSeverity = row.issues.some( function ( issue ) {
					return issue.severity === severity;
				} );
				if ( ! hasSeverity ) {
					return false;
				}
			}

			if ( search ) {
				var haystack = ( row.url + ' ' + row.title ).toLowerCase();
				if ( haystack.indexOf( search ) === -1 ) {
					return false;
				}
			}

			return true;
		} );

		if ( ! filtered.length ) {
			var emptyRow = document.createElement( 'tr' );
			emptyRow.className = 'seob-empty-row';
			var emptyCell = document.createElement( 'td' );
			emptyCell.colSpan = 8;
			emptyCell.textContent = currentRows.length ? 'Žádné výsledky neodpovídají filtru.' : 'Zatím žádný scan. Spusťte ho tlačítkem výše.';
			emptyRow.appendChild( emptyCell );
			tbody.appendChild( emptyRow );
			return;
		}

		filtered.forEach( function ( row ) {
			tbody.appendChild( buildRow( row ) );
		} );
	}

	function buildRow( row ) {
		var fragment   = rowTemplate.content.cloneNode( true );
		var resultRow  = fragment.querySelector( '.seob-result-row' );
		var editRow    = fragment.querySelector( '.seob-edit-row' );

		var link = resultRow.querySelector( '.seob-row-edit-link' );
		link.textContent = row.url.replace( /^https?:\/\/[^/]+/, '' ) || '/';
		link.href = row.edit_link || row.url;

		var scoreBadge = resultRow.querySelector( '.seob-score-badge' );
		scoreBadge.textContent = row.score;
		scoreBadge.classList.add( row.score >= 80 ? 'seob-score-good' : row.score >= 50 ? 'seob-score-mid' : 'seob-score-bad' );

		var titleIssue = findIssue( row.issues, 'title_missing' ) || findIssue( row.issues, 'title_too_long' );
		resultRow.querySelector( '.seob-col-title' ).appendChild(
			titleIssue
				? statusIcon( titleIssue.severity === 'critical' ? false : null, titleIssue.detail || '' )
				: statusIcon( true )
		);

		var descIssue = findIssue( row.issues, 'description_missing' ) || findIssue( row.issues, 'description_too_long' );
		resultRow.querySelector( '.seob-col-description' ).appendChild(
			descIssue
				? statusIcon( descIssue.severity === 'critical' ? false : null, descIssue.detail || '' )
				: statusIcon( true )
		);

		var h1Issue = findIssue( row.issues, 'h1_missing' ) || findIssue( row.issues, 'h1_duplicate' );
		resultRow.querySelector( '.seob-col-h1' ).appendChild(
			h1Issue
				? statusIcon( h1Issue.severity === 'critical' ? false : null, h1Issue.detail || '' )
				: statusIcon( true )
		);

		var altIssue = findIssue( row.issues, 'missing_alt' );
		resultRow.querySelector( '.seob-col-alt' ).appendChild(
			altIssue ? statusIcon( null, altIssue.detail || '' ) : statusIcon( true )
		);

		var schemaIssue = findIssue( row.issues, 'schema_missing' );
		resultRow.querySelector( '.seob-col-schema' ).appendChild(
			schemaIssue ? statusIcon( false ) : statusIcon( true )
		);

		// --- Edit panel ---
		var panel = editRow.querySelector( '.seob-edit-panel' );
		var titleInput = panel.querySelector( '.seob-input-title' );
		var descInput  = panel.querySelector( '.seob-input-description' );

		titleInput.value = row.title || '';
		descInput.value  = row.description || '';

		updatePixelMeter( panel, 'title', titleInput.value, TITLE_LIMIT_PX );
		updatePixelMeter( panel, 'description', descInput.value, DESCRIPTION_LIMIT_PX );
		updateSerpPreview( panel, titleInput.value, descInput.value );
		panel.querySelector( '.seob-serp-url' ).textContent = row.url;

		titleInput.addEventListener( 'input', function () {
			updatePixelMeter( panel, 'title', titleInput.value, TITLE_LIMIT_PX );
			updateSerpPreview( panel, titleInput.value, descInput.value );
		} );

		descInput.addEventListener( 'input', function () {
			updatePixelMeter( panel, 'description', descInput.value, DESCRIPTION_LIMIT_PX );
			updateSerpPreview( panel, titleInput.value, descInput.value );
		} );

		var issueList = panel.querySelector( '.seob-issue-list' );
		if ( row.issues.length ) {
			var ul = document.createElement( 'ul' );
			row.issues.forEach( function ( issue ) {
				var li = document.createElement( 'li' );
				li.className = 'seob-issue-' + issue.severity;
				var label = ISSUE_LABELS[ issue.type ] || issue.type;
				li.textContent = '[' + ( SEVERITY_LABELS[ issue.severity ] || issue.severity ) + '] ' + label + ( issue.detail ? ' (' + issue.detail + ')' : '' );
				ul.appendChild( li );
			} );
			issueList.appendChild( ul );
		} else {
			issueList.textContent = 'Žádné nálezy. Stránka je v pořádku.';
		}

		resultRow.querySelector( '.seob-toggle-edit' ).addEventListener( 'click', function () {
			editRow.hidden = ! editRow.hidden;
		} );

		panel.querySelector( '.seob-cancel-edit' ).addEventListener( 'click', function () {
			editRow.hidden = true;
		} );

		panel.querySelector( '.seob-save-meta' ).addEventListener( 'click', function () {
			var statusEl = panel.querySelector( '.seob-save-status' );
			statusEl.textContent = 'Ukládám…';

			Promise.all( [
				ajax( 'seob_save_meta', { object_id: row.object_id, field: 'title', value: titleInput.value } ),
				ajax( 'seob_save_meta', { object_id: row.object_id, field: 'description', value: descInput.value } )
			] ).then( function ( results ) {
				var ok = results.every( function ( r ) { return r.success; } );
				statusEl.textContent = ok ? 'Uloženo.' : 'Chyba při ukládání.';
				if ( ok ) {
					row.title = titleInput.value;
					row.description = descInput.value;
				}
			} ).catch( function () {
				statusEl.textContent = 'Chyba při ukládání.';
			} );
		} );

		var wrapper = document.createDocumentFragment();
		wrapper.appendChild( resultRow );
		wrapper.appendChild( editRow );

		return wrapper;
	}

	function loadResults( scanId ) {
		ajax( 'seob_scan_results', scanId ? { scan_id: scanId } : {} ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			currentRows = response.data.rows || [];
			renderSummary( response.data.summary );
			renderRows();
		} );
	}

	function setProgress( done, total ) {
		progressWrap.hidden = false;
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressFill.style.width = pct + '%';
		progressText.textContent = done + ' / ' + total + ' URL (' + pct + ' %)';
	}

	function runBatch( scanId ) {
		ajax( 'seob_scan_batch', { scan_id: scanId } ).then( function ( response ) {
			if ( ! response.success ) {
				runBtn.disabled = false;
				return;
			}

			var data = response.data;
			setProgress( data.done, data.total );

			if ( data.finished ) {
				progressWrap.hidden = true;
				runBtn.disabled = false;
				loadResults( scanId );
			} else {
				runBatch( scanId );
			}
		} );
	}

	if ( runBtn ) {
		runBtn.addEventListener( 'click', function () {
			runBtn.disabled = true;
			progressWrap.hidden = false;
			setProgress( 0, 1 );

			ajax( 'seob_scan_start', {} ).then( function ( response ) {
				if ( ! response.success ) {
					runBtn.disabled = false;
					progressWrap.hidden = true;
					return;
				}

				setProgress( 0, response.data.urls_total );
				runBatch( response.data.scan_id );
			} );
		} );
	}

	if ( filterSeverity ) {
		filterSeverity.addEventListener( 'change', renderRows );
	}

	if ( filterSearch ) {
		filterSearch.addEventListener( 'input', renderRows );
	}

	loadResults();
}() );
