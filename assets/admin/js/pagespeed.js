( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var runBtn        = document.getElementById( 'seob-psi-run' );
	var deleteBtn     = document.getElementById( 'seob-psi-delete' );
	var meta          = document.getElementById( 'seob-psi-meta' );
	var historySelect = document.getElementById( 'seob-psi-history' );
	var progressWrap  = document.getElementById( 'seob-psi-progress' );
	var progressFill  = document.getElementById( 'seob-psi-progress-fill' );
	var progressText  = document.getElementById( 'seob-psi-progress-text' );
	var progressStatus = document.getElementById( 'seob-psi-progress-status' );
	var spinner       = document.getElementById( 'seob-psi-spinner' );
	var resultsEl     = document.getElementById( 'seob-psi-results' );
	var overallEl     = document.getElementById( 'seob-psi-overall' );
	var groupTemplate = document.getElementById( 'seob-psi-group-template' );
	var overallTemplate = document.getElementById( 'seob-psi-overall-template' );
	var issueTemplate = document.getElementById( 'seob-psi-issue-template' );
	var sampleTemplate = document.getElementById( 'seob-psi-sample-template' );

	if ( ! runBtn || ! resultsEl || ! groupTemplate ) {
		return;
	}

	var currentRunId = null;
	var currentQueue = [];

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

	function setProgress( done, total, busy ) {
		progressWrap.hidden = false;
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressFill.style.width = pct + '%';
		progressFill.classList.toggle( 'is-busy', !! busy );
		spinner.classList.toggle( 'is-active', !! busy );
		progressText.textContent = done + ' / ' + total;
	}

	function strategyLabel( strategy ) {
		return 'desktop' === strategy ? 'desktop' : 'mobil';
	}

	function setStatus( text ) {
		progressStatus.textContent = text || '';
	}

	function describeItem( item ) {
		if ( ! item ) {
			return '';
		}

		return item.url + ' (' + strategyLabel( item.strategy ) + ')';
	}

	function formatScore( value ) {
		return null === value || undefined === value ? '–' : String( value );
	}

	function formatDelta( delta ) {
		if ( null === delta || undefined === delta ) {
			return { text: '', cls: '' };
		}

		if ( delta > 0 ) {
			return { text: '(+' + delta + ')', cls: 'is-up' };
		}

		if ( delta < 0 ) {
			return { text: '(' + delta + ')', cls: 'is-down' };
		}

		return { text: '(0)', cls: 'is-flat' };
	}

	function applyStrategyScores( strategyEl, row ) {
		[ 'performance_avg', 'accessibility_avg', 'best_practices_avg', 'seo_avg' ].forEach( function ( field ) {
			var scoreEl = strategyEl.querySelector( '.seob-psi-score-' + field );

			if ( scoreEl ) {
				scoreEl.textContent = formatScore( row[ field ] );
			}

			var deltaEl = strategyEl.querySelector( '.seob-psi-delta-' + field );

			if ( deltaEl ) {
				var delta = row.deltas ? row.deltas[ field ] : null;
				var formatted = formatDelta( delta );
				deltaEl.textContent = formatted.text;
				deltaEl.classList.remove( 'is-up', 'is-down', 'is-flat' );
				if ( formatted.cls ) {
					deltaEl.classList.add( formatted.cls );
				}
			}
		} );
	}

	function renderOverall( overall ) {
		if ( ! overallEl ) {
			return;
		}

		overallEl.innerHTML = '';

		if ( ! overall || ! overallTemplate ) {
			return;
		}

		var overallNode = overallTemplate.content.cloneNode( true );
		var overallGroupEl = overallNode.querySelector( '.seob-psi-overall' );

		[ 'mobile', 'desktop' ].forEach( function ( strategy ) {
			var strategyEl = overallGroupEl.querySelector( '.seob-psi-strategy[data-strategy="' + strategy + '"]' );

			if ( strategyEl && overall[ strategy ] ) {
				applyStrategyScores( strategyEl, overall[ strategy ] );
			}
		} );

		overallEl.appendChild( overallNode );
	}

	function renderGroups( groups ) {
		resultsEl.innerHTML = '';

		var objectTypes = Object.keys( groups || {} );

		if ( ! objectTypes.length ) {
			resultsEl.innerHTML = '<p>' + 'Zatím žádné výsledky.' + '</p>';
			return;
		}

		objectTypes.forEach( function ( objectType ) {
			var rows = groups[ objectType ];
			var group = groupTemplate.content.cloneNode( true );
			var groupEl = group.querySelector( '.seob-psi-group' );

			var label = rows.length && rows[ 0 ].object_type_label ? rows[ 0 ].object_type_label : objectType;
			groupEl.querySelector( '.seob-psi-group-title' ).textContent = label;

			rows.forEach( function ( row ) {
				var strategyEl = groupEl.querySelector( '.seob-psi-strategy[data-strategy="' + row.strategy + '"]' );

				if ( ! strategyEl ) {
					return;
				}

				applyStrategyScores( strategyEl, row );
			} );

			var issuesEl = groupEl.querySelector( '.seob-psi-issues' );
			var seenIssues = {};

			rows.forEach( function ( row ) {
				( row.common_issues || [] ).forEach( function ( issue ) {
					if ( seenIssues[ issue.id ] ) {
						seenIssues[ issue.id ].count += issue.count;
						return;
					}

					seenIssues[ issue.id ] = {
						id: issue.id,
						title: issue.title,
						description: issue.description,
						count: issue.count
					};
				} );
			} );

			var issueList = Object.keys( seenIssues ).map( function ( id ) {
				return seenIssues[ id ];
			} ).sort( function ( a, b ) {
				return b.count - a.count;
			} );

			if ( ! issueList.length ) {
				var emptyLi = document.createElement( 'li' );
				emptyLi.textContent = 'Žádné výrazné SEO nálezy.';
				issuesEl.appendChild( emptyLi );
			} else {
				issueList.forEach( function ( issue ) {
					var issueRow = issueTemplate.content.cloneNode( true );
					issueRow.querySelector( '.seob-psi-issue-title' ).textContent = issue.title || issue.id;
					issueRow.querySelector( '.seob-psi-issue-count' ).textContent = ' (' + issue.count + 'x)';
					issueRow.querySelector( '.seob-psi-issue-description' ).textContent = issue.description || '';
					issuesEl.appendChild( issueRow );
				} );
			}

			var samplesEl = groupEl.querySelector( '.seob-psi-samples' );
			var seenSamples = {};

			rows.forEach( function ( row ) {
				( row.samples || [] ).forEach( function ( sample ) {
					if ( seenSamples[ sample.object_id ] ) {
						return;
					}

					seenSamples[ sample.object_id ] = true;

					var sampleRow = sampleTemplate.content.cloneNode( true );
					var link = sampleRow.querySelector( '.seob-psi-sample-link' );
					link.textContent = sample.title || sample.view_link;
					link.href = sample.view_link || '#';

					var editLink = sampleRow.querySelector( '.seob-psi-sample-edit' );
					editLink.href = sample.edit_link || '#';

					samplesEl.appendChild( sampleRow );
				} );
			} );

			resultsEl.appendChild( group );
		} );
	}

	function formatRunOptionLabel( run ) {
		var date = run.finished_at || run.started_at || '';
		return date + ' · ' + run.items_total + ' položek';
	}

	function loadHistory( selectRunId ) {
		if ( ! historySelect ) {
			return;
		}

		ajax( 'seob_psi_history', {} ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			var runs = response.data.runs || [];

			historySelect.innerHTML = '';

			if ( ! runs.length ) {
				var option = document.createElement( 'option' );
				option.value = '';
				option.textContent = 'Žádné dokončené běhy';
				historySelect.appendChild( option );
				return;
			}

			runs.forEach( function ( run ) {
				var runOption = document.createElement( 'option' );
				runOption.value = String( run.id );
				runOption.textContent = formatRunOptionLabel( run );
				historySelect.appendChild( runOption );
			} );

			historySelect.value = String( selectRunId || runs[ 0 ].id );
		} );
	}

	function loadResults( runId ) {
		ajax( 'seob_psi_results', { run_id: runId || '' } ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			var result = response.data.result;

			if ( ! result ) {
				currentRunId = null;
				meta.textContent = '';
				renderOverall( null );
				renderGroups( {} );
				return;
			}

			currentRunId = result.run.id;
			meta.textContent = 'Analýza: ' + result.run.finished_at;
			renderOverall( result.overall );
			renderGroups( result.groups );

			if ( historySelect ) {
				historySelect.value = String( result.run.id );
			}
		} );
	}

	function runBatch( runId, done, total, lastItem ) {
		var nextItem = currentQueue[ done ];
		var statusParts = [];

		if ( lastItem ) {
			statusParts.push(
				'Hotovo: ' + describeItem( lastItem ) + ( lastItem.error ? ' – chyba' : ' ✓' )
			);
		}

		if ( nextItem ) {
			statusParts.push(
				'Testuji (' + ( done + 1 ) + '/' + total + '): ' + describeItem( nextItem ) + ' … (může trvat 15–40 s)'
			);
		}

		setProgress( done, total, true );
		setStatus( statusParts.join( '  |  ' ) );

		ajax( 'seob_psi_batch', { run_id: runId } ).then( function ( response ) {
			if ( ! response.success ) {
				runBtn.disabled = false;
				progressWrap.hidden = true;
				setStatus( '' );
				return;
			}

			var data = response.data;
			var newLastItem = data.items && data.items.length ? data.items[ data.items.length - 1 ] : null;

			if ( data.finished ) {
				setProgress( data.done, data.total, false );
				setStatus( '' );
				progressWrap.hidden = true;
				runBtn.disabled = false;
				loadHistory( runId );
				loadResults( runId );
			} else {
				runBatch( runId, data.done, data.total, newLastItem );
			}
		} ).catch( function () {
			runBtn.disabled = false;
			progressWrap.hidden = true;
			setStatus( '' );
		} );
	}

	runBtn.addEventListener( 'click', function () {
		runBtn.disabled = true;
		progressWrap.hidden = false;
		currentQueue = [];
		setStatus( 'Spouštím analýzu…' );
		setProgress( 0, 1, true );

		ajax( 'seob_psi_start', {} ).then( function ( response ) {
			if ( ! response.success ) {
				runBtn.disabled = false;
				progressWrap.hidden = true;
				setStatus( '' );
				window.alert( response.data && response.data.message ? response.data.message : 'Chyba.' );
				return;
			}

			if ( 0 === response.data.items_total ) {
				runBtn.disabled = false;
				progressWrap.hidden = true;
				setStatus( '' );
				loadResults();
				return;
			}

			currentQueue = response.data.queue || [];
			runBatch( response.data.run_id, 0, response.data.items_total, null );
		} );
	} );

	if ( historySelect ) {
		historySelect.addEventListener( 'change', function () {
			var runId = parseInt( historySelect.value, 10 );
			if ( runId ) {
				loadResults( runId );
			}
		} );
	}

	if ( deleteBtn ) {
		deleteBtn.addEventListener( 'click', function () {
			var runId = historySelect ? parseInt( historySelect.value, 10 ) : currentRunId;

			if ( ! runId ) {
				return;
			}

			if ( ! window.confirm( 'Opravdu chcete trvale smazat tento běh a jeho výsledky z historie?' ) ) {
				return;
			}

			deleteBtn.disabled = true;

			ajax( 'seob_psi_delete', { run_id: runId } ).then( function () {
				deleteBtn.disabled = false;
				loadHistory();
				loadResults();
			} ).catch( function () {
				deleteBtn.disabled = false;
			} );
		} );
	}

	// Po znovunačtení stránky zkontrolovat, zda neběží analýza na pozadí (WP-Cron) a pokud ano, obnovit progress bar.
	ajax( 'seob_psi_active', {} ).then( function ( response ) {
		var activeRun = response.success ? response.data.run : null;

		if ( activeRun ) {
			runBtn.disabled = true;
			currentQueue = activeRun.queue || [];
			runBatch( activeRun.run_id, activeRun.done, activeRun.total, null );
		}

		loadHistory();
		loadResults();
	} ).catch( function () {
		loadHistory();
		loadResults();
	} );
}() );
