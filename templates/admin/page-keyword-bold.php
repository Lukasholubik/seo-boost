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
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;max-width:700px;margin-bottom:24px">
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
			🔍 <?php esc_html_e( 'Náhled (5 příspěvků)', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-kwb-batch-btn" class="button button-primary button-large" disabled>
			✦ <?php esc_html_e( 'Spustit zvýraznění', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-kwb-stop-btn" class="button button-large" style="display:none;color:#b32d2e;border-color:#b32d2e">
			⏹ <?php esc_html_e( 'Zastavit', 'seo-boost' ); ?>
		</button>
	</div>

	<!-- Progress -->
	<div id="seob-kwb-progress-wrap" style="display:none;max-width:700px;margin-bottom:16px">
		<div style="background:#f0f0f0;border-radius:4px;height:20px;overflow:hidden">
			<div id="seob-kwb-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s"></div>
		</div>
		<p id="seob-kwb-progress-label" style="margin:4px 0 0;font-size:13px;color:#50575e"></p>
	</div>

	<!-- Náhled tabulka -->
	<div id="seob-kwb-preview-section" style="display:none;max-width:900px">
		<h2><?php esc_html_e( 'Náhled', 'seo-boost' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Příspěvek', 'seo-boost' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Focus KW', 'seo-boost' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Nalezeno', 'seo-boost' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody id="seob-kwb-preview-body"></tbody>
		</table>
		<p id="seob-kwb-preview-total" style="font-size:13px;color:#50575e;margin-top:8px"></p>
	</div>

	<!-- Výsledky -->
	<div id="seob-kwb-results-section" style="display:none;max-width:900px">
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

<script>
( function () {
	'use strict';

	var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce    = '<?php echo esc_js( wp_create_nonce( 'seob_admin_nonce' ) ); ?>';

	var postTypeEl = document.getElementById( 'seob-kwb-post-type' );
	var maxEl      = document.getElementById( 'seob-kwb-max' );
	var secEl      = document.getElementById( 'seob-kwb-secondary' );
	var overEl     = document.getElementById( 'seob-kwb-overwrite' );
	var prevBtn    = document.getElementById( 'seob-kwb-preview-btn' );
	var batchBtn   = document.getElementById( 'seob-kwb-batch-btn' );
	var stopBtn    = document.getElementById( 'seob-kwb-stop-btn' );
	var progWrap   = document.getElementById( 'seob-kwb-progress-wrap' );
	var progBar    = document.getElementById( 'seob-kwb-progress-bar' );
	var progLabel  = document.getElementById( 'seob-kwb-progress-label' );
	var prevSec    = document.getElementById( 'seob-kwb-preview-section' );
	var prevBody   = document.getElementById( 'seob-kwb-preview-body' );
	var prevTotal  = document.getElementById( 'seob-kwb-preview-total' );
	var resSec     = document.getElementById( 'seob-kwb-results-section' );
	var resBody    = document.getElementById( 'seob-kwb-results-body' );
	var resSummary = document.getElementById( 'seob-kwb-summary' );

	var totalPosts  = 0;
	var stopFlag    = false;

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

	// ── Náhled ───────────────────────────────────────────────────────────────

	prevBtn.addEventListener( 'click', function () {
		prevBtn.disabled = true;
		prevBtn.textContent = '⏳ Načítám…';
		prevBody.innerHTML  = '';
		prevSec.style.display = 'none';

		postForm( 'seob_kwbold_batch_preview', getOpts() ).then( function ( d ) {
			prevBtn.disabled = false;
			prevBtn.textContent = '🔍 Náhled (5 příspěvků)';

			if ( ! d.success ) { alert( 'Chyba při načítání náhledu.' ); return; }

			totalPosts = d.data.total;
			d.data.items.forEach( function ( item ) {
				var tr = document.createElement( 'tr' );
				var kwHtml = item.keywords.length
					? item.keywords.map( function ( k ) { return '<code style="font-size:11px">' + esc( k ) + '</code>'; } ).join( ' ' )
					: '<em style="color:#888">—</em>';
				var found  = item.occurrences > 0
					? '<span style="color:#2d7738">✓ ' + item.occurrences + '×</span>'
					: '<span style="color:#888">0×</span>';
				var state  = item.already_bolded
					? '<span style="color:#e67e00">již zvýrazněno</span>'
					: '<span style="color:#50575e">čeká</span>';

				tr.innerHTML =
					'<td><a href="' + esc( item.edit_url ) + '" target="_blank">' + esc( item.title ) + '</a></td>' +
					'<td>' + kwHtml + '</td>' +
					'<td>' + found + '</td>' +
					'<td>' + state + '</td>';
				prevBody.appendChild( tr );
			} );

			prevTotal.textContent = 'Celkem příspěvků k zpracování: ' + totalPosts;
			prevSec.style.display = '';
			batchBtn.disabled = false;
		} ).catch( function () {
			prevBtn.disabled = false;
			prevBtn.textContent = '🔍 Náhled (5 příspěvků)';
			alert( 'Chyba sítě.' );
		} );
	} );

	// ── Batch ─────────────────────────────────────────────────────────────────

	batchBtn.addEventListener( 'click', function () {
		if ( ! confirm( 'Spustit zvýraznění KW pro všechny ' + totalPosts + ' příspěvků?' ) ) { return; }

		stopFlag = false;
		batchBtn.style.display = 'none';
		prevBtn.disabled       = true;
		stopBtn.style.display  = '';
		progWrap.style.display = '';
		resSec.style.display   = '';
		resBody.innerHTML      = '';
		resSummary.style.display = 'none';

		var offset = 0;
		var totalOk = 0, totalSkip = 0, totalErr = 0;

		function runBatch() {
			if ( stopFlag ) {
				finish();
				return;
			}

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

				if ( data.done || stopFlag ) {
					finish();
				} else {
					setTimeout( runBatch, 200 );
				}
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

}() );
</script>
