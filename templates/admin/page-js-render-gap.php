<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$snap_table   = SEOB_Database::js_gap_snapshots_table();
$result_table = SEOB_Database::js_gap_results_table();
$total_snaps  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$snap_table}" );
$snaps_24h    = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$snap_table} WHERE received_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ) )
);
$last_analyzed = $wpdb->get_var( "SELECT MAX(analyzed_at) FROM {$result_table}" );
?>
<div class="wrap seob-wrap">
<h1>JS Render Gap detektor</h1>
<p style="color:#555;">Porovnává surové HTML (jak ho vidí Google bez JS) s vyrenderovaným DOM (jak stránku vidí uživatel). Velký rozdíl = riziko výpadku indexace.</p>

<?php if ( $total_snaps === 0 ): ?>
<div class="notice notice-warning" style="margin:12px 0;">
  <p><strong>Žádné snapshoty zatím.</strong> Beacon skript je aktivní – data se začnou sbírat od reálných návštěvníků frontendu. Navštivte pár stránek webu (jako přihlášený i jako odhlášený), po přijetí prvních snapshotů klikněte "Spustit analýzu".</p>
</div>
<?php elseif ( $snaps_24h === 0 ): ?>
<div class="notice notice-warning" style="margin:12px 0;">
  <p>Za posledních 24 h nepřišel žádný snapshot. Zkontrolujte, zda beacon není blokovaný consent nástrojem nebo cache pluginem.</p>
</div>
<?php endif; ?>

<div id="seob-jsgap-error" class="notice notice-error" style="display:none;margin:8px 0;"></div>
<div id="seob-jsgap-success" class="notice notice-success" style="display:none;margin:8px 0;"></div>

<!-- ── Statistiky ─────────────────────────────────────────── -->
<div class="seob-card" style="margin-bottom:16px;">
  <h2 style="margin-top:0;">Přehled</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:12px;">
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-total-snaps"><?php echo esc_html( $total_snaps ); ?></span>
      <span class="seob-stat-label">Snapshotů celkem</span>
    </div>
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-snaps-24h"><?php echo esc_html( $snaps_24h ); ?></span>
      <span class="seob-stat-label">Snapshotů za 24 h</span>
    </div>
    <div class="seob-stat-box seob-stat-error" id="seob-jsgap-critical-box">
      <span class="seob-stat-num" id="seob-jsgap-critical">—</span>
      <span class="seob-stat-label">Kritické (skóre ≥ 50)</span>
    </div>
    <div class="seob-stat-box seob-stat-warn" id="seob-jsgap-warning-box">
      <span class="seob-stat-num" id="seob-jsgap-warning">—</span>
      <span class="seob-stat-label">Varování (20–49)</span>
    </div>
    <div class="seob-stat-box seob-stat-ok" id="seob-jsgap-ok-box">
      <span class="seob-stat-num" id="seob-jsgap-ok">—</span>
      <span class="seob-stat-label">V pořádku (&lt; 20)</span>
    </div>
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-avg">—</span>
      <span class="seob-stat-label">Průměrné skóre</span>
    </div>
  </div>

  <?php if ( $last_analyzed ): ?>
  <p style="color:#777;font-size:12px;">Poslední analýza: <?php echo esc_html( $last_analyzed ); ?> UTC</p>
  <?php endif; ?>

  <button id="seob-jsgap-run-btn" class="button button-primary">&#9654; Spustit analýzu</button>
  <button id="seob-jsgap-clear-ls-btn" class="button" style="margin-left:8px;" title="Vymaže záznamy v localStorage prohlížeče – beacon poté znovu odešle snapshoty ze stránek, které jste dnes navštívili">&#128465; Reset rate limitů</button>
  <span id="seob-jsgap-clear-ls-msg" style="display:none;margin-left:10px;font-size:12px;color:#1a7f37;"></span>

  <!-- Progress bar -->
  <div id="seob-jsgap-progress-wrap" style="display:none;margin-top:14px;max-width:500px;">
    <div style="background:#e0e0e0;border-radius:4px;height:20px;overflow:hidden;position:relative;">
      <div id="seob-jsgap-progress-bar" style="height:100%;background:#2271b1;width:0%;transition:width 0.4s ease;"></div>
      <span id="seob-jsgap-progress-pct" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:11px;font-weight:600;color:#fff;mix-blend-mode:difference;">0 %</span>
    </div>
    <p id="seob-jsgap-progress-text" style="margin:5px 0 0;font-size:12px;color:#555;"></p>
  </div>
