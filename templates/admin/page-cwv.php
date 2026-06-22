<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$cfg         = SEOB_Settings::get( SEOB_Settings::CWV );
$raw_table   = SEOB_Database::cwv_raw_table();
$daily_table = SEOB_Database::cwv_daily_table();

global $wpdb;
$total_raw    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$raw_table}" );
$total_daily  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily_table}" );
$oldest_raw   = $wpdb->get_var( "SELECT MIN(recorded_at) FROM {$raw_table}" );
$last_raw     = $wpdb->get_var( "SELECT MAX(recorded_at) FROM {$raw_table}" );
$last_agg     = (int) get_option( 'seob_cwv_last_aggregation', 0 );
$samples_24h  = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$raw_table} WHERE recorded_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime('-24 hours') ) )
);
?>
<div class="wrap seob-wrap">
<h1>Core Web Vitals – RUM monitoring</h1>

<?php if ( $samples_24h === 0 && $total_raw === 0 ): ?>
<div class="notice notice-warning" style="margin:12px 0;">
  <p><strong>Žádná data zatím.</strong> Beacon skript je aktivní – čeká na první návštěvníky frontendu. Data se zobrazí zde po přijetí prvních měření.</p>
</div>
<?php endif; ?>

<div id="seob-cwv-error" class="notice notice-error" style="display:none;margin:8px 0;"></div>

<!-- ── Filtry ─────────────────────────────────────────────── -->
<div class="seob-card" style="margin-bottom:16px;">
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <div>
      <strong>Metrika:</strong>
      <button class="button button-primary" data-cwv-metric="LCP">LCP</button>
      <button class="button" data-cwv-metric="INP">INP</button>
      <button class="button" data-cwv-metric="CLS">CLS</button>
      <button class="button" data-cwv-metric="FCP">FCP</button>
      <button class="button" data-cwv-metric="TTFB">TTFB</button>
    </div>
    <div>
      <label for="seob-cwv-device"><strong>Zařízení:</strong></label>
      <select id="seob-cwv-device">
        <option value="">Obě</option>
        <option value="mobile">Mobil</option>
        <option value="desktop">Desktop</option>
      </select>
    </div>
    <div>
      <label for="seob-cwv-days"><strong>Období:</strong></label>
      <select id="seob-cwv-days">
        <option value="7">7 dní</option>
        <option value="30" selected>30 dní</option>
        <option value="90">90 dní</option>
      </select>
    </div>
  </div>
</div>

<!-- ── Graf ──────────────────────────────────────────────── -->
<div class="seob-card" style="margin-bottom:16px;">
  <h2 id="seob-cwv-metric-label" style="margin-top:0;">Largest Contentful Paint (LCP)</h2>
  <div id="seob-cwv-no-data-warn" class="notice notice-warning inline" style="display:none;margin:0 0 12px;">
    <p>⚠️ Za posledních 24 hodin nepřišla žádná data. Zkontrolujte, zda beacon skript není blokovaný consent nástrojem nebo cache pluginem.</p>
  </div>
  <div style="position:relative;height:320px;">
    <canvas id="seob-cwv-chart"></canvas>
  </div>
  <div style="margin-top:10px;font-size:12px;color:#72767d;">
    Přerušovaná zelená linie = hranice „Dobrý", červená = „Špatný" dle Google Core Web Vitals.
  </div>
  <!-- Dynamická diagnostika – vyplní JS po načtení dat -->
  <div id="seob-cwv-diagnostics" style="margin-top:12px;"></div>
</div>

<!-- ── Nejhorší URL ───────────────────────────────────────── -->
<div class="seob-card" style="margin-bottom:16px;">
  <h2 style="margin-top:0;">Nejhorší URL (p75)</h2>
  <table class="wp-list-table widefat striped" style="table-layout:fixed;">
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th>URL</th>
        <th style="width:130px;">p75</th>
        <th style="width:180px;">Rating / vzorky</th>
      </tr>
    </thead>
    <tbody id="seob-cwv-urls-tbody">
      <tr><td colspan="4" style="color:#72767d;text-align:center;">Načítám…</td></tr>
    </tbody>
  </table>
</div>

