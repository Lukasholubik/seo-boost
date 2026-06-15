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

	var SCHEMA_SOURCE_LABELS = {
		override: 'vlastní nastavení',
		category_default: 'podle kategorie',
		post_type_default: 'výchozí pro typ obsahu'
	};

	var SCHEMA_SHORT_LABELS = {
		off: 'Běžná stránka',
		article: 'Článek',
		service: 'Služba',
		product: 'Produkt',
		event: 'Akce',
		course: 'Kurz',
		jobposting: 'Nabídka práce',
		book: 'Kniha',
		music: 'Hudba',
		recipe: 'Recept',
		restaurant: 'Restaurace',
		video: 'Video',
		person: 'Osoba',
		software: 'Aplikace'
	};

	var TITLE_LIMIT_PX = 580;
	var DESCRIPTION_LIMIT_PX = 920;

	var runBtn        = document.getElementById( 'seob-run-scan' );
	var scanMeta      = document.getElementById( 'seob-scan-meta' );
	var progressWrap  = document.getElementById( 'seob-progress' );
	var progressFill  = document.getElementById( 'seob-progress-fill' );
	var progressText  = document.getElementById( 'seob-progress-text' );
	var summaryEl     = document.getElementById( 'seob-summary' );
	var groupsEl      = document.getElementById( 'seob-groups' );
	var groupTemplate = document.getElementById( 'seob-group-template' );
	var rowTemplate   = document.getElementById( 'seob-row-template' );
	var filterSeverity = document.getElementById( 'seob-filter-severity' );
	var filterSearch  = document.getElementById( 'seob-filter-search' );
	var scanHistory   = document.getElementById( 'seob-scan-history' );
	var scanDeleteBtn = document.getElementById( 'seob-scan-delete' );
	var exportPdfLink = document.getElementById( 'seob-export-pdf' );
	var gscNotice     = document.getElementById( 'seob-gsc-notice' );

	var currentRows    = [];
	var currentSummary = null;
	var gscAvailable   = false;
	var expandedGroups = null;

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

	function statusIcon( ok, text, tooltip ) {
		var span = document.createElement( 'span' );
		span.className = 'seob-status-icon ' + ( ok === true ? 'seob-ok' : ok === false ? 'seob-bad' : 'seob-warn' );
		span.textContent = ok === true ? '✔' : ok === false ? '✖' : '⚠';
		if ( text ) {
			span.appendChild( document.createTextNode( ' ' + text ) );
		}
		if ( tooltip ) {
			span.title = tooltip;
		}
		return span;
	}

	function issueTooltip( issue ) {
		if ( ! issue ) {
			return '';
		}
		var label = ISSUE_LABELS[ issue.type ] || issue.type;
		var prefix = '[' + ( SEVERITY_LABELS[ issue.severity ] || issue.severity ) + '] ';
		return prefix + label + ( issue.detail ? ' (' + issue.detail + ')' : '' );
	}

	function findIssue( issues, type ) {
		for ( var i = 0; i < issues.length; i++ ) {
			if ( issues[ i ].type === type ) {
				return issues[ i ];
			}
		}
		return null;
	}

	function scoreDeltaHtml( delta ) {
		if ( null === delta || undefined === delta || 0 === delta ) {
			return '';
		}

		var cls  = delta > 0 ? 'seob-score-delta-up' : 'seob-score-delta-down';
		var sign = delta > 0 ? '▲ +' : '▼ ';

		return '<span class="seob-score-delta ' + cls + '">' + sign + delta + '</span>';
	}

	/**
	 * Vytvoří element s trendem skóre (▲/▼ + delta) a volitelným tooltipem
	 * popisujícím, KTERÉ konkrétní nálezy se zlepšily/zhoršily.
	 *
	 * @return {?Element}
	 */
	function createScoreDeltaEl( delta, tooltip ) {
		if ( null === delta || undefined === delta || 0 === delta ) {
			return null;
		}

		var span = document.createElement( 'span' );
		span.className = 'seob-score-delta ' + ( delta > 0 ? 'seob-score-delta-up' : 'seob-score-delta-down' );
		span.textContent = ( delta > 0 ? '▲ +' : '▼ ' ) + delta;

		if ( tooltip ) {
			span.title = tooltip;
		}

		return span;
	}

	/**
	 * Sestaví text tooltipu pro jednu stránku – co se od minulého scanu
	 * zlepšilo (opraveno) a co zhoršilo (nové nálezy).
	 */
	function rowIssueChangeTooltip( row ) {
		var lines = [];

		if ( row.resolved_issues && row.resolved_issues.length ) {
			lines.push( 'Zlepšeno (opraveno):' );
			row.resolved_issues.forEach( function ( type ) {
				lines.push( '  ✓ ' + ( ISSUE_LABELS[ type ] || type ) );
			} );
		}

		if ( row.new_issues && row.new_issues.length ) {
			if ( lines.length ) {
				lines.push( '' );
			}
			lines.push( 'Zhoršeno (nové nálezy):' );
			row.new_issues.forEach( function ( type ) {
				lines.push( '  ✗ ' + ( ISSUE_LABELS[ type ] || type ) );
			} );
		}

		return lines.join( '\n' );
	}

	/**
	 * Sestaví text tooltipu pro skupinu – souhrn opravených a nových nálezů
	 * (s počty) napříč všemi stránkami skupiny od minulého scanu.
	 */
	function groupIssueChangeTooltip( changes ) {
		if ( ! changes ) {
			return '';
		}

		var lines    = [];
		var resolved = changes.resolved || {};
		var added    = changes['new'] || {};

		var resolvedKeys = Object.keys( resolved );
		var newKeys      = Object.keys( added );

		if ( resolvedKeys.length ) {
			lines.push( 'Zlepšeno (opraveno):' );
			resolvedKeys.forEach( function ( type ) {
				lines.push( '  ✓ ' + resolved[ type ] + '× ' + ( ISSUE_LABELS[ type ] || type ) );
			} );
		}

		if ( newKeys.length ) {
			if ( lines.length ) {
				lines.push( '' );
			}
			lines.push( 'Zhoršeno (nové nálezy):' );
			newKeys.forEach( function ( type ) {
				lines.push( '  ✗ ' + added[ type ] + '× ' + ( ISSUE_LABELS[ type ] || type ) );
			} );
		}

		return lines.join( '\n' );
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

		var resolvedTotal = summary.resolved_total || 0;

		summaryEl.innerHTML =
			'<div class="seob-score-overview">' +
				'<div class="seob-score-total">Celkové skóre webu: <strong>' + ( summary.score_avg ?? '–' ) + '/100</strong>' + scoreDeltaHtml( summary.score_delta ) + '</div>' +
				'<div class="seob-score-counts">' +
					'<span class="seob-count-critical">● ' + counts.critical + ' kritických</span>' +
					'<span class="seob-count-warning">● ' + counts.warning + ' varování</span>' +
					'<span class="seob-count-recommendation">● ' + counts.recommendation + ' doporučení</span>' +
					( resolvedTotal > 0 ? '<span class="seob-count-resolved">✓ ' + resolvedTotal + ' opraveno od minulého scanu</span>' : '' ) +
				'</div>' +
			'</div>';
	}

	function postTypeLabel( type ) {
		return ( seobData.postTypeLabels && seobData.postTypeLabels[ type ] ) || type;
	}

	function pageCountLabel( count ) {
		if ( 1 === count ) {
			return count + ' stránka';
		}

		if ( count >= 2 && count <= 4 ) {
			return count + ' stránky';
		}

		return count + ' stránek';
	}

	function severityCounts( rows ) {
		var counts = { critical: 0, warning: 0, recommendation: 0 };

		rows.forEach( function ( row ) {
			row.issues.forEach( function ( issue ) {
				if ( counts.hasOwnProperty( issue.severity ) ) {
					counts[ issue.severity ]++;
				}
			} );
		} );

		return counts;
	}

	function avgScore( rows ) {
		if ( ! rows.length ) {
			return null;
		}

		var sum = rows.reduce( function ( acc, row ) {
			return acc + row.score;
		}, 0 );

		return Math.round( sum / rows.length );
	}

	function resolvedCount( rows ) {
		return rows.reduce( function ( acc, row ) {
			return acc + ( row.resolved_issues ? row.resolved_issues.length : 0 );
		}, 0 );
	}

	function groupGsc( rows ) {
		var withGsc = rows.filter( function ( row ) {
			return !! row.gsc;
		} );

		if ( ! withGsc.length ) {
			return null;
		}

		var impressions = 0;
		var clicks = 0;
		var positionSum = 0;

		withGsc.forEach( function ( row ) {
			impressions += row.gsc.impressions;
			clicks += row.gsc.clicks;
			positionSum += row.gsc.avg_position;
		} );

		return {
			impressions: impressions,
			clicks: clicks,
			ctr: impressions > 0 ? ( clicks / impressions * 100 ) : 0,
			avg_position: positionSum / withGsc.length
		};
	}

	function buildGroup( type, rows ) {
		var fragment = groupTemplate.content.cloneNode( true );
		var toggle   = fragment.querySelector( '.seob-group-toggle' );
		var body     = fragment.querySelector( '.seob-group-body' );
		var rowsBody = fragment.querySelector( '.seob-group-rows' );

		toggle.querySelector( '.seob-group-title' ).textContent = postTypeLabel( type );
		toggle.querySelector( '.seob-group-count' ).textContent = pageCountLabel( rows.length );

		toggle.querySelector( '.seob-group-score-label' ).textContent = 'SEO skóre';

		var score = avgScore( rows );
		var scoreBadge = toggle.querySelector( '.seob-group-score-badge' );
		scoreBadge.textContent = null === score ? '–' : String( score );
		if ( null !== score ) {
			scoreBadge.classList.add( score >= 80 ? 'seob-score-good' : score >= 50 ? 'seob-score-mid' : 'seob-score-bad' );
		}

		var groupDeltas  = ( currentSummary && currentSummary.group_score_deltas ) || {};
		var groupChanges = ( currentSummary && currentSummary.group_issue_changes ) || {};
		var deltaEl      = createScoreDeltaEl( groupDeltas[ type ], groupIssueChangeTooltip( groupChanges[ type ] ) );
		if ( deltaEl ) {
			scoreBadge.insertAdjacentElement( 'afterend', deltaEl );
		}

		var counts = severityCounts( rows );
		toggle.querySelector( '.seob-group-count-critical' ).textContent = counts.critical ? counts.critical + ' kritických' : '';
		toggle.querySelector( '.seob-group-count-warning' ).textContent = counts.warning ? counts.warning + ' varování' : '';
		toggle.querySelector( '.seob-group-count-recommendation' ).textContent = counts.recommendation ? counts.recommendation + ' doporučení' : '';

		var resolved = resolvedCount( rows );
		toggle.querySelector( '.seob-group-count-resolved' ).textContent = resolved ? '✓ ' + resolved + ' opraveno' : '';

		var gscEl = toggle.querySelector( '.seob-group-gsc' );
		var gsc   = gscAvailable ? groupGsc( rows ) : null;

		if ( gsc ) {
			gscEl.textContent = gsc.impressions + ' zobr. · ' + gsc.clicks + ' kliků · CTR ' + gsc.ctr.toFixed( 1 ).replace( '.', ',' ) + ' % · poz. ' + gsc.avg_position.toFixed( 1 ).replace( '.', ',' );
		} else {
			gscEl.textContent = '';
		}

		rows.forEach( function ( row, index ) {
			rowsBody.appendChild( buildRow( row, index ) );
		} );

		var expanded = !! expandedGroups[ type ];
		body.hidden = ! expanded;
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		toggle.classList.toggle( 'is-expanded', expanded );

		toggle.addEventListener( 'click', function () {
			var nowExpanded = body.hidden;
			body.hidden = ! nowExpanded;
			toggle.setAttribute( 'aria-expanded', nowExpanded ? 'true' : 'false' );
			toggle.classList.toggle( 'is-expanded', nowExpanded );
			expandedGroups[ type ] = nowExpanded;
		} );

		return fragment;
	}

	function renderRows() {
		var severity = filterSeverity.value;
		var search   = filterSearch.value.trim().toLowerCase();

		groupsEl.innerHTML = '';

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
			var empty = document.createElement( 'p' );
			empty.className = 'seob-empty-groups';
			empty.textContent = currentRows.length ? 'Žádné výsledky neodpovídají filtru.' : 'Zatím žádný scan. Spusťte ho tlačítkem výše.';
			groupsEl.appendChild( empty );
			return;
		}

		var groups = {};
		var order  = [];

		filtered.forEach( function ( row ) {
			var type = row.object_type || 'post';

			if ( ! groups[ type ] ) {
				groups[ type ] = [];
				order.push( type );
			}

			groups[ type ].push( row );
		} );

		// Skupiny s nejhorším průměrným skóre první – problém je vidět na první pohled.
		order.sort( function ( a, b ) {
			return avgScore( groups[ a ] ) - avgScore( groups[ b ] );
		} );

		if ( ! expandedGroups ) {
			expandedGroups = {};
			expandedGroups[ order[ 0 ] ] = true;
		}

		order.forEach( function ( type ) {
			groupsEl.appendChild( buildGroup( type, groups[ type ] ) );
		} );

		groupsEl.querySelectorAll( '.seob-audit-table' ).forEach( function ( table ) {
			table.classList.toggle( 'seob-gsc-hidden', ! gscAvailable );
		} );
	}

	function buildRow( row, index ) {
		var fragment   = rowTemplate.content.cloneNode( true );
		var resultRow  = fragment.querySelector( '.seob-result-row' );
		var editRow    = fragment.querySelector( '.seob-edit-row' );

		if ( index % 2 === 1 ) {
			resultRow.classList.add( 'seob-row-alt' );
			editRow.classList.add( 'seob-row-alt' );
		}

		var link = resultRow.querySelector( '.seob-row-edit-link' );
		link.textContent = row.url.replace( /^https?:\/\/[^/]+/, '' ) || '/';
		link.href = row.edit_link || row.url;

		var scoreBadge = resultRow.querySelector( '.seob-score-badge' );
		scoreBadge.textContent = row.score;
		scoreBadge.classList.add( row.score >= 80 ? 'seob-score-good' : row.score >= 50 ? 'seob-score-mid' : 'seob-score-bad' );

		var rowDeltaEl = createScoreDeltaEl( row.score_delta, rowIssueChangeTooltip( row ) );
		if ( rowDeltaEl ) {
			scoreBadge.insertAdjacentElement( 'afterend', rowDeltaEl );
		}

		var titleIssue = findIssue( row.issues, 'title_missing' ) || findIssue( row.issues, 'title_too_long' );
		resultRow.querySelector( '.seob-col-title' ).appendChild(
			titleIssue
				? statusIcon( titleIssue.severity === 'critical' ? false : null, titleIssue.detail || '', issueTooltip( titleIssue ) )
				: statusIcon( true, '', 'V pořádku' )
		);

		var descIssue = findIssue( row.issues, 'description_missing' ) || findIssue( row.issues, 'description_too_long' );
		resultRow.querySelector( '.seob-col-description' ).appendChild(
			descIssue
				? statusIcon( descIssue.severity === 'critical' ? false : null, descIssue.detail || '', issueTooltip( descIssue ) )
				: statusIcon( true, '', 'V pořádku' )
		);

		var h1Issue = findIssue( row.issues, 'h1_missing' ) || findIssue( row.issues, 'h1_duplicate' );
		resultRow.querySelector( '.seob-col-h1' ).appendChild(
			h1Issue
				? statusIcon( h1Issue.severity === 'critical' ? false : null, h1Issue.detail || '', issueTooltip( h1Issue ) )
				: statusIcon( true, '', 'V pořádku' )
		);

		var altIssue = findIssue( row.issues, 'missing_alt' );
		resultRow.querySelector( '.seob-col-alt' ).appendChild(
			altIssue ? statusIcon( null, altIssue.detail || '', issueTooltip( altIssue ) ) : statusIcon( true, '', 'V pořádku' )
		);

		var schemaIssue = findIssue( row.issues, 'schema_missing' );
		var schemaType  = row.schema && row.schema.type ? row.schema.type : 'off';
		var schemaLabel = SCHEMA_SHORT_LABELS[ schemaType ] || schemaType;
		var schemaTooltip = ( seobData.schemaTypes && seobData.schemaTypes[ schemaType ] ) || schemaType;
		resultRow.querySelector( '.seob-col-schema' ).appendChild(
			schemaIssue ? statusIcon( false, '', issueTooltip( schemaIssue ) ) : statusIcon( true, schemaLabel, schemaTooltip )
		);

		var thinIssue = findIssue( row.issues, 'thin_content' );
		resultRow.querySelector( '.seob-col-thin' ).appendChild(
			thinIssue ? statusIcon( null, thinIssue.detail || '', issueTooltip( thinIssue ) ) : statusIcon( true, '', 'V pořádku' )
		);

		var resolvedCell = resultRow.querySelector( '.seob-col-resolved' );
		if ( row.resolved_issues && row.resolved_issues.length ) {
			var resolvedBadge = document.createElement( 'span' );
			resolvedBadge.className = 'seob-resolved-badge';
			resolvedBadge.textContent = '✓ ' + row.resolved_issues.length;
			resolvedBadge.title = 'Od minulého scanu opraveno:\n' + row.resolved_issues.map( function ( type ) {
				return '– ' + ( ISSUE_LABELS[ type ] || type );
			} ).join( '\n' );
			resolvedCell.appendChild( resolvedBadge );
		} else {
			resolvedCell.textContent = '–';
		}

		var gsc = row.gsc || null;
		resultRow.querySelector( '.seob-col-gsc-impressions' ).textContent = gsc ? String( gsc.impressions ) : '–';
		resultRow.querySelector( '.seob-col-gsc-clicks' ).textContent = gsc ? String( gsc.clicks ) : '–';
		resultRow.querySelector( '.seob-col-gsc-ctr' ).textContent = gsc ? gsc.ctr.toFixed( 2 ).replace( '.', ',' ) + ' %' : '–';
		resultRow.querySelector( '.seob-col-gsc-position' ).textContent = gsc ? String( gsc.avg_position ).replace( '.', ',' ) : '–';

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

		var schemaSelect = panel.querySelector( '.seob-input-schema' );
		var schemaSource = panel.querySelector( '.seob-schema-source' );
		var schema       = row.schema || { type: 'off', source: 'post_type_default' };

		schemaSelect.innerHTML = '';
		Object.keys( seobData.schemaTypes || {} ).forEach( function ( key ) {
			var option = document.createElement( 'option' );
			option.value = key;
			option.textContent = seobData.schemaTypes[ key ];
			if ( key === schema.type ) {
				option.selected = true;
			}
			schemaSelect.appendChild( option );
		} );

		schemaSource.textContent = 'override' === schema.source
			? 'Nastaveno ručně pro tuto stránku.'
			: 'Aktuálně použito automaticky (' + ( SCHEMA_SOURCE_LABELS[ schema.source ] || schema.source ) + '). Výběrem a uložením nastavíte vlastní schéma jen pro tuto stránku.';

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

		var aiStatus = panel.querySelector( '.seob-ai-status' );

		if ( seobData.aiQueueActive ) {
			panel.querySelectorAll( '.seob-ai-suggest-btn' ).forEach( function ( btn ) {
				btn.hidden = false;

				btn.addEventListener( 'click', function () {
					btn.disabled = true;
					aiStatus.textContent = 'Generuji návrh…';

					ajax( 'seob_ai_suggest', { object_id: row.object_id, field: btn.dataset.field } ).then( function ( response ) {
						btn.disabled = false;

						if ( response.success ) {
							aiStatus.innerHTML = 'Návrh odeslán do schvalovací fronty: „' + response.data.suggestion.replace( /</g, '&lt;' ) + '“ – <a href="' + seobData.aiQueueUrl + '" target="_blank" rel="noopener">otevřít AI frontu</a>';
						} else {
							aiStatus.textContent = response.data && response.data.message ? response.data.message : 'Chyba.';
						}
					} ).catch( function () {
						btn.disabled = false;
						aiStatus.textContent = 'Chyba při komunikaci s AI.';
					} );
				} );
			} );

			var altIssue2 = findIssue( row.issues, 'missing_alt' );
			var altBtn = panel.querySelector( '.seob-ai-suggest-alt-btn' );

			if ( altIssue2 && altBtn ) {
				altBtn.hidden = false;

				altBtn.addEventListener( 'click', function () {
					altBtn.disabled = true;
					aiStatus.textContent = 'Generuji návrhy alt textů…';

					ajax( 'seob_ai_suggest_alt', { object_id: row.object_id } ).then( function ( response ) {
						altBtn.disabled = false;

						if ( response.success ) {
							aiStatus.innerHTML = 'Navrženo ' + response.data.queued + ' alt textů – <a href="' + seobData.aiQueueUrl + '" target="_blank" rel="noopener">otevřít AI frontu</a>';
						} else {
							aiStatus.textContent = response.data && response.data.message ? response.data.message : 'Chyba.';
						}
					} ).catch( function () {
						altBtn.disabled = false;
						aiStatus.textContent = 'Chyba při komunikaci s AI.';
					} );
				} );
			}
		}

		var gscQueriesWrap  = panel.querySelector( '.seob-gsc-queries' );
		var gscQueriesBody  = panel.querySelector( '.seob-gsc-queries-table tbody' );
		var gscQueriesEmpty = panel.querySelector( '.seob-gsc-queries-empty' );
		var gscQueries      = row.gsc_queries;

		gscQueriesBody.innerHTML = '';

		if ( null === gscQueries ) {
			gscQueriesWrap.hidden = true;
		} else if ( ! gscQueries.length ) {
			gscQueriesWrap.hidden = false;
			gscQueriesBody.parentElement.hidden = true;
			gscQueriesEmpty.textContent = 'Pro tuto stránku nemáme za posledních 28 dní žádná data o klíčových slovech.';
			gscQueriesEmpty.hidden = false;
		} else {
			gscQueriesWrap.hidden = false;
			gscQueriesBody.parentElement.hidden = false;
			gscQueriesEmpty.hidden = true;

			gscQueries.forEach( function ( q ) {
				var tr = document.createElement( 'tr' );

				var queryCell = document.createElement( 'td' );
				queryCell.textContent = q.query;
				tr.appendChild( queryCell );

				var positionCell = document.createElement( 'td' );
				positionCell.textContent = String( q.avg_position ).replace( '.', ',' );
				tr.appendChild( positionCell );

				var clicksCell = document.createElement( 'td' );
				clicksCell.textContent = String( q.clicks );
				tr.appendChild( clicksCell );

				var impressionsCell = document.createElement( 'td' );
				impressionsCell.textContent = String( q.impressions );
				tr.appendChild( impressionsCell );

				gscQueriesBody.appendChild( tr );
			} );
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
				ajax( 'seob_save_meta', { object_id: row.object_id, field: 'description', value: descInput.value } ),
				ajax( 'seob_save_meta', { object_id: row.object_id, field: 'schema', value: schemaSelect.value } )
			] ).then( function ( results ) {
				var ok = results.every( function ( r ) { return r.success; } );
				statusEl.textContent = ok ? 'Uloženo.' : 'Chyba při ukládání.';
				if ( ok ) {
					row.title = titleInput.value;
					row.description = descInput.value;
					row.schema = { type: schemaSelect.value, source: 'override' };
					schemaSource.textContent = 'Nastaveno ručně pro tuto stránku.';
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

			currentRows = ( response.data.rows || [] ).map( function ( row ) {
				row.score = Number( row.score );
				row.score_delta = null === row.score_delta || undefined === row.score_delta ? null : Number( row.score_delta );
				return row;
			} );
			currentSummary = response.data.summary || null;
			gscAvailable   = !! response.data.gsc_available;

			renderSummary( response.data.summary );
			renderRows();

			if ( gscNotice ) {
				gscNotice.hidden = gscAvailable;
			}

			if ( scanHistory && response.data.summary && response.data.summary.id ) {
				scanHistory.value = String( response.data.summary.id );
			}

			if ( exportPdfLink ) {
				if ( response.data.summary && response.data.summary.id ) {
					exportPdfLink.href = seobData.reportUrl + '&scan_id=' + response.data.summary.id;
					exportPdfLink.hidden = false;
				} else {
					exportPdfLink.hidden = true;
				}
			}
		} );
	}

	function formatScanOptionLabel( scan ) {
		var date = scan.finished_at || scan.started_at || '';
		var score = ( null === scan.score_avg || undefined === scan.score_avg ) ? '–' : scan.score_avg;
		return date + ' · skóre ' + score + '/100 (' + scan.urls_total + ' URL)';
	}

	function loadHistory( selectScanId ) {
		if ( ! scanHistory ) {
			return;
		}

		ajax( 'seob_scan_history', {} ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			var scans = response.data.scans || [];

			scanHistory.innerHTML = '';

			if ( ! scans.length ) {
				var option = document.createElement( 'option' );
				option.value = '';
				option.textContent = 'Žádné dokončené scany';
				scanHistory.appendChild( option );
				return;
			}

			scans.forEach( function ( scan ) {
				var option = document.createElement( 'option' );
				option.value = String( scan.id );
				option.textContent = formatScanOptionLabel( scan );
				scanHistory.appendChild( option );
			} );

			scanHistory.value = String( selectScanId || scans[ 0 ].id );
		} );
	}

	if ( scanHistory ) {
		scanHistory.addEventListener( 'change', function () {
			var scanId = parseInt( scanHistory.value, 10 );
			if ( scanId ) {
				loadResults( scanId );
			}
		} );
	}

	if ( scanDeleteBtn ) {
		scanDeleteBtn.addEventListener( 'click', function () {
			var scanId = parseInt( scanHistory && scanHistory.value, 10 );

			if ( ! scanId ) {
				return;
			}

			if ( ! window.confirm( 'Opravdu chcete trvale smazat tento scan a jeho výsledky z historie?' ) ) {
				return;
			}

			scanDeleteBtn.disabled = true;

			ajax( 'seob_scan_delete', { scan_id: scanId } ).then( function ( response ) {
				scanDeleteBtn.disabled = false;

				if ( ! response.success ) {
					return;
				}

				loadHistory();
				loadResults();
			} ).catch( function () {
				scanDeleteBtn.disabled = false;
			} );
		} );
	}

	var scanStartTime = null;

	function formatSeconds( seconds ) {
		seconds = Math.max( 0, Math.round( seconds ) );
		var minutes = Math.floor( seconds / 60 );
		var rest    = seconds % 60;
		return minutes > 0 ? ( minutes + ' min ' + rest + ' s' ) : ( rest + ' s' );
	}

	function setProgress( done, total ) {
		progressWrap.hidden = false;
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressFill.style.width = pct + '%';

		var text = done + ' / ' + total + ' URL (' + pct + ' %)';

		if ( scanStartTime && done > 0 && done < total ) {
			var elapsed   = ( Date.now() - scanStartTime ) / 1000;
			var perItem   = elapsed / done;
			var remaining = perItem * ( total - done );
			text += ' · zbývá cca ' + formatSeconds( remaining );
		}

		progressText.textContent = text;
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
				scanStartTime = null;
				loadResults( scanId );
				loadHistory( scanId );
			} else {
				runBatch( scanId );
			}
		} ).catch( function () {
			runBtn.disabled = false;
			scanStartTime = null;
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

				scanStartTime = Date.now();
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
	loadHistory();
}() );