</div>

<!-- ── Výsledky ───────────────────────────────────────────── -->
<div class="seob-card">
  <h2 style="margin-top:0;">Výsledky per URL</h2>

  <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
    <strong>Filtr:</strong>
    <button class="button seob-jsgap-filter button-primary" data-filter="all">Vše</button>
    <button class="button seob-jsgap-filter" data-filter="critical">Kritické</button>
    <button class="button seob-jsgap-filter" data-filter="warning">Varování</button>
    <button class="button seob-jsgap-filter" data-filter="ok">OK</button>
  </div>

  <div id="seob-jsgap-table-wrap">
    <p style="color:#888;">Klikněte "Spustit analýzu" pro načtení výsledků, nebo počkejte na týdenní cron.</p>
  </div>
  <div id="seob-jsgap-pagination" style="margin-top:10px;display:none;"></div>
</div>

<!-- ── Legenda skóre ─────────────────────────────────────── -->
<div class="seob-card" style="margin-top:16px;">
  <h2 style="margin-top:0;">Jak číst Gap Skóre</h2>
  <table class="widefat" style="max-width:600px;">
    <thead><tr><th>Skóre</th><th>Závažnost</th><th>Co to znamená</th></tr></thead>
    <tbody>
      <tr><td>0–19</td><td><span class="seob-ok">&#10003; OK</span></td><td>Stránka dobře indexovatelná i bez JS</td></tr>
      <tr><td>20–49</td><td><span class="seob-warn">&#9888; Varování</span></td><td>Část obsahu závislá na JS – doporučeno zkontrolovat</td></tr>
      <tr><td>50–100</td><td><span class="seob-error">&#10005; Kritické</span></td><td>Klíčový obsah (H1, JSON-LD, meta) chybí v raw HTML – riziko výpadku indexace</td></tr>
    </tbody>
  </table>
</div>

