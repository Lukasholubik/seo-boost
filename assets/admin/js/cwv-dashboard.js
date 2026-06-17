/* SEO Booster Pro – CWV RUM Dashboard */
/* global seobData, Chart */
(function () {
  'use strict';

  var chart = null;
  var currentMetric = 'LCP';
  var currentDevice = '';
  var currentDays   = 30;

  // Prahy Google (ms, nebo bezrozměrné pro CLS)
  var THRESHOLDS = {
    LCP:  { good: 2500,  poor: 4000,  unit: 'ms',  label: 'Largest Contentful Paint' },
    INP:  { good: 200,   poor: 500,   unit: 'ms',  label: 'Interaction to Next Paint' },
    CLS:  { good: 0.1,   poor: 0.25,  unit: '',    label: 'Cumulative Layout Shift' },
    FCP:  { good: 1800,  poor: 3000,  unit: 'ms',  label: 'First Contentful Paint' },
    TTFB: { good: 800,   poor: 1800,  unit: 'ms',  label: 'Time to First Byte' },
  };

  function ajax(action, data, done) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: seobData.nonce }, data));
    fetch(seobData.ajaxUrl, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (res.success) done(res.data); else showError(res.data && res.data.message); })
      .catch(function () { showError('Chyba načítání.'); });
  }

  function showError(msg) {
    var el = document.getElementById('seob-cwv-error');
    if (el) { el.textContent = msg || 'Chyba.'; el.style.display = 'block'; }
  }

  function ratingColor(value, metric) {
    var t = THRESHOLDS[metric];
    if (!t) return '#888';
    if (value <= t.good) return '#1a7f37';
    if (value <= t.poor) return '#9a6700';
    return '#cf222e';
  }

  function formatValue(value, metric) {
    if (!value && value !== 0) return '—';
    var t = THRESHOLDS[metric] || { unit: '' };
    if (metric === 'CLS') return value.toFixed(3);
    return Math.round(value) + ' ms';
  }

  function loadChart() {
    ajax('seob_cwv_dashboard', {
      metric: currentMetric,
      device: currentDevice,
      days:   currentDays,
    }, function (d) {
      var ctx = document.getElementById('seob-cwv-chart');
      if (!ctx) return;

      if (chart) { chart.destroy(); chart = null; }

      var t = THRESHOLDS[d.metric] || {};

      var datasets = [];
      if (!currentDevice || currentDevice === 'mobile') {
        datasets.push({
          label: 'Mobil p75',
          data: d.mobile,
          borderColor: '#0969da',
          backgroundColor: 'rgba(9,105,218,0.1)',
          tension: 0.3,
          spanGaps: true,
          pointRadius: 3,
        });
      }
      if (!currentDevice || currentDevice === 'desktop') {
        datasets.push({
          label: 'Desktop p75',
          data: d.desktop,
          borderColor: '#6639ba',
          backgroundColor: 'rgba(102,57,186,0.1)',
          tension: 0.3,
          spanGaps: true,
          pointRadius: 3,
        });
      }

      chart = new Chart(ctx, {
        type: 'line',
        data: { labels: d.labels, datasets: datasets },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'top' },
            annotation: t.good ? {
              annotations: {
                good: { type: 'line', yMin: t.good, yMax: t.good, borderColor: '#1a7f37', borderWidth: 1, borderDash: [4,4], label: { content: 'Dobrý', display: true, position: 'end', color: '#1a7f37', backgroundColor: 'transparent', font: { size: 11 } } },
                poor: { type: 'line', yMin: t.poor, yMax: t.poor, borderColor: '#cf222e', borderWidth: 1, borderDash: [4,4], label: { content: 'Špatný', display: true, position: 'end', color: '#cf222e', backgroundColor: 'transparent', font: { size: 11 } } },
              },
            } : {},
          },
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: (t.unit || (currentMetric === 'CLS' ? 'skóre' : '')) },
            },
          },
        },
      });

      // Health status
      var samplesEl = document.getElementById('seob-cwv-samples-24h');
      if (samplesEl) samplesEl.textContent = d.samples_24h;

      var aggEl = document.getElementById('seob-cwv-last-agg');
      if (aggEl) aggEl.textContent = d.last_agg || '—';

      var warnEl = document.getElementById('seob-cwv-no-data-warn');
      if (warnEl) warnEl.style.display = d.samples_24h === 0 ? 'block' : 'none';
    });
  }

  function loadWorstUrls() {
    var tbody = document.getElementById('seob-cwv-urls-tbody');
    if (!tbody) return;

    ajax('seob_cwv_worst_urls', {
      metric: currentMetric,
      device: currentDevice || 'mobile',
      days:   currentDays,
      limit:  20,
    }, function (d) {
      if (!d.urls.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="color:#72767d;text-align:center;">Žádná data pro zvolený filtr.</td></tr>';
        return;
      }
      var html = '';
      d.urls.forEach(function (u, i) {
        var color = ratingColor(u.p75, d.metric);
        var badge = '<span style="background:' + color + ';color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;">' + (u.rating || '') + '</span>';
        html += '<tr><td>' + (i + 1) + '</td><td><a href="' + encodeURI(location.origin + u.path) + '" target="_blank" rel="noopener">' + escHtml(u.path) + '</a></td>' +
          '<td style="font-weight:600;color:' + color + ';">' + formatValue(u.p75, d.metric) + '</td>' +
          '<td>' + badge + ' <small style="color:#888;">' + u.samples + ' vzorků</small></td></tr>';
      });
      tbody.innerHTML = html;
    });
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function init() {
    // Metric buttons
    document.querySelectorAll('[data-cwv-metric]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('[data-cwv-metric]').forEach(function (b) { b.classList.remove('button-primary'); b.classList.add('button'); });
        btn.classList.add('button-primary');
        btn.classList.remove('button');
        currentMetric = btn.dataset.cwvMetric;
        updateMetricLabel();
        loadChart();
        loadWorstUrls();
      });
    });

    // Device select
    var deviceSel = document.getElementById('seob-cwv-device');
    if (deviceSel) {
      deviceSel.addEventListener('change', function () {
        currentDevice = deviceSel.value;
        loadChart();
        loadWorstUrls();
      });
    }

    // Days select
    var daysSel = document.getElementById('seob-cwv-days');
    if (daysSel) {
      daysSel.addEventListener('change', function () {
        currentDays = parseInt(daysSel.value, 10);
        loadChart();
        loadWorstUrls();
      });
    }

    // Settings form
    var settingsForm = document.getElementById('seob-cwv-settings-form');
    if (settingsForm) {
      settingsForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = {};
        new FormData(settingsForm).forEach(function (v, k) { data[k] = v; });
        ajax('seob_cwv_save_settings', { data: JSON.stringify(data) }, function (d) {
          var msg = document.getElementById('seob-cwv-settings-msg');
          if (msg) { msg.textContent = d.message; msg.style.display = 'block'; setTimeout(function () { msg.style.display = 'none'; }, 3000); }
        });
      });
    }

    // Run aggregation button
    var runAggBtn = document.getElementById('seob-cwv-run-agg-btn');
    if (runAggBtn) {
      runAggBtn.addEventListener('click', function () {
        runAggBtn.disabled = true;
        runAggBtn.textContent = 'Plánuji…';
        ajax('seob_cwv_run_aggregation', {}, function (d) {
          runAggBtn.disabled = false;
          runAggBtn.textContent = '▶ Spustit agregaci nyní';
          var msg = document.getElementById('seob-cwv-agg-msg');
          if (msg) { msg.textContent = d.message; msg.style.display = 'inline'; setTimeout(function () { msg.style.display = 'none'; loadChart(); loadWorstUrls(); }, 5000); }
        });
      });
    }

    updateMetricLabel();
    loadChart();
    loadWorstUrls();
  }

  function updateMetricLabel() {
    var el = document.getElementById('seob-cwv-metric-label');
    if (el && THRESHOLDS[currentMetric]) {
      el.textContent = THRESHOLDS[currentMetric].label + ' (' + currentMetric + ')';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
