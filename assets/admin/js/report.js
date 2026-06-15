( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var loading      = document.getElementById( 'seob-report-loading' );
	var empty        = document.getElementById( 'seob-report-empty' );
	var content      = document.getElementById( 'seob-report-content' );
	var summaryEl    = document.getElementById( 'seob-report-summary' );
	var introEl      = document.getElementById( 'seob-report-intro' );
	var pagesEl      = document.getElementById( 'seob-report-pages' );
	var offerSelect  = document.getElementById( 'seob-report-offer-select' );
	var offerName    = document.getElementById( 'seob-report-offer-name' );
	var offerBody    = document.getElementById( 'seob-report-offer-body' );
	var downloadBtn  = document.getElementById( 'seob-report-download' );
	var statusEl     = document.getElementById( 'seob-report-status' );
	var pageTemplate = document.getElementById( 'seob-report-page-template' );
	var issueTemplate = document.getElementById( 'seob-report-issue-template' );
	var issueSummaryTemplate = document.getElementById( 'seob-report-issue-summary-template' );
	var issueSummaryEl = document.getElementById( 'seob-report-issue-summary' );
	var pagesNoteEl = document.getElementById( 'seob-report-pages-note' );
	var bizVisitsEl = document.getElementById( 'seob-report-biz-visits' );
	var bizConversionEl = document.getElementById( 'seob-report-biz-conversion' );
	var bizValueEl = document.getElementById( 'seob-report-biz-value' );
	var errorEl      = document.getElementById( 'seob-report-error' );
	var progressFill = document.getElementById( 'seob-report-progress-fill' );
	var progressText = document.getElementById( 'seob-report-progress-text' );

	if ( ! content ) {
		return;
	}

	var reportData = null;

	function buildIssue( issue ) {
		var fragment = issueTemplate.content.cloneNode( true );

		fragment.querySelector( '.seob-pdf-issue-title' ).textContent = issue.severity_label + ': ' + issue.label;

		var impactEl  = fragment.querySelector( '.seob-pdf-issue-impact' );
		var benefitEl = fragment.querySelector( '.seob-pdf-issue-benefit' );

		impactEl.textContent  = issue.impact ? 'Dopad, pokud se neřeší: ' + issue.impact : '';
		benefitEl.textContent = issue.benefit ? 'Přínos po opravě: ' + issue.benefit : '';

		if ( ! issue.impact ) {
			impactEl.hidden = true;
		}

		if ( ! issue.benefit ) {
			benefitEl.hidden = true;
		}

		return fragment;
	}

	function buildPage( row ) {
		var fragment = pageTemplate.content.cloneNode( true );

		fragment.querySelector( '.seob-pdf-page-title' ).textContent = ( row.title || row.url ) + ' – ' + row.score + '/100';
		fragment.querySelector( '.seob-pdf-page-url' ).textContent = row.url;

		var issuesEl = fragment.querySelector( '.seob-pdf-issues' );

		row.issues.forEach( function ( issue ) {
			issuesEl.appendChild( buildIssue( issue ) );
		} );

		return fragment;
	}

	function renderSummary( data ) {
		summaryEl.innerHTML = '';

		var items = [
			{ label: 'Průměrné skóre', value: data.scan.score_avg + '/100' },
			{ label: 'Kritické nálezy', value: data.counts.critical },
			{ label: 'Varování', value: data.counts.warning },
			{ label: 'Doporučení', value: data.counts.recommendation }
		];

		items.forEach( function ( item ) {
			var box = document.createElement( 'div' );
			box.className = 'seob-summary-box';

			var value = document.createElement( 'div' );
			value.className = 'seob-summary-value';
			value.textContent = item.value;

			var label = document.createElement( 'div' );
			label.className = 'seob-summary-label';
			label.textContent = item.label;

			box.appendChild( value );
			box.appendChild( label );
			summaryEl.appendChild( box );
		} );
	}

	function buildIssueSummaryGroup( group ) {
		var fragment = issueSummaryTemplate.content.cloneNode( true );

		fragment.querySelector( '.seob-pdf-issue-summary-title' ).textContent =
			group.severity_label + ': ' + group.label + ' (' + group.count + ' stránek)';

		var impactEl  = fragment.querySelector( '.seob-pdf-issue-impact' );
		var benefitEl = fragment.querySelector( '.seob-pdf-issue-benefit' );

		impactEl.textContent  = group.impact ? 'Dopad, pokud se neřeší: ' + group.impact : '';
		benefitEl.textContent = group.benefit ? 'Přínos po opravě: ' + group.benefit : '';

		if ( ! group.impact ) {
			impactEl.hidden = true;
		}

		if ( ! group.benefit ) {
			benefitEl.hidden = true;
		}

		var listEl = fragment.querySelector( '.seob-pdf-issue-summary-pages' );

		group.pages.forEach( function ( page ) {
			var item = document.createElement( 'li' );
			item.textContent = ( page.title || page.url ) + ' – ' + page.url;
			listEl.appendChild( item );
		} );

		return fragment;
	}

	function renderIssueSummary( data ) {
		if ( ! issueSummaryEl ) {
			return;
		}

		issueSummaryEl.innerHTML = '';

		( data.issue_summary || [] ).forEach( function ( group ) {
			issueSummaryEl.appendChild( buildIssueSummaryGroup( group ) );
		} );
	}

	function renderOfferSelect( data ) {
		offerSelect.innerHTML = '';

		Object.keys( data.offer_templates ).forEach( function ( key ) {
			var option = document.createElement( 'option' );
			option.value = key;
			option.textContent = data.offer_templates[ key ].name;

			if ( key === data.offer_suggestion ) {
				option.selected = true;
			}

			offerSelect.appendChild( option );
		} );
	}

	function applyOffer( key ) {
		var offer = reportData.offer_templates[ key ];

		if ( ! offer ) {
			return;
		}

		offerName.value = offer.name;
		offerBody.value = offer.body;
	}

	function render( data ) {
		reportData = data;

		renderSummary( data );

		introEl.value = data.intro_summary;

		pagesEl.innerHTML = '';

		( data.detailed_rows || [] ).forEach( function ( row ) {
			pagesEl.appendChild( buildPage( row ) );
		} );

		if ( pagesNoteEl ) {
			if ( data.remaining_count > 0 ) {
				pagesNoteEl.textContent = 'Zobrazeno ' + data.detailed_rows.length + ' nejhorších stránek z ' +
					data.pages_with_issues_count + ' s nálezy. Dalších ' + data.remaining_count +
					' stránek je shrnuto v přehledu podle typu nálezu níže.';
			} else {
				pagesNoteEl.textContent = '';
			}
		}

		renderIssueSummary( data );

		renderOfferSelect( data );
		applyOffer( data.offer_suggestion );

		loading.hidden = true;
		content.hidden = false;
	}

	offerSelect.addEventListener( 'change', function () {
		applyOffer( offerSelect.value );
	} );

	downloadBtn.addEventListener( 'click', function () {
		var formData = new FormData();
		formData.append( 'action', 'seob_pdf_export' );
		formData.append( 'nonce', seobData.nonce );
		formData.append( 'scan_id', String( reportData.scan.id ) );
		formData.append( 'intro_summary', introEl.value );
		formData.append( 'offer_key', offerSelect.value );
		formData.append( 'offer_name', offerName.value );
		formData.append( 'offer_body', offerBody.value );

		if ( bizVisitsEl && bizVisitsEl.value ) {
			formData.append( 'biz_monthly_visits', bizVisitsEl.value );
		}

		if ( bizConversionEl && bizConversionEl.value ) {
			formData.append( 'biz_conversion_rate', bizConversionEl.value );
		}

		if ( bizValueEl && bizValueEl.value ) {
			formData.append( 'biz_avg_value', bizValueEl.value );
		}

		statusEl.textContent = 'Generuji PDF…';
		downloadBtn.disabled = true;

		fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'export-failed' );
			}

			return response.blob();
		} ).then( function ( blob ) {
			var url = URL.createObjectURL( blob );
			var link = document.createElement( 'a' );

			link.href = url;
			link.download = 'seo-audit-' + reportData.scan.id + '.pdf';
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );

			URL.revokeObjectURL( url );

			statusEl.textContent = 'Hotovo.';
			downloadBtn.disabled = false;
		} ).catch( function () {
			statusEl.textContent = 'Chyba při generování PDF.';
			downloadBtn.disabled = false;
		} );
	} );

	function showError( message ) {
		loading.hidden = true;
		empty.hidden = false;

		if ( errorEl ) {
			errorEl.textContent = message || '';
		}
	}

	var requestData = new FormData();
	requestData.append( 'action', 'seob_pdf_report_data' );
	requestData.append( 'nonce', seobData.nonce );
	requestData.append( 'scan_id', String( seobData.scanId || 0 ) );

	var TIMEOUT_MS = 20000;
	var startTime  = Date.now();

	var controller = new AbortController();
	var timeoutId  = window.setTimeout( function () {
		controller.abort();
	}, TIMEOUT_MS );

	var tickId = window.setInterval( function () {
		var elapsed = Date.now() - startTime;
		var percent = Math.min( 100, Math.round( ( elapsed / TIMEOUT_MS ) * 100 ) );

		if ( progressFill ) {
			progressFill.style.width = percent + '%';
		}

		if ( progressText ) {
			progressText.textContent = Math.round( elapsed / 1000 ) + ' s / ' + Math.round( TIMEOUT_MS / 1000 ) + ' s';
		}
	}, 250 );

	function stopProgress() {
		window.clearInterval( tickId );
	}

	fetch( seobData.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: requestData,
		signal: controller.signal
	} ).then( function ( response ) {
		window.clearTimeout( timeoutId );
		stopProgress();

		if ( ! response.ok ) {
			throw new Error( 'HTTP ' + response.status );
		}

		return response.json();
	} ).then( function ( response ) {
		loading.hidden = true;

		if ( ! response.success ) {
			var message = response.data && response.data.message ? response.data.message : '';
			showError( message );
			return;
		}

		render( response.data );
	} ).catch( function ( error ) {
		window.clearTimeout( timeoutId );
		stopProgress();

		if ( 'AbortError' === error.name ) {
			showError( 'Vypršel časový limit požadavku (20 s). Server na požadavek vůbec neodpověděl – zkontrolujte, zda něco neblokuje admin-ajax.php (např. plugin kontrolující licenci přes internet bez připojení).' );
			return;
		}

		showError( error.message || '' );
	} );
}() );