<!-- ── Průvodce pro začátečníky ──────────────────────────── -->
<div class="seob-card" style="margin-top:16px;">
  <h2 style="margin-top:0;">&#128218; Co je JS Render Gap a proč ho řešit</h2>

  <details open style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Co je JS Render Gap?</summary>
    <div style="padding:12px 0 0 16px;line-height:1.7;color:#333;">
      <p>Google a ostatní vyhledávače si stahují vaše stránky jako <strong>"raw HTML"</strong> – tedy tak, jak přijde ze serveru, <strong>bez spouštění JavaScriptu</strong>. Ale moderní weby (WordPress s Elementorem, WooCommerce, page buildery) zobrazují část obsahu dynamicky přes JavaScript.</p>
      <p><strong>Výsledek:</strong> Google vidí stránku jinak než váš návštěvník. Nadpisy, popisky, strukturovaná data nebo hlavní text nemusí být Googlem zaindexovány – jako by stránka byla prázdná.</p>
      <p>Tento modul to zjistí automaticky: zachytí, jak stránku vidí uživatel (přes beacon v prohlížeči), stáhne raw HTML a porovná oba pohledy. Výsledek je Gap Skóre 0–100.</p>
    </div>
  </details>

  <details style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Proč to řešit?</summary>
    <div style="padding:12px 0 0 16px;line-height:1.7;color:#333;">
      <p>Google od roku 2019 sice JS renderuje, ale s <strong>velkým zpožděním</strong> (dny až týdny) a ne u každé stránky. Pokud je váš klíčový obsah závislý na JS:</p>
      <ul style="margin-left:20px;">
        <li>Stránka nezíská dobré pozice, protože Google nezná její obsah</li>
        <li>Structured data (JSON-LD) nejsou zpracována → zmizí hvězdičky, FAQ, ceny ve výsledcích</li>
        <li>H1 chybí → Google neví, o čem stránka je → špatná relevance klíčových slov</li>
        <li>Nový obsah trvá déle zaindexovat</li>
      </ul>
      <p><strong>Přínosy opravy:</strong></p>
      <ul style="margin-left:20px;">
        <li>Rychlejší a spolehlivější indexace</li>
        <li>Lepší pozice ve vyhledávání</li>
        <li>Rich snippety ve výsledcích (hvězdičky, FAQ, ceny, recenze)</li>
        <li>Větší organická návštěvnost</li>
      </ul>
    </div>
  </details>

  <details style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Co se stane, když to nebudu řešit?</summary>
    <div style="padding:12px 0 0 16px;line-height:1.7;color:#333;">
      <p>Pokud máte kritické nebo varovné nálezy a nic neděláte:</p>
      <ul style="margin-left:20px;">
        <li><strong>Ztráta pozic:</strong> Stránky s JS-závislým H1 nebo textem budou postupně klesat, protože Google je bude považovat za slabé obsahem</li>
        <li><strong>Zmizení rich snippetů:</strong> Produkty bez JSON-LD v raw HTML nezobrazí ve výsledcích hvězdičky ani cenu</li>
        <li><strong>Pomalá indexace:</strong> Nové stránky čekají na Google's JS rendering queue – místo hodin to trvá dny nebo týdny</li>
        <li><strong>Neefektivní crawl budget:</strong> Googlebot stráví čas renderováním stránek místo indexováním nového obsahu</li>
      </ul>
    </div>
  </details>

  <details id="seob-jsgap-fixes" style="margin-bottom:0;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Jak opravit jednotlivé problémy (podrobný návod)</summary>
    <p style="color:#888;font-size:12px;margin:4px 0 16px 0;">Kliknutím na název problému v tabulce výsledků se přeskrolujete přesně sem na konkrétní návod.</p>
    <div style="padding:0 0 0 0;line-height:1.7;color:#333;">

      <div id="seob-fix-h1_missing_in_raw" style="background:#fff3cd;border-left:4px solid #e65100;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; H1 chybí v raw HTML</strong> <span style="color:#888;font-size:12px;">(Gap score +35 – nejzávažnější)</span>
        <p style="margin:6px 0 4px;">Váš hlavní nadpis je vygenerován JavaScriptem – Google ho nevidí a neví, o čem stránka je.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Zkontrolujte zdrojový kód stránky:</strong> Pravým tlačítkem na stránce → "Zobrazit zdrojový kód" (nebo Ctrl+U). Hledejte <code>&lt;h1</code>. Pokud ho tam nevidíte, problém je potvrzen.</li>
          <li><strong>WordPress téma:</strong> H1 musí být v PHP šabloně (single.php, page.php), ne vkládán přes JS. Zkontrolujte svůj child theme.</li>
          <li><strong>Elementor:</strong> Widget "Nadpis" nastavený jako H1 přes "HTML tag" je v pořádku, pokud stránka není postavena čistě na JS load. Zkuste přepnout na statický H1 v šabloně.</li>
          <li><strong>WooCommerce produkty:</strong> Název produktu je obvykle v H1 staticky – zkontrolujte, zda child theme šablona <code>single-product.php</code> obsahuje <code>the_title()</code> v tagu <code>&lt;h1&gt;</code>.</li>
        </ol>
      </div>

      <div id="seob-fix-h1_mismatch" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; H1 nesouhlasí s tím, co vidí uživatel</strong> <span style="color:#888;font-size:12px;">(Gap score +20)</span>
        <p style="margin:6px 0 4px;">V raw HTML je jiný text H1 než po načtení JavaScriptu. Google indexuje špatný nadpis.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li>Ujistěte se, že JavaScript pouze stylizuje H1, ale <strong>nemění jeho text</strong>.</li>
          <li>Pokud máte A/B testing nebo personalizaci nadpisu přes JS – přesuňte logiku na server (PHP) nebo použijte SSR.</li>
          <li>Zkontrolujte, zda žádný JS plugin neoverwrituje obsah H1 při načtení stránky.</li>
        </ol>
      </div>

      <div id="seob-fix-title_mismatch" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Title stránky nesouhlasí</strong> <span style="color:#888;font-size:12px;">(Gap score +15)</span>
        <p style="margin:6px 0 4px;">Tag <code>&lt;title&gt;</code> v raw HTML se liší od titulku, který vidí prohlížeč po načtení JS.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Rank Math / Yoast SEO:</strong> Tyto pluginy vkládají title staticky do PHP – neměli byste mít problém. Zkontrolujte, zda jiný plugin title nepřepisuje.</li>
          <li>Hledejte v JS kódu <code>document.title =</code> – pokud to nějaký plugin dělá, zakažte ho nebo ho konfigurujte, aby title neměnil.</li>
          <li><strong>SPA weby (React, Vue):</strong> Použijte Next.js nebo Nuxt.js s SSR – title musí být vyrenderován na serveru.</li>
        </ol>
      </div>

      <div id="seob-fix-meta_desc_missing_in_raw" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Meta description chybí v raw HTML</strong> <span style="color:#888;font-size:12px;">(Gap score +15)</span>
        <p style="margin:6px 0 4px;">Popis stránky pro vyhledávače není přítomen v HTML – je přidáván až JavaScriptem nebo chybí úplně.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li>Ujistěte se, že máte nainstalovaný a nakonfigurovaný SEO plugin (Rank Math, Yoast). Tyto pluginy vkládají meta description staticky.</li>
          <li>Vyplňte meta description u každé stránky/příspěvku v SEO panelu v editoru.</li>
          <li><strong>Nikdy</strong> nespoléhejte na JavaScript pro vložení meta description – Google ho nemusí zpracovat.</li>
        </ol>
      </div>

      <div id="seob-fix-json_ld_gap" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Strukturovaná data (JSON-LD) chybí v raw HTML</strong> <span style="color:#888;font-size:12px;">(Gap score +20)</span>
        <p style="margin:6px 0 4px;">Vaše schémata (Article, Product, FAQ, Review...) jsou vkládána JavaScriptem. Google je nemusí vidět → žádné rich snippety ve výsledcích.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Rank Math:</strong> JSON-LD vkládá staticky do <code>&lt;head&gt;</code> přes PHP hook <code>wp_head</code> – pokud toto používáte, zkontrolujte, zda máte plugin aktualizovaný a schéma správně nakonfigurované.</li>
          <li><strong>Vlastní JSON-LD kód:</strong> Přesuňte <code>&lt;script type="application/ld+json"&gt;</code> z JavaScript souborů do PHP šablony (použijte <code>wp_head</code> akci).</li>
          <li><strong>Ověření:</strong> Ve zdrojovém kódu stránky hledejte <code>application/ld+json</code>. Pokud tam není, schéma nepochází ze serveru.</li>
        </ol>
      </div>

      <div id="seob-fix-text_ratio" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; Příliš málo textu v raw HTML</strong> <span style="color:#888;font-size:12px;">(Gap score +10 varování / +20 kritické)</span>
        <p style="margin:6px 0 4px;">Hlavní textový obsah stránky je zobrazován přes JavaScript (lazy load obsahu, akordeony, záložky načítané AJAXem). Google vidí prázdnou nebo chudou stránku.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Akordeony a záložky:</strong> Obsah musí být v HTML a jen vizuálně skrytý pomocí CSS (<code>display:none</code> nebo <code>visibility:hidden</code>) – <strong>ne</strong> načítaný AJAXem po kliknutí. Google CSS-skrytý obsah indexuje.</li>
          <li><strong>Lazy load obsahu:</strong> Lazy load je správný pro obrázky, ale <strong>nikoli</strong> pro hlavní textový obsah. Ten musí být v HTML od začátku.</li>
          <li><strong>Elementor Loop / WooCommerce gridy:</strong> Produkty nebo příspěvky načítané filtry přes AJAX Google nevidí. Zvažte statické stránkování místo AJAX filtrování pro klíčové kategorie.</li>
          <li><strong>Infinite scroll:</strong> Google indexuje jen první stránku. Přidejte alternativní stránkování s URL (<code>/page/2/</code>) nebo použijte Google's strukturu pro infinite scroll.</li>
        </ol>
      </div>

    </div>
  </details>
</div>

</div>
