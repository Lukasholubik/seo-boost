/* global seobData */
(function ($) {
  'use strict';

  var currentFilter = 'all';
  var currentPage   = 1;
  var pollTimer     = null;

  function ajax(action, data, ok, fail) {
    $.post(seobData.ajaxUrl, Object.assign({ action: action, nonce: seobData.nonce }, data))
      .done(function (r) { r.success ? ok(r.data) : (fail || showError)(r.data); })
      .fail(function () { showError({ message: 'Chyba spojení.' }); });
  }

  function showError(d) {
    $('#seob-jsgap-error').html('<p>' + (d.message || 'Neznámá chyba.') + '</p>').show();
    setTimeout(function () { $('#seob-jsgap-error').hide(); }, 6000);
  }

  function showSuccess(msg) {
    $('#seob-jsgap-success').html('<p>' + msg + '</p>').show();
    setTimeout(function () { $('#seob-jsgap-success').hide(); }, 5000);
  }

  // ── Progress bar ──────────────────────────────────────────────────────────

  function startPolling() {
    stopPolling();
    showProgress(0, 'Inicializace analýzy…');
    pollTimer = setInterval(pollStatus, 2000);
  }

  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function pollStatus() {
    ajax('seob_jsgap_scan_status', {}, function (d) {
      var label = d.phase;
      if (d.total > 0) {
        label += ' (' + d.analyzed + ' / ' + d.total + ' URL)';
      }
      showProgress(d.percent, label);

      if (!d.running) {
        stopPolling();
        setTimeout(function () {
          $('#seob-jsgap-progress-wrap').fadeOut(400);
          loadStats();
          loadResults();
          if (d.analyzed > 0) {
            showSuccess('Analýza dokončena – zkontrolujte výsledky níže.');
          }
        }, 800);
      }
    });
  }

  function showProgress(percent, text) {
    $('#seob-jsgap-progress-wrap').show();
    $('#seob-jsgap-progress-bar').css('width', percent + '%');
    $('#seob-jsgap-progress-pct').text(percent + ' %');
    $('#seob-jsgap-progress-text').text(text || '');
  }

  // ── Statistiky ────────────────────────────────────────────────────────────

  function loadStats() {
    ajax('seob_jsgap_stats', {}, function (d) {
      $('#seob-jsgap-total-snaps').text(d.total_snaps);
      $('#seob-jsgap-snaps-24h').text(d.snaps_24h);
      $('#seob-jsgap-critical').text(d.critical);
      $('#seob-jsgap-warning').text(d.warning);
      $('#seob-jsgap-ok').text(d.ok);
      $('#seob-jsgap-avg').text(d.avg_score ? parseFloat(d.avg_score).toFixed(1) : '—');
    });
  }

  // ── Spustit analýzu ───────────────────────────────────────────────────────

  $('#seob-jsgap-run-btn').on('click', function () {
    var btn = $(this).prop('disabled', true).text('Plánuji…');
    ajax('seob_jsgap_run_scan', {}, function () {
      btn.prop('disabled', false).text('▶ Spustit analýzu');
      startPolling();
    }, function (d) {
      btn.prop('disabled', false).text('▶ Spustit analýzu');
      showError(d);
    });
  });

  // ── Filtr ─────────────────────────────────────────────────────────────────

  $(document).on('click', '.seob-jsgap-filter', function () {
    currentFilter = $(this).data('filter');
    currentPage   = 1;
    $('.seob-jsgap-filter').removeClass('button-primary');
    $(this).addClass('button-primary');
    loadResults();
  });

  // ── Výsledky ─────────────────────────────────────────────────────────────

  function loadResults(page) {
    page = page || currentPage;
    var wrap = $('#seob-jsgap-table-wrap');
    wrap.html('<p style="color:#888">Načítám…</p>');

    ajax('seob_jsgap_results', { filter: currentFilter, page: page }, function (d) {
      currentPage = d.page;
      if (!d.rows.length) {
        wrap.html('<p style="color:#888">Žádné výsledky' + (currentFilter !== 'all' ? ' pro vybraný filtr' : '') + '. Spusťte analýzu nebo počkejte na příchod snapshotů.</p>');
        $('#seob-jsgap-pagination').hide();
        return;
      }
      renderTable(d.rows, wrap);
      renderPagination(d.page, d.pages);
    });
  }

  function scoreClass(score) {
    if (score >= 50) return 'seob-error';
    if (score >= 20) return 'seob-warn';
    return 'seob-ok';
  }

  function scoreLabel(score) {
    if (score >= 50) return '✗ Kritické';
    if (score >= 20) return '⚠ Varování';
    return '✓ OK';
  }

  function renderTable(rows, wrap) {
    var table = $('<table class="wp-list-table widefat fixed striped"></table>');
    table.append('<thead><tr><th>URL</th><th style="width:90px">Gap skóre</th><th style="width:100px">Rendered H1</th><th style="width:100px">Raw H1</th><th style="width:80px">JSON-LD R/R</th><th style="width:120px">Problémy</th><th style="width:90px">Akce</th></tr></thead>');
    var tbody = $('<tbody></tbody>');

    rows.forEach(function (row) {
      var sc     = parseInt(row.gap_score, 10);
      var cls    = scoreClass(sc);
      var issues = row.issues || [];

      var tr = $('<tr></tr>');
      tr.append('<td><code>' + escHtml(row.path) + '</code></td>');
      tr.append('<td><span class="' + cls + '"><strong>' + sc + '</strong> – ' + scoreLabel(sc) + '</span></td>');
      tr.append('<td>' + truncate(row.rendered_h1 || '—', 40) + '</td>');
      tr.append('<td>' + truncate(row.raw_h1 || '—', 40) + '</td>');
      tr.append('<td>' + (row.rendered_json_ld_count || 0) + ' / ' + (row.raw_json_ld_count || 0) + '</td>');

      var issueHtml = issues.length
        ? issues.map(function (i) { return '<div class="' + scoreClass(i.severity === 'critical' ? 60 : 25) + '" title="' + escHtml(i.message) + '">• ' + escHtml(i.type.replace(/_/g, ' ')) + '</div>'; }).join('')
        : '<span class="seob-ok">—</span>';
      tr.append('<td>' + issueHtml + '</td>');

      var analyzeBtn = $('<button class="button button-small seob-jsgap-analyze" data-hash="' + row.url_hash + '">Znovu</button>');
      tr.append($('<td></td>').append(analyzeBtn));

      tbody.append(tr);
    });

    table.append(tbody);
    wrap.html(table);
  }

  $(document).on('click', '.seob-jsgap-analyze', function () {
    var btn  = $(this).prop('disabled', true).text('…');
    var hash = $(this).data('hash');
    ajax('seob_jsgap_analyze_one', { url_hash: hash }, function () {
      btn.prop('disabled', false).text('Znovu');
      showSuccess('Analýza dokončena.');
      loadResults(currentPage);
    }, function (d) {
      btn.prop('disabled', false).text('Znovu');
      showError(d);
    });
  });

  // ── Pagination ────────────────────────────────────────────────────────────

  function renderPagination(page, pages) {
    if (pages <= 1) { $('#seob-jsgap-pagination').hide(); return; }
    var pag = $('#seob-jsgap-pagination').empty().show();
    for (var i = 1; i <= pages; i++) {
      var btn = $('<button class="button' + (i === page ? ' button-primary' : '') + '">' + i + '</button>').data('p', i);
      pag.append(btn).append(' ');
    }
  }

  $(document).on('click', '#seob-jsgap-pagination button', function () {
    loadResults($(this).data('p'));
  });

  // ── Helpers ───────────────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function truncate(str, n) {
    return str.length > n ? escHtml(str.slice(0, n)) + '…' : escHtml(str);
  }

  // ── Init ──────────────────────────────────────────────────────────────────

  loadStats();
  loadResults();

  // Pokud analýza právě běží (např. po reload stránky), navázat na polling
  ajax('seob_jsgap_scan_status', {}, function (d) {
    if (d.running) {
      showProgress(d.percent, d.phase + (d.total > 0 ? ' (' + d.analyzed + ' / ' + d.total + ' URL)' : ''));
      startPolling();
    }
  });

}(jQuery));