<!-- ── Health status + statistiky ────────────────────────── -->
<div class="seob-two-col" style="gap:16px;">

<div class="seob-card">
  <h2 style="margin-top:0;">Stav modulu</h2>
  <table class="widefat">
    <tr>
      <td>Vzorky za posledních 24h</td>
      <td>
        <strong id="seob-cwv-samples-24h"><?php echo $samples_24h; ?></strong>
        <?php if ($samples_24h === 0): ?>
          <span class="seob-badge seob-badge-warning" style="margin-left:6px;">⚠️ žádná data</span>
        <?php else: ?>
          <span style="color:#1a7f37;margin-left:6px;">✓ aktivní</span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td>Poslední agregace (p75)</td>
      <td id="seob-cwv-last-agg"><?php echo $last_agg ? esc_html( date_i18n( 'Y-m-d H:i', $last_agg ) ) : '<span class="seob-muted">ještě neproběhla</span>'; ?></td>
    </tr>
    <tr>
      <td>Příští agregace (cron)</td>
      <td><?php $next_agg = wp_next_scheduled( SEOB_CWV_Aggregator::CRON_HOOK ); echo $next_agg ? esc_html( date_i18n( 'Y-m-d H:i', $next_agg ) ) : '<span class="seob-muted">—</span>'; ?></td>
    </tr>
    <tr>
      <td>Celkem surových záznamů</td>
      <td><?php echo number_format( $total_raw ); ?></td>
    </tr>
    <tr>
      <td>Nejstarší záznam</td>
      <td><?php echo $oldest_raw ? esc_html( $oldest_raw ) : '—'; ?></td>
    </tr>
    <tr>
      <td>Nejnovější záznam</td>
      <td><?php echo $last_raw ? esc_html( $last_raw ) : '—'; ?></td>
    </tr>
    <tr>
      <td>Denní p75 záznamů</td>
      <td><?php echo number_format( $total_daily ); ?></td>
    </tr>
  </table>

  <div style="margin-top:16px;padding-top:16px;border-top:1px solid #dcdcde;">
    <p style="margin:0 0 8px;color:#555;font-size:13px;">Agregace počítá p75 z posledního dne surových dat a aktualizuje grafy. Cron ji spouští každou noc automaticky – zde ji lze vyvolat ručně.</p>
    <button id="seob-cwv-run-agg-btn" class="button button-primary">&#9654; Spustit agregaci nyní</button>
    <span id="seob-cwv-agg-msg" class="seob-success" style="display:none;margin-left:12px;"></span>
  </div>
</div>

<div class="seob-card">
  <h2 style="margin-top:0;">Nastavení retence dat</h2>
  <form id="seob-cwv-settings-form">
    <table class="form-table">
      <tr>
        <th>Surová data – uchovat (dní)</th>
        <td>
          <input type="number" name="raw_retention_days" min="7" max="365"
                 value="<?php echo (int)($cfg['raw_retention_days'] ?? 90); ?>" class="small-text">
          <p class="description">Raw beacony jsou odstraněny po tomto počtu dní. Doporučeno: 90.</p>
        </td>
      </tr>
      <tr>
        <th>Denní agregáty – uchovat (dní)</th>
        <td>
          <input type="number" name="daily_retention_days" min="30" max="730"
                 value="<?php echo (int)($cfg['daily_retention_days'] ?? 365); ?>" class="small-text">
          <p class="description">Denní p75 záznamy jsou odstraněny po tomto počtu dní. Doporučeno: 365.</p>
        </td>
      </tr>
    </table>
    <button type="submit" class="button button-primary">Uložit nastavení</button>
    <span id="seob-cwv-settings-msg" class="seob-success" style="display:none;margin-left:12px;"></span>
  </form>

  <div style="margin-top:24px;padding-top:16px;border-top:1px solid #dcdcde;">
    <h3 style="margin-top:0;">O měřených metrikách</h3>
    <dl style="font-size:13px;line-height:1.6;">
      <dt><strong>LCP</strong> – Largest Contentful Paint</dt>
      <dd>Čas do vykreslení největšího obsahu. Cíl: &lt; 2,5 s.</dd>
      <dt><strong>INP</strong> – Interaction to Next Paint</dt>
      <dd>Odezva na interakci. Cíl: &lt; 200 ms.</dd>
      <dt><strong>CLS</strong> – Cumulative Layout Shift</dt>
      <dd>Vizuální stabilita. Cíl: &lt; 0,1.</dd>
      <dt><strong>FCP</strong> – First Contentful Paint</dt>
      <dd>Čas do prvního vykreslení. Cíl: &lt; 1,8 s.</dd>
      <dt><strong>TTFB</strong> – Time to First Byte</dt>
      <dd>Čas serveru na první byte. Cíl: &lt; 800 ms.</dd>
    </dl>
  </div>
