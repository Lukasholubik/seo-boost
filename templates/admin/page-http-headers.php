<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap seob-wrap">
  <h1>HTTP Hlavičky &amp; Bezpečnost</h1>
  <p class="seob-subtitle">Kontroluje HTTP odpovědi stránek webu – bezpečnostní hlavičky, x-robots-tag, HTTPS, přesměrování a cache.</p>

  <div id="seob-http-error" class="notice notice-error" style="display:none;"></div>
  <div id="seob-http-success" class="notice notice-success" style="display:none;"></div>

  <!-- Ovládací panel -->
  <div class="seob-card" style="margin-bottom:20px;padding:16px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <button id="seob-http-start-btn" class="button button-primary">&#9654; Spustit scan</button>
    <button id="seob-http-cancel-btn" class="button" style="display:none;">&#9632; Zrušit</button>
    <select id="seob-http-history-select" style="display:none;">
      <option value="">— výsledky skenů —</option>
    </select>
    <span id="seob-http-last-scan" style="color:#888;font-size:13px;"></span>
  </div>

  <!-- Progress bar -->
  <div id="seob-http-progress-wrap" style="display:none;margin-bottom:16px;">
    <div style="background:#e5e7eb;border-radius:4px;height:18px;overflow:hidden;">
      <div id="seob-http-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.4s;"></div>
    </div>
    <p id="seob-http-progress-text" style="margin:4px 0 0;font-size:13px;color:#555;"></p>
  </div>

  <!-- Přehledové karty -->
  <div id="seob-http-summary" style="display:none;gap:12px;margin-bottom:20px;" class="seob-stats-row">
    <div class="seob-stat-card">
      <div class="seob-stat-num" id="seob-http-stat-scanned">—</div>
      <div class="seob-stat-label">Zkontrolováno URL</div>
    </div>
    <div class="seob-stat-card seob-error">
      <div class="seob-stat-num" id="seob-http-stat-critical">—</div>
      <div class="seob-stat-label">Kritické problémy</div>
    </div>
    <div class="seob-stat-card seob-warn">
      <div class="seob-stat-num" id="seob-http-stat-warnings">—</div>
      <div class="seob-stat-label">Varování</div>
    </div>
    <div class="seob-stat-card seob-ok">
      <div class="seob-stat-num" id="seob-http-stat-ok">—</div>
      <div class="seob-stat-label">OK (bez problémů)</div>
    </div>
  </div>

  <!-- Filtr -->
  <div id="seob-http-filters" style="display:none;margin-bottom:12px;">
    <button class="button seob-http-filter button-primary" data-filter="all">Vše</button>
    <button class="button seob-http-filter" data-filter="critical">Kritické</button>
    <button class="button seob-http-filter" data-filter="warning">Varování</button>
    <button class="button seob-http-filter" data-filter="ok">OK</button>
  </div>

  <!-- Výsledková tabulka -->
  <div id="seob-http-table-wrap">
    <p style="color:#888;">Spusťte scan pro zobrazení výsledků.</p>
  </div>

  <hr style="margin:32px 0 24px;">

  <!-- Rychlá kontrola jedné URL -->
  <div class="seob-card" style="padding:16px 20px;margin-bottom:24px;">
    <h3 style="margin-top:0;">Rychlá kontrola URL</h3>
    <p style="color:#555;margin:0 0 10px;">Zadejte libovolnou URL a okamžitě uvidíte její HTTP hlavičky a problémy.</p>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="url" id="seob-http-single-url" class="regular-text" placeholder="https://example.com/stranka/" style="max-width:420px;">
      <button id="seob-http-single-btn" class="button">Zkontrolovat</button>
    </div>
    <div id="seob-http-single-result" style="margin-top:14px;"></div>
  </div>

  <hr style="margin:24px 0;">

  <!-- Dokumentace / jak opravit -->
  <details id="seob-http-fixes" style="margin-top:20px;">
    <summary style="cursor:pointer;font-size:15px;font-weight:600;padding:8px 0;">&#9660; Jak opravit nalezené problémy</summary>
    <div style="padding:12px 0 0;">

      <div id="seob-fix-x_robots_noindex" style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#10060; X-Robots-Tag: noindex v HTTP hlavičce</strong> <span style="color:#888;font-size:12px;">(Gap score -30)</span>
        <p style="margin:6px 0 4px;">Server posílá <code>X-Robots-Tag: noindex</code> – Google tuto stránku <strong>nekopíruje do indexu</strong>, i kdyby měla skvělý obsah.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li>Zkontrolujte <code>.htaccess</code> – hledejte <code>Header set X-Robots-Tag</code> nebo <code>header("X-Robots-Tag</code></li>
          <li>V <code>wp-config.php</code> zkontrolujte konstantu <code>BLOG_PUBLIC</code> – musí být <code>1</code>, ne <code>0</code>.</li>
          <li>Nastavení → Čtení → <strong>zrušte zaškrtnutí</strong> „Zabránit vyhledávačům v indexování tohoto webu".</li>
          <li>Zkontrolujte Rank Math → Nastavení → Rozšířené → „noindex" pro celý web.</li>
        </ol>
      </div>

      <div id="seob-fix-no_https" style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#10060; Stránka neslouží přes HTTPS</strong>
        <p style="margin:6px 0 4px;">Google od 2018 upřednostňuje HTTPS weby a od 2021 ho fakticky vyžaduje pro dobré hodnocení (Core Web Vitals, Page Experience).</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li>Nainstalujte SSL certifikát (Let's Encrypt – zdarma přes hosting).</li>
          <li>V <code>.htaccess</code> přidejte redirect: <code>RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]</code></li>
          <li>Aktualizujte URL webu v Nastavení → Obecné na <code>https://</code>.</li>
          <li>Spusťte plugin Really Simple SSL nebo Cloudflare pro automatické přesměrování.</li>
        </ol>
      </div>

      <div id="seob-fix-missing_hsts" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Chybí Strict-Transport-Security (HSTS)</strong>
        <p style="margin:6px 0 4px;">HSTS říká prohlížeči, aby vždy používal HTTPS. Bez ní může být první request přes HTTP (Man-in-the-Middle riziko).</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit (.htaccess / nginx):</strong></p>
        <pre style="background:#f5f5f5;padding:8px;border-radius:3px;font-size:12px;overflow-x:auto;"># Apache (.htaccess)
&lt;IfModule mod_headers.c&gt;
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
&lt;/IfModule&gt;

# Nginx (nginx.conf)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;</pre>
      </div>

      <div id="seob-fix-missing_xcto" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Chybí X-Content-Type-Options</strong>
        <p style="margin:6px 0 4px;">Zabrání MIME sniffing útokům – prohlížeč nevykoná soubor jako jiný typ, než říká Content-Type.</p>
        <pre style="background:#f5f5f5;padding:8px;border-radius:3px;font-size:12px;overflow-x:auto;"># Apache (.htaccess)
&lt;IfModule mod_headers.c&gt;
  Header always set X-Content-Type-Options "nosniff"
&lt;/IfModule&gt;</pre>
        <p style="margin:6px 0 2px;"><strong>Nebo přes WP plugin:</strong> Použijte Security Headers plugin nebo Wordfence (sekce Brute Force Protection → HTTP hlavičky).</p>
      </div>

      <div id="seob-fix-missing_xfo" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Chybí X-Frame-Options</strong>
        <p style="margin:6px 0 4px;">Zabrání clickjacking útokům – web nelze vložit do iframe na cizí doméně.</p>
        <pre style="background:#f5f5f5;padding:8px;border-radius:3px;font-size:12px;overflow-x:auto;"># Apache (.htaccess)
&lt;IfModule mod_headers.c&gt;
  Header always set X-Frame-Options "SAMEORIGIN"
&lt;/IfModule&gt;</pre>
      </div>

      <div id="seob-fix-missing_rp" style="background:#f0f9ff;border-left:4px solid #60a5fa;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#8505; Chybí Referrer-Policy</strong>
        <p style="margin:6px 0 4px;">Řídí, jaký referrer se posílá při navigaci mezi stránkami. Ovlivňuje přesnost dat v Google Analytics.</p>
        <pre style="background:#f5f5f5;padding:8px;border-radius:3px;font-size:12px;overflow-x:auto;"># Apache (.htaccess)
&lt;IfModule mod_headers.c&gt;
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
&lt;/IfModule&gt;</pre>
      </div>

      <div id="seob-fix-missing_cache" style="background:#f0f9ff;border-left:4px solid #60a5fa;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#8505; Chybí Cache-Control / Expires</strong>
        <p style="margin:6px 0 4px;">Cache hlavičky urychlují opakované načtení stránek a pomáhají skóre PageSpeed. WordPress samotný je neodesílá – musí je nastavit server nebo caching plugin.</p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>WP Rocket / W3TC / LiteSpeed Cache</strong> – nastavte dobu cachování v pluginu.</li>
          <li><strong>Manuálně (.htaccess):</strong>
            <pre style="background:#f5f5f5;padding:8px;border-radius:3px;font-size:12px;overflow-x:auto;">&lt;IfModule mod_expires.c&gt;
  ExpiresActive On
  ExpiresDefault "access plus 1 hour"
  ExpiresByType text/html "access plus 1 hour"
&lt;/IfModule&gt;</pre>
          </li>
        </ol>
      </div>

    </div>
  </details>

</div>
