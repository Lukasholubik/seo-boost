/* JSON-LD Validátor – admin JS (polling-based scan) */
(function () {
	'use strict';

	const { ajaxUrl, nonce } = window.seobData || {};
	const page = document.getElementById('seob-jld-page');
	if (!page) return;

	// ── Helpers ───────────────────────────────────────────────────────────────

	async function post(action, data) {
		const fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', nonce);
		for (const [k, v] of Object.entries(data || {})) fd.append(k, v);
		const r = await fetch(ajaxUrl, { method: 'POST', body: fd });
		return r.json();
	}

	function escHtml(str) {
		const d = document.createElement('div');
		d.textContent = String(str ?? '');
		return d.innerHTML;
	}

	// ── Progress bar ──────────────────────────────────────────────────────────

	const progressWrap = document.getElementById('seob-jld-progress');
	const bar          = document.getElementById('seob-jld-bar');
	const barPct       = document.getElementById('seob-jld-bar-pct');
	const barLabel     = document.getElementById('seob-jld-progress-label');

	function setProgress(scanned, total) {
		const pct = total > 0 ? Math.round((scanned / total) * 100) : 0;
		if (bar)    { bar.style.width = pct + '%'; }
		if (barPct) { barPct.textContent = pct + '%'; }
		if (barLabel) {
			barLabel.textContent = `${scanned} / ${total} stránek`;
		}
		if (progressWrap) progressWrap.style.display = '';
	}

	function hideProgress() {
		if (progressWrap) progressWrap.style.display = 'none';
	}

	// ── Polling ───────────────────────────────────────────────────────────────

	let pollTimer = null;

	function startPolling() {
		if (pollTimer) return;
		pollTimer = setInterval(poll, 2000);
		poll(); // okamzite prvni check
	}

	function stopPolling() {
		if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
	}

	async function poll() {
		let res;
		try {
			res = await post('seob_json_ld_scan_status');
		} catch {
			return; // sit. chyba – zkusime znovu za 2 s
		}

		if (!res?.success) return;

		const d = res.data;

		if (d.status === 'none' || d.status === 'cancelled') {
			stopPolling();
			hideProgress();
			return;
		}

		if (d.status === 'running') {
			setProgress(d.scanned || 0, d.total || 0);
			return;
		}

		if (d.status === 'done') {
			stopPolling();
			// Reload – pridame scan_id do URL aby se rovnou zobrazil novy scan
			const url = new URL(location.href);
			url.searchParams.set('scan_id', d.scan_id);
			location.href = url.toString();
		}
	}

	// ── Start scan ────────────────────────────────────────────────────────────

	const btnStart = document.getElementById('seob-jld-start');
	const startMsg = document.getElementById('seob-jld-start-msg');

	if (btnStart) {
		btnStart.addEventListener('click', async () => {
			btnStart.disabled = true;
			if (startMsg) startMsg.textContent = 'Spouštím scan…';

			try {
				const res = await post('seob_json_ld_start_scan', { limit: 50 });
				if (!res?.success) {
					if (startMsg) startMsg.textContent = 'Chyba: ' + (res?.data?.message || 'Neznámá chyba');
					btnStart.disabled = false;
					return;
				}

				// Skryt tlacitko, zobrazit progress
				btnStart.style.display = 'none';
				if (startMsg) startMsg.textContent = '';
				setProgress(0, res.data.total || 0);

				// Aktualizovat nadpis
				const heading = page.querySelector('h1');
				if (heading) heading.insertAdjacentHTML('afterend',
					'<p style="color:#2271b1;font-weight:600;">Probiha scan – pokracuje i kdyz prejdete na jinou stranku.' +
					' Skenuje 1 URL kazde 3 sekundy, aby nezatizil server.</p>');

				startPolling();
			} catch (e) {
				if (startMsg) startMsg.textContent = 'Síťová chyba: ' + e.message;
				btnStart.disabled = false;
			}
		});
	}

	// ── Cancel scan ───────────────────────────────────────────────────────────

	const btnCancel = document.getElementById('seob-jld-cancel');
	if (btnCancel) {
		btnCancel.addEventListener('click', async () => {
			btnCancel.disabled = true;
			await post('seob_json_ld_cancel_scan');
			location.reload();
		});
	}

	// ── Auto-start polling if scan was running on page load ───────────────────

	if (page.dataset.running === '1') {
		startPolling();
	}

	// ── Validátor jedné URL ───────────────────────────────────────────────────

	const btnSingle = document.getElementById('seob-jld-scan-single');
	if (btnSingle) {
		btnSingle.addEventListener('click', async () => {
			const urlInput = document.getElementById('seob-jld-single-url');
			const url = urlInput?.value.trim();
			if (!url) return;

			btnSingle.disabled = true;
			btnSingle.textContent = 'Načítám…';

			const resultEl = document.getElementById('seob-jld-single-result');

			try {
				const res = await post('seob_json_ld_scan_url', { url });
				if (resultEl) {
					if (res?.success) {
						resultEl.innerHTML = renderSingleResult(res.data);
					} else {
						resultEl.innerHTML = `<div class="notice notice-error inline"><p>${escHtml(res?.data?.message || 'Neznámá chyba')}</p></div>`;
					}
					resultEl.style.display = '';
				}
			} catch (e) {
				if (resultEl) {
					resultEl.innerHTML = `<div class="notice notice-error inline"><p>Síťová chyba: ${escHtml(e.message)}</p></div>`;
					resultEl.style.display = '';
				}
			} finally {
				btnSingle.disabled = false;
				btnSingle.textContent = 'Validovat';
			}
		});

		document.getElementById('seob-jld-single-url')
			?.addEventListener('keydown', e => { if (e.key === 'Enter') btnSingle.click(); });
	}

	function renderSingleResult(d) {
		if (d.status === 'error') {
			return `<div class="notice notice-error inline"><p>Chyba: ${escHtml(d.error)}</p></div>`;
		}

		let html = `<strong>Schémat: ${d.schema_count}</strong>`;
		if (d.schema_types?.length) {
			html += ` <span style="color:#666;">(${d.schema_types.map(escHtml).join(', ')})</span>`;
		}

		if (d.issues?.length) {
			html += '<ul style="margin:8px 0;padding:0;list-style:none;">';
			d.issues.forEach(i => {
				const isErr = i.severity === 'error';
				const c     = isErr ? '#d63638' : '#dba617';
				const bg    = isErr ? '#fff0f0' : '#fffbee';
				const icon  = isErr ? '&#10060;' : '&#9888;';
				html += `<li style="padding:8px 10px;margin:4px 0;border-radius:4px;border-left:3px solid ${c};background:${bg};">`;
				html += `<strong style="color:${c}">${icon} ${escHtml(i.type)}</strong>: ${escHtml(i.message)}`;
				if (i.fix_hint) {
					// fix_hint pochazi ze serveru (hardcoded PHP) – bezpecne pouzit jako HTML
					html += `<div style="margin-top:5px;font-size:12px;color:#50575e;"><strong>Jak opravit:</strong> ${i.fix_hint}</div>`;
				}
				html += '</li>';
			});
			html += '</ul>';
		} else {
			html += '<p style="color:#00a32a;margin:4px 0;">&#10003; Zadne chyby ani varovani.</p>';
		}

		if (d.duplicates?.length) {
			html += '<p style="margin:4px 0;"><strong>Duplicity:</strong> ';
			html += d.duplicates.map(dup =>
				`<span style="color:${dup.exact ? '#d63638' : '#dba617'}">${dup.exact ? '❌' : '⚠'} ${escHtml(dup.type)} ×${dup.count}</span>`
			).join(', ');
			html += '</p>';
		}

		return html;
	}

})();
