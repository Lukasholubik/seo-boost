<?php
/**
 * Keyword Bold – admin stránka pro plošné zpracování příspěvků.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = get_post_types( [ 'public' => true ], 'objects' );
unset( $post_types['attachment'] );
?>
<div class="wrap seob-wrap">
	<h1>🔡 <?php esc_html_e( 'Zvýraznění klíčových slov', 'seo-boost' ); ?></h1>

	<p style="max-width:700px;color:#50575e">
		<?php esc_html_e( 'Automaticky obalí Focus KW (z Rank Math) tagem <strong> pro SEO signál relevance. Doporučeno: 1 výskyt na příspěvek, pouze primary KW. Zpracování je reverzibilní – undo odstraní pouze naše tagy.', 'seo-boost' ); ?>
	</p>

	<!-- Nastavení -->
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;max-width:760px;margin-bottom:24px">
		<h2 style="margin-top:0"><?php esc_html_e( 'Nastavení', 'seo-boost' ); ?></h2>
		<div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start">

			<label style="font-size:13px">
				<?php esc_html_e( 'Post type:', 'seo-boost' ); ?><br>
				<select id="seob-kwb-post-type" style="margin-top:4px;min-width:180px">
					<?php foreach ( $post_types as $pt ) : ?>
						<option value="<?php echo esc_attr( $pt->name ); ?>">
							<?php echo esc_html( $pt->labels->name . ' (' . $pt->name . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label style="font-size:13px">
				<?php esc_html_e( 'Max. výskytů na příspěvek:', 'seo-boost' ); ?><br>
				<select id="seob-kwb-max" style="margin-top:4px">
					<option value="1" selected>1× (doporučeno pro SEO)</option>
					<option value="2">2×</option>
					<option value="3">3×</option>
				</select>
			</label>

			<label style="font-size:13px">
				<?php esc_html_e( 'Rozsah zpracování:', 'seo-boost' ); ?><br>
				<select id="seob-kwb-filter-mode" style="margin-top:4px;min-width:220px">
					<option value="all">Všechny příspěvky</option>
					<option value="only_new">Jen nové (bez zvýraznění)</option>
				</select>
			</label>

			<label style="font-size:13px;display:flex;align-items:center;gap:6px;margin-top:20px">
				<input type="checkbox" id="seob-kwb-secondary">
				<?php esc_html_e( 'Zahrnout sekundární KW (Rank Math)', 'seo-boost' ); ?>
			</label>

			<label style="font-size:13px;display:flex;align-items:center;gap:6px;margin-top:20px">
				<input type="checkbox" id="seob-kwb-overwrite">
				<?php esc_html_e( 'Přepsat existující zvýraznění', 'seo-boost' ); ?>
			</label>

		</div>
	</div>

	<!-- Akce -->
	<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
		<button type="button" id="seob-kwb-preview-btn" class="button button-large">
			🔍 <?php esc_html_e( 'Náhled (20 příspěvků)', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-kwb-batch-btn" class="button button-primary button-large" disabled>
			✦ <?php esc_html_e( 'Spustit zvýraznění', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-kwb-stop-btn" class="button button-large" style="display:none;color:#b32d2e;border-color:#b32d2e">
			⏹ <?php esc_html_e( 'Zastavit', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-kwb-undo-btn" class="button button-large" style="color:#b32d2e;border-color:#b32d2e">
			↺ <?php esc_html_e( 'Odebrat zvýraznění (vše)', 'seo-boost' ); ?>
		</button>
	</div>
	<div id="seob-kwb-undo-status" style="display:none;margin-bottom:12px;padding:8px 12px;background:#fcf0f1;border-left:3px solid #b32d2e;font-size:13px"></div>

	<!-- Progress -->
	<div id="seob-kwb-progress-wrap" style="display:none;max-width:700px;margin-bottom:16px">
		<div style="background:#f0f0f0;border-radius:4px;height:20px;overflow:hidden">
			<div id="seob-kwb-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s"></div>
		</div>
		<p id="seob-kwb-progress-label" style="margin:4px 0 0;font-size:13px;color:#50575e"></p>
	</div>

	<!-- Náhled tabulka -->
	<div id="seob-kwb-preview-section" style="display:none;max-width:960px">
		<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
			<h2 style="margin:0"><?php esc_html_e( 'Náhled', 'seo-boost' ); ?></h2>
			<span style="font-size:12px;color:#50575e">Filtr:</span>
			<span class="seob-kwb-tab seob-kwb-tab--active" data-filter="all">Vše</span>
			<span class="seob-kwb-tab" data-filter="not_found">Bez nálezu (0×)</span>
			<span class="seob-kwb-tab" data-filter="waiting">Čeká</span>
			<span class="seob-kwb-tab" data-filter="bolded">Již zvýrazněno</span>
			<span class="seob-kwb-tab" data-filter="no_kw">Bez KW</span>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Příspěvek', 'seo-boost' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Focus KW', 'seo-boost' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Nalezeno', 'seo-boost' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody id="seob-kwb-preview-body"></tbody>
		</table>
		<div style="display:flex;align-items:center;gap:12px;margin-top:8px">
			<p id="seob-kwb-preview-total" style="font-size:13px;color:#50575e;margin:0"></p>
			<button type="button" id="seob-kwb-preview-more" class="button" style="display:none">
				Načíst dalších 20
			</button>
		</div>
	</div>

	<!-- Výsledky -->
	<div id="seob-kwb-results-section" style="display:none;max-width:960px">
		<h2><?php esc_html_e( 'Výsledky', 'seo-boost' ); ?></h2>
		<div id="seob-kwb-summary" style="padding:12px 16px;background:#edfaef;border-left:4px solid #00a32a;border-radius:2px;margin-bottom:12px;display:none"></div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Příspěvek', 'seo-boost' ); ?></th>
					<th style="width:200px"><?php esc_html_e( 'KW', 'seo-boost' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Výskytů', 'seo-boost' ); ?></th>
					<th style="width:180px"><?php esc_html_e( 'Výsledek', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody id="seob-kwb-results-body"></tbody>
		</table>
	</div>

</div>

<style>
.seob-kwb-tab {
	display:inline-block;padding:3px 10px;border-radius:3px;font-size:12px;
	cursor:pointer;background:#f0f0f1;color:#50575e;border:1px solid #dcdcde;
}
.seob-kwb-tab--active { background:#2271b1;color:#fff;border-color:#2271b1; }
.seob-kwb-tab:hover:not(.seob-kwb-tab--active) { background:#e0e0e0; }
#seob-kwb-preview-body tr[data-hidden="1"] { display:none; }
</style>

<script>
( function () {
	'use strict';

	var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce    = '<?php echo esc_js( wp_create_nonce( 'seob_admin_nonce' ) ); ?>';

	var postTypeEl   = document.getElementById( 'seob-kwb-post-type' );
	var maxEl        = document.getElementById( 'seob-kwb-max' );
	var secEl        = document.getElementById( 'seob-kwb-secondary' );
	var overEl       = document.getElementById( 'seob-kwb-overwrite' );
	var filterModeEl = document.getElementById( 'seob-kwb-filter-mode' );
	var prevBtn      = document.getElementById( 'seob-kwb-preview-btn' );
	var prevMoreBtn  = document.getElementById( 'seob-kwb-preview-more' );
	var batchBtn     = document.getElementById( 'seob-kwb-batch-btn' );
	var stopBtn      = document.getElementById( 'seob-kwb-stop-btn' );
	var undoBtn      = document.getElementById( 'seob-kwb-undo-btn' );
	var undoStatus   = document.getElementById( 'seob-kwb-undo-status' );
	var progWrap     = document.getElementById( 'seob-kwb-progress-wrap' );
	var progBar      = document.getElementById( 'seob-kwb-progress-bar' );
	var progLabel    = document.getElementById( 'seob-kwb-progress-label' );
	var prevSec      = document.getElementById( 'seob-kwb-preview-section' );
	var prevBody     = document.getElementById( 'seob-kwb-preview-body' );
	var prevTotal    = document.getElementById( 'seob-kwb-preview-total' );
	var resSec       = document.getElementById( 'seob-kwb-results-section' );
	var resBody      = document.getElementById( 'seob-kwb-results-body' );
	var resSummary   = document.getElementById( 'seob-kwb-summary' );

	var totalPosts    = 0;
	var previewOffset = 0;
	var stopFlag      = false;
	var activeFilter  = 'all';

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s );
		return d.innerHTML;
	}

	function getOpts( extra ) {
		var base = {
			nonce:           nonce,
			post_type:       postTypeEl.value,
			max_occurrences: maxEl.value,
			use_secondary:   secEl.checked ? '1' : '',
			overwrite:       overEl.checked ? '1' : '',
			only_new:        filterModeEl.value === 'only_new' ? '1' : '',
		};
		return Object.assign( base, extra || {} );
	}

	function postForm( action, opts ) {
		var fd = new FormData();
		fd.append( 'action', action );
		Object.keys( opts ).forEach( function ( k ) { fd.append( k, opts[ k ] ); } );
		return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) { return r.json(); } );
	}

	// ── Filtr tabulky náhledu ─────────────────────────────────────────────────

	document.querySelectorAll( '.seob-kwb-tab' ).forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			document.querySelectorAll( '.seob-kwb-tab' ).forEach( function ( t ) {
				t.classList.remove( 'seob-kwb-tab--active' );
			} );
			tab.classList.add( 'seob-kwb-tab--active' );
			activeFilter = tab.dataset.filter;
			applyPreviewFilter();
		} );
	} );

	function rowFilterKey( tr ) {
		return tr.dataset.filterKey || '';
	}

	function applyPreviewFilter() {
		var rows = prevBody.querySelectorAll( 'tr' );
		var visible = 0;
		rows.forEach( function ( tr ) {
			var key = rowFilterKey( tr );
			var show = activeFilter === 'all' || key === activeFilter;
			tr.setAttribute( 'data-hidden', show ? '0' : '1' );
			if ( show ) visible++;
		} );
		updatePreviewCount( visible );
	}

	function updatePreviewCount( visible ) {
		var allRows = prevBody.querySelectorAll( 'tr' ).length;
		var suffix  = activeFilter !== 'all' ? ' (filtrováno: ' + visible + ')' : '';
		prevTotal.textContent = 'Celkem příspěvků k zpracování: ' + totalPosts + suffix;
	}

	// ── Náhled ───────────────────────────────────────────────────────────────

	function loadPreview( append ) {
		prevBtn.disabled    = true;
		prevMoreBtn.style.display = 'none';
		prevBtn.textContent = '⏳ Načítám…';

		postForm( 'seob_kwbold_batch_preview', getOpts( {
			preview_limit:  20,
			preview_offset: previewOffset,
		} ) ).then( function ( d ) {
			prevBtn.disabled    = false;
			prevBtn.textContent = '🔍 Náhled (20 příspěvků)';

			if ( ! d.success ) { alert( 'Chyba při načítání náhledu.' ); return; }

			if ( ! append ) {
				prevBody.innerHTML = '';
			}

			totalPosts    = d.data.total;
			previewOffset = d.data.next_offset;

			d.data.items.forEach( function ( item ) {
				var tr = document.createElement( 'tr' );

				var filterKey;
				if ( ! item.keywords.length ) {
					filterKey = 'no_kw';
				} else if ( item.already_bolded ) {
					filterKey = 'bolded';
				} else if ( item.occurrences === 0 ) {
					filterKey = 'not_found';
				} else {
					filterKey = 'waiting';
				}
				tr.dataset.filterKey = filterKey;

				var kwHtml = item.keywords.length
					? item.keywords.map( function ( k ) { return '<code style="font-size:11px">' + esc( k ) + '</code>'; } ).join( ' ' )
					: '<em style="color:#888">—</em>';

				var foundColor = item.occurrences > 0 ? '#2d7738' : '#d63638';
				var found = '<span style="color:' + foundColor + '">' + ( item.occurrences > 0 ? '✓ ' : '✗ ' ) + item.occurrences + '×</span>';

				var state;
				if ( ! item.keywords.length ) {
					state = '<span style="color:#d63638">bez KW</span>';
				} else if ( item.already_bolded ) {
					state = '<span style="color:#e67e00">již zvýrazněno</span>';
				} else if ( item.occurrences === 0 ) {
					state = '<span style="color:#d63638">nenalezeno</span>';
				} else {
					state = '<span style="color:#50575e">čeká</span>';
				}

				tr.innerHTML =
					'<td><a href="' + esc( item.edit_url ) + '" target="_blank">' + esc( item.title ) + '</a></td>' +
					'<td>' + kwHtml + '</td>' +
					'<td>' + found + '</td>' +
					'<td>' + state + '</td>';
				prevBody.appendChild( tr );
			} );

			prevSec.style.display = '';
			batchBtn.disabled     = false;

			applyPreviewFilter();

			if ( d.data.has_more ) {
				prevMoreBtn.style.display = '';
			}
		} ).catch( function () {
			prevBtn.disabled    = false;
			prevBtn.textContent = '🔍 Náhled (20 příspěvků)';
			alert( 'Chyba sítě.' );
		} );
	}

	prevBtn.addEventListener( 'click', function () {
		previewOffset = 0;
		activeFilter  = 'all';
		document.querySelectorAll( '.seob-kwb-tab' ).forEach( function ( t ) {
			t.classList.toggle( 'seob-kwb-tab--active', t.dataset.filter === 'all' );
		} );
		loadPreview( false );
	} );

	prevMoreBtn.addEventListener( 'click', function () {
		loadPreview( true );
	} );

	// ── Batch ─────────────────────────────────────────────────────────────────

	batchBtn.addEventListener( 'click', function () {
		var modeLabel = filterModeEl.value === 'only_new' ? 'nové (dosud nezpracované)' : 'všechny';
		if ( ! confirm( 'Spustit zvýraznění KW pro ' + modeLabel + ' příspěvky (' + totalPosts + ' celkem)?' ) ) { return; }

		stopFlag = false;
		batchBtn.style.display = 'none';
		prevBtn.disabled       = true;
		stopBtn.style.display  = '';
		progWrap.style.display = '';
		resSec.style.display   = '';
		resBody.innerHTML      = '';
		resSummary.style.display = 'none';

		var offset = 0;
		var totalOk = 0, totalSkip = 0;

		function runBatch() {
			if ( stopFlag ) { finish(); return; }

			postForm( 'seob_kwbold_batch', getOpts( { offset: offset, batch_size: 10 } ) ).then( function ( d ) {
				if ( ! d.success ) { finish(); return; }

				var data = d.data;
				data.results.forEach( function ( r ) {
					var tr = document.createElement( 'tr' );
					var kwHtml = r.keywords.length
						? '<code style="font-size:11px">' + esc( r.keywords[0] ) + '</code>'
						: '<em>—</em>';
					var resHtml = r.success
						? '<span style="color:#2d7738">✓ ' + esc( r.message ) + '</span>'
						: '<span style="color:#888">— ' + esc( r.message ) + '</span>';
					if ( r.success ) { totalOk++; } else { totalSkip++; }
					tr.innerHTML =
						'<td><a href="' + esc( window.location.origin + '/wp-admin/post.php?post=' + r.id + '&action=edit' ) + '" target="_blank">' + esc( r.title ) + '</a></td>' +
						'<td>' + kwHtml + '</td>' +
						'<td>' + ( r.occurrences || 0 ) + '</td>' +
						'<td>' + resHtml + '</td>';
					resBody.appendChild( tr );
				} );

				offset = data.next_offset;
				var pct = totalPosts > 0 ? Math.round( offset / totalPosts * 100 ) : 100;
				progBar.style.width   = Math.min( pct, 100 ) + '%';
				progLabel.textContent = offset + ' / ' + totalPosts + ' zpracováno';

				if ( data.done || stopFlag ) { finish(); }
				else { setTimeout( runBatch, 200 ); }
			} ).catch( finish );
		}

		function finish() {
			batchBtn.style.display = '';
			batchBtn.disabled      = false;
			prevBtn.disabled       = false;
			stopBtn.style.display  = 'none';
			progBar.style.width    = '100%';
			progLabel.textContent  = 'Hotovo: ' + offset + ' zpracováno.';

			resSummary.innerHTML =
				'<strong>✅ Dokončeno</strong><br>' +
				'Zvýrazněno: <strong>' + totalOk + '</strong> &nbsp;|&nbsp; ' +
				'Přeskočeno/chyba: <strong>' + totalSkip + '</strong>';
			resSummary.style.display = '';
		}

		runBatch();
	} );

	stopBtn.addEventListener( 'click', function () { stopFlag = true; } );

	// ── Batch Undo ───────────────────────────────────────────────────────────

	if ( undoBtn ) {
		undoBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Odebrat zvýraznění KW ze VŠECH příspěvků vybraného post type? Tato akce je nevratná.' ) ) { return; }

			undoBtn.disabled    = true;
			undoBtn.textContent = '⏳ Odebírám…';
			if ( undoStatus ) { undoStatus.style.display = 'none'; }

			var undoOffset = 0;
			var totalUndone = 0;

			function runUndo() {
				postForm( 'seob_kwbold_batch_undo', {
					nonce:      nonce,
					post_type:  postTypeEl.value,
					offset:     undoOffset,
					batch_size: 20,
				} ).then( function ( d ) {
					if ( ! d.success ) {
						undoBtn.disabled    = false;
						undoBtn.textContent = '↺ Odebrat zvýraznění (vše)';
						if ( undoStatus ) {
							undoStatus.innerHTML = '✗ Chyba: ' + esc( d.data && d.data.message ? d.data.message : 'neznámá' );
							undoStatus.style.display = '';
						}
						return;
					}
					totalUndone += d.data.undone;
					undoOffset   = d.data.next_offset;

					if ( ! d.data.done ) {
						undoBtn.textContent = '⏳ Odebírám… (' + totalUndone + ')';
						setTimeout( runUndo, 100 );
					} else {
						undoBtn.disabled    = false;
						undoBtn.textContent = '↺ Odebrat zvýraznění (vše)';
						if ( undoStatus ) {
							undoStatus.innerHTML = '✓ Hotovo: odebráno zvýraznění z <strong>' + totalUndone + '</strong> příspěvků.';
							undoStatus.style.background  = '#edfaef';
							undoStatus.style.borderColor = '#00a32a';
							undoStatus.style.display     = '';
						}
					}
				} ).catch( function () {
					undoBtn.disabled    = false;
					undoBtn.textContent = '↺ Odebrat zvýraznění (vše)';
				} );
			}

			runUndo();
		} );
	}

}() );
</script>
