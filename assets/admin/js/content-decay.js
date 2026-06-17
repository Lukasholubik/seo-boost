/**
 * Content Decay Monitor – admin JS
 */
/* global jQuery, seobDecayData */
(function ($) {
	'use strict';

	const nonce = () => $('#seob-decay-nonce').val();

	let activeFilter = 'all';
	let lastResults  = [];

	// ── Init ──────────────────────────────────────────────────────────────────

	$(function () {
		loadLastResults();

		$('#seob-decay-scan-btn').on('click', runScan);

		$(document).on('click', '.seob-filter-btn', function () {
			activeFilter = $(this).data('filter');
			$('.seob-filter-btn').removeClass('active');
			$(this).addClass('active');
			renderTable( lastResults );
		});
	});

	// ── AJAX ──────────────────────────────────────────────────────────────────

	function loadLastResults() {
		$.post(ajaxurl, {
			action : 'seob_decay_get_results',
			nonce  : nonce(),
		}).done(function (res) {
			if ( res.success && res.data.has_results ) {
				showResults( res.data );
			}
		});
	}

	function runScan() {
		const $btn = $('#seob-decay-scan-btn');
		const $status = $('#seob-decay-scan-status');

		$btn.prop('disabled', true).text('Scanuji…');
		$status.text('Probíhá analýza – může trvat několik sekund…');

		$.post(ajaxurl, {
			action : 'seob_decay_run_scan',
			nonce  : nonce(),
		}).done(function (res) {
			if ( res.success ) {
				showResults( res.data );
				$status.text('');
			} else {
				$status.text('Chyba: ' + (res.data?.message || 'neznámá chyba'));
			}
		}).fail(function () {
			$status.text('Chyba připojení – zkuste znovu.');
		}).always(function () {
			$btn.prop('disabled', false).text('Spustit scan');
		});
	}

	// ── Render ────────────────────────────────────────────────────────────────

	function showResults( data ) {
		lastResults = data.results || [];

		// Summary karty
		$('#cnt-decaying').text(data.decaying || 0);
		$('#cnt-stale').text(data.stale || 0);
		$('#cnt-aging').text(data.aging || 0);
		$('#cnt-fresh').text(data.fresh || 0);
		$('#seob-decay-scanned-at').text( data.scanned_date ? 'Poslední scan: ' + data.scanned_date : '' );

		$('#seob-decay-summary').show();
		$('#seob-decay-filters').show();
		$('#seob-decay-results-wrap').show();

		renderTable( lastResults );
	}

	function renderTable( rows ) {
		const filtered = activeFilter === 'all'
			? rows
			: rows.filter( r => r.decay_label === activeFilter );

		const $tbody = $('#seob-decay-tbody').empty();

		if ( ! filtered.length ) {
			$tbody.append('<tr><td colspan="8" style="text-align:center;padding:20px;">Žádné výsledky pro zvolený filtr.</td></tr>');
			return;
		}

		filtered.forEach( function (r) {
			const badgeCls = 'badge-' + r.decay_label;
			const labelMap = { decaying: 'Chřadnoucí', stale: 'Stagnující', aging: 'Stárnoucí', fresh: 'Čerstvé' };
			const label    = labelMap[r.decay_label] || r.decay_label;
			const fillCls  = 'fill-' + r.decay_label;

			const ageText = r.days_old >= 365
				? Math.round(r.days_old / 30) + ' měs.'
				: r.days_old + ' dní';

			const clicksCurrent  = r.gsc ? r.gsc.current_clicks : '–';
			const clicksPrevious = r.gsc ? r.gsc.previous_clicks : '–';
			let trendHtml = '–';
			if ( r.gsc && r.gsc.previous_clicks >= 5 ) {
				const drop = (r.gsc.previous_clicks - r.gsc.current_clicks) / r.gsc.previous_clicks;
				const pct  = Math.round(drop * 100);
				if ( pct > 0 ) {
					const cls = pct > 50 ? 'sig-critical' : ( pct > 25 ? 'sig-warning' : 'sig-info' );
					trendHtml = '<span class="' + cls + '">−' + pct + '%</span>';
				} else if ( pct < 0 ) {
					trendHtml = '<span style="color:#2a7d2a">+' + Math.abs(pct) + '%</span>';
				} else {
					trendHtml = '±0%';
				}
			}

			const posText = r.gsc && r.gsc.current_position ? '#' + r.gsc.current_position : '–';

			const signalsHtml = r.signals && r.signals.length
				? '<ul class="seob-signals-list">' + r.signals.map( s => {
					const cls = 'sig-' + (s.severity || 'info');
					return '<li class="' + cls + '" title="' + esc(s.message) + '">▸ ' + esc(s.label) + '</li>';
				}).join('') + '</ul>'
				: '<span style="color:#999">žádné</span>';

			const editHtml = r.edit_url
				? '<a href="' + esc(r.edit_url) + '" class="button button-small" target="_blank">Upravit</a>'
				: '';

			const scoreBar = '<span class="seob-score-bar"><span class="seob-score-fill ' + fillCls + '" style="width:' + r.decay_score + '%"></span></span>' + r.decay_score;

			const titleHtml = r.path
				? '<a href="' + esc( r.path ) + '" target="_blank">' + esc( r.title || r.path ) + '</a>'
				  + '<br><small style="color:#999">' + esc(r.path) + '</small>'
				: esc(r.title || '');

			$tbody.append(
				'<tr data-label="' + r.decay_label + '">' +
				'<td>' + titleHtml + '</td>' +
				'<td><span class="seob-decay-badge ' + badgeCls + '">' + label + '</span><br>' + scoreBar + '</td>' +
				'<td>' + ageText + '</td>' +
				'<td>' + clicksCurrent + '</td>' +
				'<td>' + trendHtml + '</td>' +
				'<td>' + posText + '</td>' +
				'<td>' + signalsHtml + '</td>' +
				'<td>' + editHtml + '</td>' +
				'</tr>'
			);
		});
	}

	function esc( str ) {
		return $('<div>').text( str || '' ).html();
	}

}(jQuery));
