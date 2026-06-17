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

      // sanitize_key() na serveru vrátí lowercase ('ttfb') – normalizuj na uppercase
      var t = THRESHOLDS[(d.metric || currentMetric).toUpperCase()] || {};

      // Datové body
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
          order: 1,
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
          order: 2,
        });
      }

      // Hranicové linie jako dataset (nevyžaduje annotation plugin)
      var n = d.labels.length;
      if (t.good && n > 0) {
        datasets.push({
          label: 'Dobrý (' + (t.unit ? t.good + ' ' + t.unit : t.good) + ')',
          data: Array(n).fill(t.good),
          borderColor: '#1a7f37',
          borderWidth: 1.5,
          borderDash: [5, 4],
          pointRadius: 0,
          fill: false,
          tension: 0,
          order: 10,
        });
        datasets.push({
          label: 'Špatný (' + (t.unit ? t.poor + ' ' + t.unit : t.poor) + ')',
          data: Array(n).fill(t.poor),
          borderColor: '#cf222e',
          borderWidth: 1.5,
          borderDash: [5, 4],
          pointRadius: 0,
          fill: false,
          tension: 0,
          order: 11,
        });
      }

      chart = new Chart(ctx, {
        type: 'line',
        data: { labels: d.labels, datasets: datasets },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'top' },
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

      showDiagnostics(d);
    });
  }

  // ── Stručné příčiny pro diagnostický panel ────────────────────────────────
  var DIAG = {
    LCP: {
      ni:   'Hero obrázek je velký nebo chybí preload. Server odpovídá pomalu (viz TTFB).',
      poor: 'Stránka se načítá velmi pomalu – uživatelé ji pravděpodobně opouštějí. Kritické pro pozice v Google.',
      tips: ['Zkontrolujte, zda hero obrázek nemá <code>loading="lazy"</code> – odstraňte to', 'Přidejte <code>fetchpriority="high"</code> na hero obrázek', 'Aktivujte stránkový cache (WP Rocket, LiteSpeed Cache)', 'Konvertujte obrázky na WebP'],
    },
    INP: {
      ni:   'Stránka reaguje na klikání se zpožděním – obvykle příliš mnoho nebo příliš těžký JavaScript.',
      poor: 'Stránka téměř nereaguje na interakce. Zákazník si produkt nepřidá do košíku, odejde.',
      tips: ['Identifikujte těžké JS pluginy v Chrome DevTools → Performance', 'Aktivujte "Delay JS" ve WP Rocket', 'Zkontrolujte cookies/GDPR plugin (OneTrust, Cookiebot jsou těžké)', 'Aktualizujte Elementor a page builder na nejnovější verzi'],
    },
    CLS: {
      ni:   'Stránka mírně "poskakuje" při načítání – chybí rozměry obrázkům nebo rezervace pro bannery.',
      poor: 'Stránka výrazně poskakuje – uživatelé klikají na špatná místa. Silný negativní dopad na UX.',
      tips: ['Přidejte <code>width</code> a <code>height</code> ke všem obrázkům v HTML', 'Vyhraďte místo pro reklamy pomocí <code>min-height</code> v CSS', 'Přidejte <code>font-display: swap</code> do CSS vašich fontů', 'Obalte YouTube/Google Maps do kontejneru s <code>aspect-ratio: 16/9</code>'],
    },
    FCP: {
      ni:   'Uživatel čeká na první obsah. Nejčastěji render-blocking CSS nebo pomalý server.',
      poor: 'Stránka se zdá prázdná příliš dlouho – většina návštěvníků odejde ještě před načtením.',
      tips: ['Aktivujte stránkový cache (WP Rocket, LiteSpeed Cache)', 'Minifikujte a slučte CSS/JS soubory', 'Přidejte kritické CSS inline do <code>&lt;head&gt;</code>', 'Preloadujte hlavní font: <code>&lt;link rel="preload" as="font"&gt;</code>'],
    },
    TTFB: {
      ni:   'Server odpovídá pomalu – žádný cache, pomalý PHP/databáze nebo přetížený hosting.',
      poor: 'Server je velmi pomalý. Vše ostatní (LCP, FCP) bude špatné, dokud neopravíte TTFB.',
      tips: ['Aktivujte stránkový cache – TTFB klesne na &lt; 100 ms okamžitě', 'Nainstalujte Query Monitor a najděte pomalé DB dotazy', 'Aktivujte Redis Object Cache (u hostingů jako Kinsta, WP Engine)', 'Zvažte Cloudflare (zdarma) – cachuje stránky na CDN uzlech'],
    },
  };

  function showDiagnostics(d) {
    var el = document.getElementById('seob-cwv-diagnostics');
    if (!el) return;

    var metric = (d.metric || currentMetric).toUpperCase();
    var t      = THRESHOLDS[metric];
    var diag   = DIAG[metric];
    if (!t || !diag) { el.innerHTML = ''; return; }

    // Najdi nejnovější hodnotu (mobile nebo desktop)
    var allVals = (d.mobile || []).concat(d.desktop || []).filter(function (v) { return v !== null && v !== undefined; });
    if (!allVals.length) { el.innerHTML = ''; return; }
    var latest = allVals[allVals.length - 1];

    var rating, borderColor, bgColor, icon, ratingLabel;
    if (latest <= t.good) {
      rating = 'good'; borderColor = '#1a7f37'; bgColor = '#f0fff4'; icon = '✓'; ratingLabel = 'Dobrý';
    } else if (latest <= t.poor) {
      rating = 'ni'; borderColor = '#e65100'; bgColor = '#fff8e1'; icon = '⚠'; ratingLabel = 'Potřebuje zlepšení';
    } else {
      rating = 'poor'; borderColor = '#cf222e'; bgColor = '#fff0f0'; icon = '✗'; ratingLabel = 'Špatný';
    }

    var formatted = formatValue(latest, metric);

    if (rating === 'good') {
      el.innerHTML = '<div style="padding:10px 16px;background:' + bgColor + ';border-left:4px solid ' + borderColor + ';border-radius:0 4px 4px 0;">' +
        '<strong style="color:' + borderColor + ';">' + icon + ' ' + metric + ': ' + ratingLabel + ' (' + formatted + ')</strong>' +
        ' – pod hranicí Google doporučení. Jen udržujte!' +
        '</div>';
      return;
    }

    var tipsHtml = diag.tips.map(function (tip, i) {
      return '<li style="margin-bottom:4px;">' + tip + '</li>';
    }).join('');

    el.innerHTML = '<div style="padding:12px 16px;background:' + bgColor + ';border-left:4px solid ' + borderColor + ';border-radius:0 4px 4px 0;">' +
      '<div style="display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;">' +
        '<strong style="font-size:14px;color:' + borderColor + ';">' + icon + ' ' + metric + ': ' + ratingLabel + ' (' + formatted + ')</strong>' +
        '<a class="seob-cwv-diag-link" href="#seob-cwv-fix-' + metric.toLowerCase() + '" style="font-size:12px;color:#2271b1;white-space:nowrap;">→ Podrobný návod opravy ↓</a>' +
      '</div>' +
      '<p style="margin:6px 0 6px;color:#555;font-size:13px;">' + (rating === 'poor' ? diag.poor : diag.ni) + '</p>' +
      '<strong style="font-size:12px;color:#333;">Nejčastější příčiny a první kroky:</strong>' +
      '<ol style="margin:4px 0 0 18px;font-size:13px;">' + tipsHtml + '</ol>' +
    '</div>';
  }

  // Proklik z diagnostiky na konkrétní sekci návodu
  document.addEventListener('click', function (e) {
    var link = e.target.closest('.seob-cwv-diag-link');
    if (!link) return;
    e.preventDefault();
    var anchor = link.getAttribute('href').replace('#', '');
    var target = document.getElementById(anchor);
    if (!target) return;
    var details = document.getElementById('seob-cwv-fixes');
    if (details) details.open = true;
    setTimeout(function () {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.style.transition = 'box-shadow 0.3s';
      target.style.boxShadow  = '0 0 0 3px #2271b1';
      setTimeout(function () { target.style.boxShadow = ''; }, 2200);
    }, 80);
  });

  function loadWorstUrls() {
    var tbody = document.getElementById('seob-cwv-urls-tbody');
    if (!tbody) return;

    ajax('seob_cwv_worst_urls', {
      metric: currentMetric,
      device: currentDevice,
      days:   currentDays,
      limit:  20,
    }, function (d) {
      if (!d.urls.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="color:#72767d;text-align:center;">Žádná data pro zvolený filtr.</td></tr>';
        return;
      }
      var html = '';
      d.urls.forEach(function (u, i) {
        var color = ratingColor(u.p75, d.metric.toUpperCase());
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
        runAggBtn.textContent = 'Agregace probíhá…';
        var msg = document.getElementById('seob-cwv-agg-msg');
        if (msg) { msg.textContent = 'Zpracovávám data…'; msg.style.display = 'inline'; msg.style.color = '#555'; }

        var body = new URLSearchParams({ action: 'seob_cwv_run_aggregation', nonce: seobData.nonce });
        fetch(seobData.ajaxUrl, { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            runAggBtn.disabled = false;
            runAggBtn.textContent = '▶ Spustit agregaci nyní';
            if (res.success) {
              if (msg) { msg.textContent = res.data.message; msg.style.color = '#1a7f37'; setTimeout(function () { msg.style.display = 'none'; }, 5000); }
              loadChart();
              loadWorstUrls();
            } else {
              if (msg) { msg.textContent = 'Chyba: ' + ((res.data && res.data.message) || 'Neznámá chyba.'); msg.style.color = '#cf222e'; }
            }
          })
          .catch(function () {
            runAggBtn.disabled = false;
            runAggBtn.textContent = '▶ Spustit agregaci nyní';
            if (msg) { msg.textContent = 'Chyba spojení.'; msg.style.color = '#cf222e'; }
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
