/* global seobData */
(function ($) {
  'use strict';

  var currentFilter = 'all';
  var currentPage   = 1;
  var scanRunning   = false;

  function ajax(action, data, ok, fail) {
    $.post(seobData.ajaxUrl, Object.assign({ action: action, nonce: seobData.nonce }, data))
      .done(function (r) { r.success ? ok(r.data) : (fail || showError)(r.data); })
      .fail(function () {
        // Server error (500, timeout) – resetuj scan stav aby UI nezamrzlo
        if (scanRunning) {
          hideProgress();
          $('#seob-jsgap-run-btn').prop('disabled', false).text('▶ Spustit analýzu');
          scanRunning = false;
        }
        showError({ message: 'Chyba serveru – zkuste analýzu spustit znovu.' });
      });
  }

  function showError(d) {
    $('#seob-jsgap-error').html('<p>' + escHtml(d.message || 'Neznámá chyba.') + '</p>').show();
    setTimeout(function () { $('#seob-jsgap-error').hide(); }, 8000);
  }

  function showSuccess(msg) {
    $('#seob-jsgap-success').html('<p>' + escHtml(msg) + '</p>').show();
    setTimeout(function () { $('#seob-jsgap-success').hide(); }, 6000);
  }

  // ── Progress bar ──────────────────────────────────────────────────────────

  function showProgress(percent, text) {
    $('#seob-jsgap-progress-wrap').show();
    $('#seob-jsgap-progress-bar').css('width', Math.max(5, percent) + '%');
    $('#seob-jsgap-progress-pct').text(percent + ' %');
    $('#seob-jsgap-progress-text').text(text || '');
  }

  function hideProgress() {
    $('#seob-jsgap-progress-wrap').fadeOut(600);
  }

  // ── Scan – synchronní dávkové volání ─────────────────────────────────────

  function runScanBatch() {
    ajax('seob_jsgap_run_scan', {}, function (d) {
      if (d.total === 0) {
        hideProgress();
        showError({ message: d.message || 'Žádné snapshoty. Navštivte nejprve pár stránek webu.' });
        $('#seob-jsgap-run-btn').prop('disabled', false).text('▶ Spustit analýzu');
        scanRunning = false;
        return;
      }

      var label = 'Analyzuji URL ' + d.analyzed + ' / ' + d.total;
      if (d.processed === 0 && d.remaining > 0) {
        label = 'Zpracovávám… (' + d.remaining + ' zbývá)';
      }
      showProgress(d.percent, label);

      if (d.done) {
        showProgress(100, 'Hotovo! Analyzováno ' + d.analyzed + ' URL.');
        setTimeout(function () {
          hideProgress();
          $('#seob-jsgap-run-btn').prop('disabled', false).text('▶ Spustit analýzu');
          scanRunning = false;
          loadStats();
          loadResults();
          showSuccess('Analýza dokončena – ' + d.analyzed + ' URL zkontrolováno.');
        }, 1000);
      } else {
        // Zpracuj další dávku
        setTimeout(runScanBatch, 300);
      }
    }, function (d) {
      hideProgress();
      $('#seob-jsgap-run-btn').prop('disabled', false).text('▶ Spustit analýzu');
      scanRunning = false;
      showError(d);
    });
  }

  // ── Tlačítko spustit ──────────────────────────────────────────────────────

  $('#seob-jsgap-run-btn').on('click', function () {
    if (scanRunning) return;
    scanRunning = true;
    $(this).prop('disabled', true).text('Analyzuji…');
    showProgress(2, 'Spouštím analýzu…');
    runScanBatch();
  });

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
        wrap.html('<p style="color:#888">Žádné výsledky' + (currentFilter !== 'all' ? ' pro vybraný filtr' : '') + '. Spusťte analýzu nebo počkejte na příchod snapshotů od návštěvníků.</p>');
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
    table.append('<thead><tr><th>URL</th><th style="width:110px">Gap skóre</th><th style="width:110px">Rendered H1</th><th style="width:110px">Raw H1</th><th style="width:80px">JSON-LD R/R</th><th style="width:130px">Problémy</th><th style="width:80px">Akce</th></tr></thead>');
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
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function truncate(str, n) {
    str = String(str || '');
    return str.length > n ? escHtml(str.slice(0, n)) + '…' : escHtml(str);
  }

  // ── Init ──────────────────────────────────────────────────────────────────

  loadStats();
  loadResults();

}(jQuery));
