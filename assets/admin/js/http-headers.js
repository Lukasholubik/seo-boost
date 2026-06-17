/* global seobData */
(function ($) {
  'use strict';

  var currentFilter = 'all';
  var currentResults = [];

  // ── AJAX helper ──────────────────────────────────────────────────────────

  function ajax(action, data, ok, fail) {
    $.post(seobData.ajaxUrl, Object.assign({ action: action, nonce: seobData.nonce }, data))
      .done(function (r) { r.success ? ok(r.data) : (fail || showError)(r.data); })
      .fail(function () { showError({ message: 'Chyba serveru – zkuste to znovu.' }); });
  }

  function showError(d) {
    $('#seob-http-error').html('<p>' + escHtml(d.message || 'Neznámá chyba.') + '</p>').show();
    setTimeout(function () { $('#seob-http-error').hide(); }, 8000);
  }

  function showSuccess(msg) {
    $('#seob-http-success').html('<p>' + escHtml(msg) + '</p>').show();
    setTimeout(function () { $('#seob-http-success').hide(); }, 5000);
  }

  // ── Progress ─────────────────────────────────────────────────────────────

  function showProgress(pct, text) {
    $('#seob-http-progress-wrap').show();
    $('#seob-http-progress-bar').css('width', Math.max(3, pct) + '%');
    $('#seob-http-progress-text').text(text || '');
  }

  function hideProgress() {
    $('#seob-http-progress-wrap').fadeOut(500);
  }

  // ── Start scan ───────────────────────────────────────────────────────────

  $('#seob-http-start-btn').on('click', function () {
    $(this).prop('disabled', true).text('Spouštím…');
    $('#seob-http-cancel-btn').show();
    showProgress(2, 'Připravuji seznam URL…');

    ajax('seob_http_headers_start_scan', { limit: 50 }, function (d) {
      showProgress(5, 'Scan spuštěn – ' + d.total + ' URL ve frontě.');
      pollStatus();
    }, function (d) {
      $('#seob-http-start-btn').prop('disabled', false).text('▶ Spustit scan');
      $('#seob-http-cancel-btn').hide();
      hideProgress();
      showError(d);
    });
  });

  // ── Cancel ───────────────────────────────────────────────────────────────

  $('#seob-http-cancel-btn').on('click', function () {
    ajax('seob_http_headers_cancel_scan', {}, function () {
      hideProgress();
      $('#seob-http-start-btn').prop('disabled', false).text('▶ Spustit scan');
      $('#seob-http-cancel-btn').hide();
      showError({ message: 'Scan zrušen.' });
    });
  });

  // ── Polling ───────────────────────────────────────────────────────────────

  var pollTimer = null;

  function pollStatus() {
    ajax('seob_http_headers_scan_status', {}, function (d) {
      if ('none' === d.status || !d.status) {
        finalizeScan(null);
        return;
      }

      if ('running' === d.status) {
        var pct  = d.total > 0 ? Math.round((d.scanned / d.total) * 100) : 0;
        var text = 'Zkontrolováno ' + d.scanned + ' / ' + d.total + ' URL…';
        showProgress(pct, text);
        pollTimer = setTimeout(pollStatus, 2000);
        return;
      }

      // done / cancelled
      finalizeScan(d);
    });
  }

  function finalizeScan(d) {
    clearTimeout(pollTimer);
    hideProgress();
    $('#seob-http-start-btn').prop('disabled', false).text('▶ Spustit scan');
    $('#seob-http-cancel-btn').hide();

    if (d && d.status === 'done') {
      showSuccess('Scan dokončen – ' + d.scanned + ' URL zkontrolováno. (' + d.critical + ' kritické, ' + d.warnings + ' varování)');
      loadHistory(function () {
        var $sel = $('#seob-http-history-select');
        var newest = $sel.find('option').eq(1).val();
        if (newest) loadResults(parseInt(newest, 10));
      });
    }
  }

  // ── Historie ─────────────────────────────────────────────────────────────

  function loadHistory(cb) {
    ajax('seob_http_headers_get_history', {}, function (history) {
      var $sel = $('#seob-http-history-select').empty().append('<option value="">— výsledky skenů —</option>');
      if (history.length) {
        history.forEach(function (h) {
          var dt   = new Date(h.started_at * 1000);
          var label = dt.toLocaleDateString('cs-CZ') + ' ' + dt.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
          $sel.append('<option value="' + h.scan_id + '">' + label + ' (' + h.scanned + ' URL, ' + h.critical + ' krit.)</option>');
        });
        $sel.show();
        $('#seob-http-last-scan').text('Poslední scan: ' + (history[0] ? new Date(history[0].started_at * 1000).toLocaleDateString('cs-CZ') : '—'));
      }
      if (typeof cb === 'function') cb();
    });
  }

  $('#seob-http-history-select').on('change', function () {
    var id = parseInt($(this).val(), 10);
    if (id) loadResults(id);
  });

  // ── Výsledky ─────────────────────────────────────────────────────────────

  function loadResults(scanId) {
    $('#seob-http-table-wrap').html('<p style="color:#888">Načítám…</p>');

    ajax('seob_http_headers_get_results', { scan_id: scanId }, function (rows) {
      currentResults = rows;
      renderSummary(rows);
      renderTable(rows);
      $('#seob-http-summary').css('display', 'flex');
      $('#seob-http-filters').show();
    });
  }

  // ── Filtr ─────────────────────────────────────────────────────────────────

  $(document).on('click', '.seob-http-filter', function () {
    currentFilter = $(this).data('filter');
    $('.seob-http-filter').removeClass('button-primary');
    $(this).addClass('button-primary');
    renderTable(currentResults);
  });

  // ── Summary karty ─────────────────────────────────────────────────────────

  function renderSummary(rows) {
    var critical = 0, warnings = 0, ok = 0;
    rows.forEach(function (r) {
      var hasCrit = (r.issues || []).some(function (i) { return i.severity === 'critical'; });
      var hasWarn = (r.issues || []).some(function (i) { return i.severity === 'warning'; });
      if (hasCrit)      critical++;
      else if (hasWarn) warnings++;
      else              ok++;
    });

    $('#seob-http-stat-scanned').text(rows.length);
    $('#seob-http-stat-critical').text(critical);
    $('#seob-http-stat-warnings').text(warnings);
    $('#seob-http-stat-ok').text(ok);
  }

  // ── Tabulka ──────────────────────────────────────────────────────────────

  function renderTable(rows) {
    var filtered = rows.filter(function (r) {
      if (currentFilter === 'all') return true;
      var hasCrit = (r.issues || []).some(function (i) { return i.severity === 'critical'; });
      var hasWarn = (r.issues || []).some(function (i) { return i.severity === 'warning'; });
      if (currentFilter === 'critical') return hasCrit;
      if (currentFilter === 'warning')  return !hasCrit && hasWarn;
      if (currentFilter === 'ok')       return !hasCrit && !hasWarn;
      return true;
    });

    if (!filtered.length) {
      $('#seob-http-table-wrap').html('<p style="color:#888">Žádné výsledky pro zvolený filtr.</p>');
      return;
    }

    var table = $('<table class="wp-list-table widefat fixed striped"></table>');
    table.append(
      '<thead><tr>' +
        '<th>URL</th>' +
        '<th style="width:70px">Status</th>' +
        '<th style="width:80px">Skóre</th>' +
        '<th style="width:220px">Problémy</th>' +
        '<th style="width:80px">Akce</th>' +
      '</tr></thead>'
    );

    var tbody = $('<tbody></tbody>');
    filtered.forEach(function (r) {
      var issues   = r.issues || [];
      var hasCrit  = issues.some(function (i) { return i.severity === 'critical'; });
      var hasWarn  = issues.some(function (i) { return i.severity === 'warning'; });
      var rowClass = hasCrit ? 'seob-error' : (hasWarn ? 'seob-warn' : 'seob-ok');

      var issueHtml = issues.length
        ? issues.map(function (i) {
            var sev     = i.severity;
            var cls     = sev === 'critical' ? 'seob-error' : (sev === 'warning' ? 'seob-warn' : '');
            var anchor  = 'seob-fix-' + escHtml(i.type);
            return '<div class="' + cls + '" style="white-space:nowrap;font-size:12px;">' +
              '• <a class="seob-http-fix-link" href="#' + anchor + '" title="' + escHtml(i.message) + '" style="color:inherit;text-decoration:underline dotted;">' +
              escHtml(i.label) + '</a></div>';
          }).join('')
        : '<span class="seob-ok">&#10003; OK</span>';

      var editLink = r.edit_url
        ? '<a href="' + escHtml(r.edit_url) + '" target="_blank" class="button button-small">Upravit</a>'
        : '';

      var statusCls = r.status_code === 200 ? '' : (r.status_code >= 400 ? 'seob-error' : 'seob-warn');

      var tr = $('<tr></tr>');
      tr.append('<td><code>' + escHtml(trimUrl(r.url)) + '</code>' + (r.error ? '<br><span style="color:#888;font-size:11px;">Chyba: ' + escHtml(r.error) + '</span>' : '') + '</td>');
      tr.append('<td><span class="' + statusCls + '">' + (r.status_code || '—') + '</span></td>');
      tr.append('<td><span class="' + rowClass + '"><strong>' + (r.score !== undefined ? r.score : '—') + '</strong></span></td>');
      tr.append('<td>' + issueHtml + '</td>');
      tr.append('<td>' + editLink + '</td>');
      tbody.append(tr);
    });

    table.append(tbody);
    $('#seob-http-table-wrap').html(table);
  }

  // Proklik na dokumentační sekci
  $(document).on('click', '.seob-http-fix-link', function (e) {
    e.preventDefault();
    var anchor = $(this).attr('href').replace('#', '');
    var target = document.getElementById(anchor);
    if (!target) return;

    var details = document.getElementById('seob-http-fixes');
    if (details) details.open = true;

    setTimeout(function () {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.style.transition = 'box-shadow 0.3s';
      target.style.boxShadow  = '0 0 0 3px #2271b1';
      setTimeout(function () { target.style.boxShadow = ''; }, 2200);
    }, 80);
  });

  // ── Rychlá kontrola jedné URL ─────────────────────────────────────────────

  $('#seob-http-single-btn').on('click', function () {
    var url = $('#seob-http-single-url').val().trim();
    if (!url) { showError({ message: 'Zadejte URL.' }); return; }

    var btn = $(this).prop('disabled', true).text('Kontroluji…');
    $('#seob-http-single-result').html('<p style="color:#888">Načítám…</p>');

    ajax('seob_http_headers_check_url', { url: url }, function (r) {
      btn.prop('disabled', false).text('Zkontrolovat');
      renderSingleResult(r);
    }, function (d) {
      btn.prop('disabled', false).text('Zkontrolovat');
      showError(d);
    });
  });

  function renderSingleResult(r) {
    var wrap = $('#seob-http-single-result');
    var issues = r.issues || [];

    var html = '<div style="margin-top:10px;">';
    html += '<p><strong>Status:</strong> ' + (r.status_code || '—') + ' &nbsp; <strong>Skóre:</strong> ' + (r.score !== undefined ? r.score : '—') + '/100</p>';

    if (r.error) {
      html += '<p class="seob-error">Chyba: ' + escHtml(r.error) + '</p>';
    }

    if (issues.length) {
      html += '<table class="wp-list-table widefat striped" style="margin-top:8px;"><thead><tr><th>Závažnost</th><th>Problém</th><th>Popis</th></tr></thead><tbody>';
      issues.forEach(function (i) {
        var sev = i.severity;
        var cls = sev === 'critical' ? 'seob-error' : (sev === 'warning' ? 'seob-warn' : '');
        var sevLabel = sev === 'critical' ? '&#10060; Kritické' : (sev === 'warning' ? '&#9888; Varování' : 'ℹ Info');
        html += '<tr><td><span class="' + cls + '">' + sevLabel + '</span></td><td>' + escHtml(i.label) + '</td><td>' + escHtml(i.message) + '</td></tr>';
      });
      html += '</tbody></table>';
    } else if (!r.error) {
      html += '<p class="seob-ok">&#10003; Žádné problémy nalezeny.</p>';
    }

    // Přehled hlaviček
    if (r.headers && Object.keys(r.headers).length) {
      html += '<details style="margin-top:10px;"><summary style="cursor:pointer;font-size:13px;color:#555;">Zobrazit všechny HTTP hlavičky (' + Object.keys(r.headers).length + ')</summary>';
      html += '<table class="wp-list-table widefat striped" style="margin-top:6px;font-size:12px;"><tbody>';
      Object.entries(r.headers).forEach(function (kv) {
        html += '<tr><td style="width:200px;font-family:monospace;">' + escHtml(kv[0]) + '</td><td style="font-family:monospace;word-break:break-all;">' + escHtml(kv[1]) + '</td></tr>';
      });
      html += '</tbody></table></details>';
    }

    html += '</div>';
    wrap.html(html);
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function trimUrl(url) {
    try {
      var u = new URL(url);
      return u.pathname + (u.search || '');
    } catch (e) {
      return url;
    }
  }

  // ── Init ─────────────────────────────────────────────────────────────────

  loadHistory(function () {
    var $sel = $('#seob-http-history-select');
    var newest = $sel.find('option').eq(1).val();
    if (newest) loadResults(parseInt(newest, 10));

    // Zkontroluj, zda je aktivní scan
    ajax('seob_http_headers_scan_status', {}, function (d) {
      if (d.status === 'running') {
        $('#seob-http-start-btn').prop('disabled', true).text('Analyzuji…');
        $('#seob-http-cancel-btn').show();
        pollStatus();
      }
    });
  });

}(jQuery));