</div>

</div><!-- .seob-two-col -->

<!-- ── Průvodce CWV pro začátečníky ──────────────────────── -->
<div class="seob-card" style="margin-top:16px;">
  <h2 style="margin-top:0;">&#128218; Co jsou Core Web Vitals a proč je řešit</h2>

  <details open style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Co jsou Core Web Vitals?</summary>
    <div style="padding:12px 0 0 16px;line-height:1.7;color:#333;">
      <p>Core Web Vitals jsou <strong>3 + 2 metriky rychlosti a komfortu</strong>, které Google měří u každé stránky a od roku 2021 je zahrnul přímo do svého hodnotícího algoritmu. Čím lepší skóre, tím větší šance na lepší pozice ve výsledcích vyhledávání.</p>
      <p>Tento modul měří vaše <strong>skutečné návštěvníky</strong> (RUM = Real User Monitoring) – ne laboratorní simulaci, ale reálná data z jejich prohlížečů. To je přesně to, co Google vidí a hodnotí.</p>
      <table class="widefat" style="max-width:640px;margin-top:10px;">
        <thead><tr><th>Metrika</th><th>Co měří</th><th style="width:100px">Dobrý cíl</th></tr></thead>
        <tbody>
          <tr><td><strong>LCP</strong></td><td>Jak rychle se načte největší prvek stránky (hero obrázek, banner)</td><td style="color:#1a7f37;">&lt; 2,5 s</td></tr>
          <tr><td><strong>INP</strong></td><td>Jak rychle stránka reaguje na klik nebo stisk klávesy</td><td style="color:#1a7f37;">&lt; 200 ms</td></tr>
          <tr><td><strong>CLS</strong></td><td>Jak moc stránka "poskakuje" při načítání (obrázky posouvají text)</td><td style="color:#1a7f37;">&lt; 0,1</td></tr>
          <tr><td><strong>FCP</strong></td><td>Kdy uživatel poprvé uvidí jakýkoliv obsah</td><td style="color:#1a7f37;">&lt; 1,8 s</td></tr>
          <tr><td><strong>TTFB</strong></td><td>Jak rychle server odpoví (čistý serverový čas)</td><td style="color:#1a7f37;">&lt; 800 ms</td></tr>
        </tbody>
      </table>
    </div>
  </details>

  <details style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Proč to řešit a co vám to přinese?</summary>
    <div style="padding:12px 0 0 16px;line-height:1.7;color:#333;">
      <p><strong>Rizika pokud CWV ignorujete:</strong></p>
      <ul style="margin-left:20px;">
        <li>Google znevýhodní váš web oproti konkurenci s lepšími CWV – i při stejném obsahu</li>
        <li>Pomalé LCP = návštěvníci odejdou dřív, než stránka doběhne (bounce rate roste)</li>
        <li>Vysoké CLS = uživatelé klikají omylem na špatné prvky → frustrace, odchody</li>
        <li>Pomalý INP = web působí "mrtvě" → zákazník si produkt nepřidá do košíku</li>
      </ul>
      <p style="margin-top:12px;"><strong>Co vám zlepšení přinese:</strong></p>
      <ul style="margin-left:20px;">
        <li>Lepší pozice ve vyhledávání (CWV jsou přímý ranking faktor)</li>
        <li>Vyšší konverzní poměr – rychlý web = více prodejů a poptávek</li>
        <li>Nižší míra okamžitého opuštění (bounce rate)</li>
        <li>Lepší hodnocení v Google Search Console</li>
        <li>Úspora crawl budgetu – Googlebot efektivněji indexuje rychlé weby</li>
      </ul>
    </div>
  </details>

  <details id="seob-cwv-fixes" style="margin-bottom:0;">
    <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">&#9654; Jak opravit jednotlivé metriky (podrobný návod)</summary>
    <p style="color:#888;font-size:12px;margin:4px 0 16px 0;">Kliknutím na "Jak opravit" v diagnostickém panelu výše se přeskrolujete přímo na konkrétní metriku.</p>
    <div style="line-height:1.7;color:#333;">

      <div id="seob-cwv-fix-lcp" style="background:#fff3cd;border-left:4px solid #e65100;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; LCP &gt; 2,5 s – Largest Contentful Paint (nejčastější problém)</strong>
        <p style="margin:6px 0 4px;">Největší prvek stránky (obvykle hero obrázek nebo banner) se načítá pomalu. Uživatel čeká na prázdnou obrazovku.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit (od nejúčinnějšího):</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Nastavte správné načítání hero obrázku:</strong> Hlavní obrázek stránky NESMÍ mít <code>loading="lazy"</code>. Přidejte mu <code>fetchpriority="high"</code>. Tento jeden krok nejčastěji zlepší LCP o 0,5–1 s.</li>
          <li><strong>Konvertujte obrázky na WebP:</strong> WebP je 2× menší než JPEG při stejné kvalitě. WP Rocket, Imagify nebo Smush to udělají automaticky.</li>
          <li><strong>Preload hero obrázku:</strong> Do <code>&lt;head&gt;</code> přidejte: <code>&lt;link rel="preload" as="image" href="URL-obrazku.webp"&gt;</code>. Řekne prohlížeči, aby ho stáhl jako první.</li>
          <li><strong>Aktivujte stránkový cache:</strong> WP Rocket, LiteSpeed Cache nebo W3 Total Cache uloží stránku jako statické HTML – server odpoví 10× rychleji.</li>
          <li><strong>Aktivujte CDN:</strong> Statické soubory (obrázky, CSS, JS) se servírují ze serveru nejblíže návštěvníkovi. Cloudflare je zdarma.</li>
          <li><strong>Zkontrolujte hosting:</strong> Sdílený hosting bývá přetížený. Zvažte přechod na VPS nebo managed WordPress hosting (Kinsta, WP Engine, SiteGround).</li>
        </ol>
      </div>

      <div id="seob-cwv-fix-inp" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; INP &gt; 200 ms – Interaction to Next Paint</strong>
        <p style="margin:6px 0 4px;">Stránka pomalu reaguje na interakce uživatele (klik, stisk klávesy). Způsobuje to příliš mnoho nebo příliš těžký JavaScript.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Odstraňte nebo odložte zbytečné JS pluginy:</strong> Každý WordPress plugin přidává JavaScript. Zkontrolujte v DevTools (záložka Performance) které skripty blokují odezvu nejdéle. Plugin Query Monitor vám ukáže počet dotazů a skriptů.</li>
          <li><strong>Aktivujte "Delay JavaScript" ve WP Rocket:</strong> Odloží načítání JS na po interakci uživatele – stránka bude reagovat okamžitě.</li>
          <li><strong>Zkontrolujte consent plugin (cookies lišta):</strong> Těžké GDPR pluginy (OneTrust, Cookiebot) mohou výrazně zpomalit INP. Zvažte lehčí alternativu.</li>
          <li><strong>Aktualizujte Elementor a ostatní pluginy:</strong> Nové verze page builderů generují obecně lehčí JS než starší verze.</li>
        </ol>
      </div>

      <div id="seob-cwv-fix-cls" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; CLS &gt; 0,1 – Cumulative Layout Shift</strong>
        <p style="margin:6px 0 4px;">Stránka "poskakuje" při načítání – prvky se přesouvají, protože prohlížeč nezná jejich rozměry předem.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Přidejte rozměry ke všem obrázkům:</strong> Každý obrázek musí mít atributy <code>width</code> a <code>height</code>: <code>&lt;img width="800" height="600" ...&gt;</code>. WordPress od verze 5.5 to přidává automaticky – zkontrolujte téma.</li>
          <li><strong>Vyhraďte místo pro reklamy a bannery:</strong> Pokud se banner načítá dynamicky, vyhraďte mu místo v CSS pomocí <code>min-height</code>, aby ostatní prvky neposkočily.</li>
          <li><strong>Nastavte font-display: swap pro vlastní fonty:</strong> Zabraňuje skoku textu při přepnutí z fallback fontu na vlastní. Přidejte do CSS souboru fontu.</li>
          <li><strong>Vyhněte se animacím, které posouvají obsah:</strong> Animace by měly používat <code>transform</code> a <code>opacity</code>, ne <code>top/left/margin</code>.</li>
          <li><strong>Zkontrolujte embedded videa a mapy:</strong> Vložená YouTube videa nebo Google Maps způsobují CLS. Použijte obalující kontejner s pevným poměrem stran (<code>aspect-ratio: 16/9</code>).</li>
        </ol>
      </div>

      <div id="seob-cwv-fix-fcp" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:16px;border-radius:0 4px 4px 0;">
        <strong>&#9888; FCP &gt; 1,8 s – First Contentful Paint</strong>
        <p style="margin:6px 0 4px;">Uživatel příliš dlouho čeká, než uvidí první obsah. Nejčastěji způsobeno pomalým serverem nebo blokujícími CSS/JS soubory v hlavičce.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Aktivujte stránkový cache</strong> (viz LCP bod 4 výše) – největší vliv na FCP.</li>
          <li><strong>Minifikujte CSS a JS:</strong> WP Rocket, LiteSpeed Cache nebo Autoptimize sloučí a zmenší soubory, odstraní render-blocking resources.</li>
          <li><strong>Inline kritické CSS:</strong> CSS potřebné pro "above the fold" (první pohled bez scrollování) vložte přímo do <code>&lt;head&gt;</code>. WP Rocket to zvládne automaticky funkcí "Optimize CSS delivery".</li>
          <li><strong>Preload klíčových fontů:</strong> <code>&lt;link rel="preload" as="font" href="..." crossorigin&gt;</code> v <code>&lt;head&gt;</code> zajistí, že se fonty načtou dříve.</li>
        </ol>
      </div>

      <div id="seob-cwv-fix-ttfb" style="background:#fff3cd;border-left:4px solid #f59e0b;padding:10px 14px;margin-bottom:0;border-radius:0 4px 4px 0;">
        <strong>&#9888; TTFB &gt; 800 ms – Time to First Byte</strong>
        <p style="margin:6px 0 4px;">Server reaguje příliš pomalu – ještě než prohlížeč začne stahovat stránku, čeká na odpověď. Může být způsobeno pomalým hostingem nebo náročnými databázovými dotazy.</p>
        <p style="margin:4px 0 2px;"><strong>Jak opravit:</strong></p>
        <ol style="margin:4px 0 0 18px;">
          <li><strong>Aktivujte stránkový cache:</strong> Stránka uložená jako statické HTML se servíruje okamžitě (TTFB pod 100 ms) bez spouštění PHP a databázových dotazů. Nejvýznamnější zlepšení TTFB.</li>
          <li><strong>Aktivujte object cache (Redis / Memcached):</strong> Ukládá výsledky databázových dotazů do paměti RAM. Dostupné u kvalitních hostingů (Kinsta, WP Engine, SiteGround). WP Redis nebo W3TC to nastaví.</li>
          <li><strong>Zkontrolujte pomalé pluginy:</strong> Plugin Query Monitor ukáže, které pluginy dělají nejvíce databázových dotazů. Odstraňte nebo nahraďte nejpomalejší.</li>
          <li><strong>Zvažte přechod na lepší hosting:</strong> Sdílený hosting (pod 5 €/měsíc) bývá přetížený a TTFB má 1–3 s. VPS nebo managed WP hosting má TTFB typicky pod 200 ms.</li>
          <li><strong>Aktivujte Cloudflare:</strong> I bezplatný plán Cloudflare výrazně zkrátí TTFB díky anycast síti a cachování na hraničních serverech.</li>
        </ol>
      </div>

    </div>
  </details>
</div>

</div><!-- .wrap -->
