/* global seobData, wp */

(function () {
	'use strict';

	// ── Otevírací doba – checkbox Zavřeno ────────────────────────────────────
	document.querySelectorAll('.seob-hours-closed-cb').forEach(function (cb) {
		cb.addEventListener('change', function () {
			const row = cb.closest('tr');
			row.querySelectorAll('.seob-hours-input').forEach(function (input) {
				input.disabled = cb.checked;
			});
			row.classList.toggle('seob-hours-closed', cb.checked);
		});
	});

	// ── Výběr obrázku z Média ───────────────────────────────────────────────
	let mediaFrame = null;

	const selectBtn  = document.getElementById('seob-ls-image-select');
	const removeBtn  = document.getElementById('seob-ls-image-remove');
	const imageIdEl  = document.getElementById('ls-image-id');
	const imageUrlEl = document.getElementById('ls-image-url');
	const previewEl  = document.getElementById('seob-ls-image-preview');

	if (selectBtn) {
		selectBtn.addEventListener('click', function () {
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: 'Vybrat logo / obrázek firmy',
				button: { text: 'Použít tento obrázek' },
				multiple: false,
			});

			mediaFrame.on('select', function () {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				imageIdEl.value  = attachment.id;
				imageUrlEl.value = attachment.url;
				previewEl.innerHTML = '<img src="' + attachment.url + '" style="max-width:200px;max-height:100px;border:1px solid #ddd;padding:2px;">';
				removeBtn.style.display = '';
			});

			mediaFrame.open();
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function () {
			imageIdEl.value  = '0';
			imageUrlEl.value = '';
			previewEl.innerHTML = '';
			removeBtn.style.display = 'none';
		});
	}

	// ── Helpers ──────────────────────────────────────────────────────────────
	function showStatus(msg, type) {
		const el = document.getElementById('seob-ls-status');
		if (!el) return;
		el.textContent = msg;
		el.style.color = type === 'error' ? '#cc1818' : '#468847';
	}

	function collectFormData() {
		const form = document.getElementById('seob-local-seo-form');
		const data = new FormData(form);
		data.append('action', 'seob_local_seo_save');
		data.append('nonce', seobData.nonce);
		return data;
	}

	function post(action, extra) {
		const data = new URLSearchParams();
		data.set('action', action);
		data.set('nonce', seobData.nonce);
		if (extra) {
			for (const [k, v] of Object.entries(extra)) {
				data.set(k, v);
			}
		}
		return fetch(seobData.ajaxUrl, { method: 'POST', body: data })
			.then(function (r) { return r.json(); });
	}

	function postForm(action) {
		const form    = document.getElementById('seob-local-seo-form');
		const formData = new FormData(form);
		formData.set('action', action);
		formData.set('nonce', seobData.nonce);

		return fetch(seobData.ajaxUrl, { method: 'POST', body: formData })
			.then(function (r) { return r.json(); });
	}

	// ── Uložení ──────────────────────────────────────────────────────────────
	const saveBtn = document.getElementById('seob-ls-save');
	if (saveBtn) {
		saveBtn.addEventListener('click', function () {
			showStatus('Ukládám…', 'info');
			saveBtn.disabled = true;

			postForm('seob_local_seo_save')
				.then(function (res) {
					if (res.success) {
						showStatus('Uloženo.', 'ok');
					} else {
						showStatus('Chyba: ' + (res.data && res.data.message ? res.data.message : 'neznámá chyba'), 'error');
					}
				})
				.catch(function () { showStatus('Síťová chyba.', 'error'); })
				.finally(function () { saveBtn.disabled = false; });
		});
	}

	// ── Náhled JSON-LD ───────────────────────────────────────────────────────
	const previewBtn = document.getElementById('seob-ls-preview');
	const previewBox = document.getElementById('seob-ls-preview-box');
	const previewCode = document.getElementById('seob-ls-preview-code');

	if (previewBtn) {
		previewBtn.addEventListener('click', function () {
			previewBtn.disabled = true;

			post('seob_local_seo_preview')
				.then(function (res) {
					if (res.success) {
						previewCode.textContent = res.data.json;
						previewBox.style.display = '';
						previewBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Chyba při generování náhledu. Nejprve uložte nastavení.');
					}
				})
				.catch(function () { alert('Síťová chyba.'); })
				.finally(function () { previewBtn.disabled = false; });
		});
	}

	// ── NAP scan ─────────────────────────────────────────────────────────────
	const napBtn     = document.getElementById('seob-ls-nap-scan');
	const napResults = document.getElementById('seob-ls-nap-results');
	const napSummary = document.getElementById('seob-ls-nap-summary');
	const napTable   = document.getElementById('seob-ls-nap-table');
	const napBody    = document.getElementById('seob-ls-nap-body');

	if (napBtn) {
		napBtn.addEventListener('click', function () {
			napBtn.disabled   = true;
			napBtn.textContent = 'Skenuji…';
			napResults.style.display = '';
			napSummary.innerHTML = '<p>Probíhá skenování webu…</p>';
			napTable.style.display = 'none';
			napBody.innerHTML = '';

			post('seob_local_seo_nap_scan')
				.then(function (res) {
					if (!res.success) {
						napSummary.innerHTML = '<div class="notice notice-error inline"><p>' + esc(res.data ? res.data.message : 'Chyba scanu') + '</p></div>';
						return;
					}

					const d = res.data;
					const issueClass = d.issue_count > 0 ? 'notice-warning' : 'notice-success';
					const issueMsg   = d.issue_count > 0
						? 'Nalezeno <strong>' + d.issue_count + '</strong> stran s nestandardním formátem telefonu.'
						: 'Žádné problémy s formátem telefonu nebyly nalezeny.';

					napSummary.innerHTML = '<div class="notice ' + issueClass + ' inline"><p>'
						+ 'Prohledáno stran: <strong>' + d.total + '</strong>. ' + issueMsg
						+ '</p></div>';

					if (d.results.length === 0) {
						napTable.style.display = 'none';
						return;
					}

					napBody.innerHTML = '';
					d.results.forEach(function (row) {
						const tr = document.createElement('tr');
						tr.className = row.has_issue ? 'seob-nap-issue' : '';

						const matches = row.matches.map(function (m) {
							const label = { phone: '📞', city: '📍', name: '🏢' }[m.type] || '?';
							const ok    = m.ok ? ' style="color:green"' : ' style="color:#cc1818;font-weight:bold"';
							return '<span' + ok + '>' + label + ' ' + esc(m.value) + (m.ok ? '' : ' ⚠') + '</span>';
						}).join('<br>');

						tr.innerHTML = '<td><strong>' + esc(row.title) + '</strong></td>'
							+ '<td>' + matches + '</td>'
							+ '<td>' + (row.has_issue ? '<span style="color:#cc1818">⚠ Neshodný formát</span>' : '<span style="color:green">✓ OK</span>') + '</td>'
							+ '<td>'
							+ '<a href="' + esc(row.edit_link) + '" target="_blank">Upravit</a>'
							+ ' | <a href="' + esc(row.view_link) + '" target="_blank">Zobrazit</a>'
							+ '</td>';

						napBody.appendChild(tr);
					});

					napTable.style.display = '';
				})
				.catch(function () { napSummary.innerHTML = '<p>Síťová chyba.</p>'; })
				.finally(function () {
					napBtn.disabled = false;
					napBtn.textContent = 'Spustit NAP scan';
				});
		});
	}

	function esc(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}
})();
