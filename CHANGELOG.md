# Changelog – SEO Booster Pro

Všechny výrazné změny jsou dokumentovány v tomto souboru.
Formát dle [Keep a Changelog](https://keepachangelog.com/cs/1.0.0/).

## [1.2.0] – 2026-06-23

### Přidáno
- **Internal Links: per-group výběr** – každá záložka v sekcích Osamocené stránky i Všechny stránky má tlačítka přímo v záhlaví skupiny: **Vybrat vše**, **↑ Chybějící** (jen stránky s `link_status = low`), **↓ Přebývající** (jen `high`)
- **Internal Links: bulk insert dávkování** – výběr libovolného počtu stránek je nyní zpracován v dávkách po 50 zasílaných sekvenčně; tlačítko ukazuje průběh „Dávka X / Y"; dříve se zpracovalo jen prvních 50 z celého výběru

### Opraveno
- Bulk insert ignoroval stránky 51+ při výběru více než 50 položek

## [0.9.2] – 2026-06-22

### Přidáno
- **Redirect Manager: hromadný import z CSV** – nová sekce na stránce Přesměrování. Nahrání CSV souboru (čárka nebo středník, volitelná hlavička) vytvoří nebo aktualizuje přesměrování hromadně. Výsledek ukazuje počet vytvořených / aktualizovaných / přeskočených + chyby na konkrétních řádcích. Max 2 MB, MIME validace.

## [0.9.1] – 2026-06-22

### Bezpečnost (kompletní penetrační audit 96 PHP souborů)
- **`sslverify` podmíněné podle prostředí** – nová `SEOB_Settings::is_local_environment()` detekuje `.local`, `localhost`, privátní IP. Na produkci se SSL certifikáty ověřují (dříve bylo `sslverify=false` globálně). Přepis možný přes `define('SEOB_SSLVERIFY', true/false)` v `wp-config.php`.
- **Cookie filtering v loopback requestech** – `Audit/Scanner.php` nyní předává pouze `wordpress_*` auth cookies (dříve všechny `$_COOKIE` včetně tracking cookies třetích stran)
- **Globální per-IP rate limit v JS Render Gap beaconu** – `BeaconReceiver.php` přidán limit 200 snapshotů/hod per IP (brání zálavě transienty přes různé URL)
- **SSRF ochrana AI endpointu** – `OpenAiCompatibleProvider.php` blokuje non-http(s) protokoly a privátní IP rozsahy (AWS metadata 169.254.x.x, RFC 1918)
- Potvrzeno OK: ABSPATH ve všech 96 souborech, nonce+capability checks všechny AJAX handlery, `$wpdb->prepare()` všude kde jsou user data, output escaping v templates, žádné `eval()`/`exec()`/`unserialize()` na user datech

## [0.9.0] – 2026-06-17

### Přidáno
- **M12: JS Render Gap detektor** (`includes/JsRenderGap/`) – nový modul `js-render-gap` (výchozí vypnuto):
  - `BeaconReceiver.php` – REST endpoint `POST /wp-json/seo-booster/v1/js-gap`; přijímá DOM snapshoty z frontend beaconu; rate limit 1×/24h per URL per IP hash
  - `Comparator.php` – porovnává rendered DOM snapshot s raw HTML (staženo přes `wp_remote_get`); detekuje: H1 chybí v raw, title/meta description mismatch, JSON-LD gap, text ratio kritický/varování; vypočítá gap score 0–100
  - `ScanRunner.php` – dávkové porovnání (cron `seob_js_gap_scan`, pondělí 03:30 UTC, max 10 URL/dávka); on-demand analýza jedné URL; zapisuje metriky `pages_with_gap` a `avg_gap_score` do `seo_booster_metrics`
  - `Ajax.php` – 4 AJAX handlery: statistiky, výsledky (filtry + stránkování), on-demand analýza jedné URL, spuštění batch scanu
  - `assets/js/js-render-gap-beacon.js` – lightweight frontend beacon (< 1.5 kB); zachytí DOM po DOMContentLoaded + 800ms zpoždění; localStorage rate limit 7 dní per URL; odesílá: title, h1, headings, meta desc, JSON-LD count, text length, links count
  - `templates/admin/page-js-render-gap.php` – admin dashboard se stat boxy, filtry (kritické/varování/OK), výsledkovou tabulkou a legendou skóre
  - `assets/admin/js/js-render-gap.js` – dashboard JS: stats, filtry, výsledky s inline re-analýzou, stránkování
  - `docs/modules/js-render-gap.md` – dokumentace dle spec (proč / co se zlepší / monitoring / health check tabulka)
  - DB tabulky: `seo_booster_js_gap_snapshots` (rendered DOM data z beaconu) + `seo_booster_js_gap_results` (výsledky porovnání + gap score)
  - Gap skóre: 0–19 = OK, 20–49 = varování, 50–100 = kritické

## [Unreleased]

### Přidáno
- **Interní prolinkování / Internal Link Assistant** (`includes/InternalLinks/`):
  nový modul `internal-links` (výchozí vypnuto). Indexuje interní link graf
  webu (`extract_links()`, 3-strategie extrakce obsahu jako Audit
  Dashboard), detekuje osamocené (orphan) stránky bez příchozího interního
  odkazu a navrhuje top 3 nejpodobnější stránky pro prolinkování pomocí
  **lokálního TF-IDF + kosinové podobnosti** (žádná externí AI/API). Nová
  stránka „Interní prolinkování“ (`seob-internal-links`) s dávkovým
  reindexem (progress bar, vzor PageSpeed), souhrnem (počet stránek, orphans
  + trend, průměr interních odkazů + trend), tabulkami osamocených a všech
  stránek. Postranní metabox v editoru s návrhy „odkázat z“. 3 nové DB
  tabulky (`internal_links`, `link_suggestions`, `link_scans`,
  `SEOB_DB_VERSION` → 0.6.0), health check, nové PHPUnit testy
  (`ExtractorTest`, `SimilarityTest`) – **56 testů, 170 assertions, OK**.
  Detaily v `docs/modules/internal-links.md`.
- **PageSpeed Insights (Lighthouse)** (`includes/PageSpeed/`): nový modul
  `pagespeed` – pro každý veřejný typ obsahu s publikovanými stránkami
  vybere náhodný vzorek (5 položek), otestuje přes Google PageSpeed
  Insights API (mobil i desktop) a shrne průměrné skóre
  (Performance/Accessibility/Best Practices/SEO) + nejčastější SEO nálezy
  s popisem. Dávkový scan na vyžádání s progress barem (vzor Audit scan),
  historie posledních 10 běhů s porovnáním (delta) oproti předchozímu
  běhu. Nová stránka „PageSpeed Insights“ (`seob-pagespeed`), API klíč
  šifrovaný v DB (`SEOB_AiQueue_Crypt`), health check, dokumentace v
  `docs/modules/pagespeed.md` (vč. návodu na získání free API klíče).
  Navíc nová karta **„Celkový přehled webu“** – vážený průměr skóre přes
  všechny typy obsahu (mobil/desktop) s trendem oproti minulému běhu
  (`SEOB_PageSpeed_ScanRunner::compute_overall_scores()`).
- **Audit Dashboard – trend skóre vs. minulý scan**: celkové skóre,
  skóre po kategoriích i po jednotlivých stránkách nyní zobrazují `▲/▼`
  s tooltipem, který popisuje konkrétní zlepšení/zhoršení (`new_issues`,
  `resolved_issues`, `group_score_deltas`, `group_issue_changes`).
- **AI schvalovací fronta** (`includes/AiQueue/`): nový modul `ai-queue`
  (výchozí vypnuto, `depends_on: ['audit']`). Generický OpenAI-compatible
  AI adaptér (`SEOB_AiQueue_OpenAi_Compatible_Provider`, funguje např. s
  free-tier Google Gemini / Groq endpointy), API klíč šifrovaný v DB
  (AES-256-CBC, `SEOB_AiQueue_Crypt`). V Audit Dashboardu tlačítka
  „Navrhnout pomocí AI“ (title/description) a „Navrhnout alt texty obrázků“
  vloží návrh do `seo_booster_ai_queue` se stavem `pending` – nic se
  nezapisuje automaticky. Nová stránka „AI fronta“ (`seob-ai-queue`) pro
  schválení/zamítnutí návrhů, health check `ai_queue_checks()`. Nové
  PHPUnit testy (`CryptTest`, `PromptBuilderTest`) – **28 testů, 84
  assertions, OK**. Detaily v `docs/modules/ai-queue.md`.
- **Composer + PHPUnit scaffolding**: `composer.json` (dev závislosti
  PHPUnit + Brain Monkey, vlastní `vendor-dev/` adresář, aby se nemíchal s
  ručně vendorovanými knihovnami v `vendor/`), `phpunit.xml.dist`,
  `tests/bootstrap.php` + `tests/TestCase.php` a první unit testy pro čistou
  logiku (`ReportData::logo_dimensions_mm()`/`issue_labels()`,
  `Document::hex_to_rgb()`, `ModuleManager::MODULES` integrita,
  `GscInsights::normalize_path()`). Spuštění: `composer install && composer
  test`. **Ověřeno: 20 testů, 55 assertions, OK.** Detaily v
  `docs/testing.md`.

### Opraveno
- **Audit scan**: skenuje dynamicky všechny veřejné post typy webu
  s publikovaným obsahem (vč. custom post typů jako „Slovíček pojmů“),
  místo natvrdo `post`/`page` (`SEOB_Audit_ScanRunner::get_audit_post_types()`).
- **Audit scan – kontrola obsahu (thin content)** u custom post typů, které
  ukládají text do meta polí místo `post_content` (např. JetEngine CPT
  „Slovíček pojmů“), dřív vždy hlásila 0 slov – `Scanner::collect_meta_content()`
  nyní obsah poskládá z textových meta polí.
- **Audit Dashboard – skóre skupiny** se po zapnutí trendů zobrazovalo jako
  absurdní čísla (`2348275025` apod.) – `row.score` z DB je string a `+`
  dělal konkatenaci, opraveno převodem na `Number` v `loadResults()`. Skupina
  má nově i popisek „SEO skóre“.
- **Export PDF reportu**: logo agentury se v hlavičce stránek nezobrazovalo,
  pokud admin nenahrál vlastní logo v Export – nastavení (`company.logo_id`
  bylo `0`) – `SEOB_Pdf_Report_Data::build()` nyní jako fallback použije logo
  webu (Vzhled → Přizpůsobit → Logo, `get_theme_mod('custom_logo')`). Odsazení
  textu nálezu od barevného levého okraje karty zvětšeno z `14px` na `6mm`
  (`.seob-pdf-issue` v `templates/pdf/report.php`).
- **Export PDF reportu**: logo v sekci „Vystavil“ a v hlavičce stránek se
  nově vykresluje maximálně ve své přirozené velikosti (přepočet z px přes
  `getimagesize()` při 150 DPI, `SEOB_Pdf_Report_Data::logo_dimensions_mm()`)
  místo roztažení na pevný/CSS box, takže nevypadá rozmazaně/pixelovaně.
  Doplněno chybějící odsazení od barevné čáry vlevo i v boxu „Souhrn auditu“
  a v položkách „Souhrn dopadů a přínosů zlepšení“ (`6mm`).

### Přidáno
- **Nový modul „Search Console statistiky (Rank Math)“**: Audit Dashboard
  nově zobrazuje 4 nové sloupce (Zobrazení, Kliky, CTR, Pozice) za posledních
  28 dní – data se čtou přímo z vlastní tabulky Rank Math
  `wp_rank_math_analytics_gsc` (žádné vlastní Google API/OAuth). Pokud Rank
  Math nemá připojený modul Analytics se Search Console, zobrazí se v
  dashboardu informační notice s odkazem na nastavení Rank Math a sloupce
  jsou skryté. Nový modul `gsc-insights` (Nastavení → Moduly, výchozí
  zapnuto, závisí na Audit Dashboardu) + health check ve „Stav systému“.
  Detaily v `docs/modules/gsc-insights.md`.
- **Export PDF reportu – redesign (`SEOB_VERSION` → `0.3.0`)**: oprava
  zobrazení emoji v titulku PDF (font DejaVu Sans nezvládá znaky nad U+FFFF,
  např. 🏢 před názvem webu – nyní se odstraní, `SEOB_Pdf_Report_Data::sanitize_text()`).
  Report nyní obsahuje plnou kartu s popisem nálezů jen pro N nejhůře
  hodnocených stránek (nastavitelné v Export – nastavení → Rozsah reportu,
  výchozí 12), zbylé stránky se shrnou do tabulky „Souhrn nálezů podle typu“
  s přílohou konkrétních URL – vhodné i pro audity 100+ stránkových webů.
  Export reportu nově umožňuje vyplnit měsíční návštěvnost, konverzní poměr
  a hodnotu objednávky/leadu – PDF pak doplní orientační odhad dopadu
  zlepšení SEO na návštěvnost, konverze a tržby (`compute_business_impact()`).
  Export – nastavení → nová sekce „Firemní údaje a branding“: upload loga
  agentury (media uploader) a barevný akcent, použité v záhlaví, nadpisech
  a patičce PDF. Detaily v `docs/modules/pdf-export.md`.
- **Export PDF reportu – hlavičkový papír a sekce „Vystavil“**: logo agentury
  a název webu se nově zobrazují v záhlaví KAŽDÉ stránky PDF (ne jen titulní),
  spolu s akcentovou linkou; v patičce každé stránky je název firmy a číslo
  stránky (`SEOB_Pdf_Document extends TCPDF`). Větší odsazení textu nálezu od
  barevného levého okraje karty. Nová konsolidovaná sekce „Souhrn dopadů a
  přínosů zlepšení“ shrnuje dopad/přínos za všechny typy nálezů na webu. Nová
  sekce „Vystavil“ na konci reportu s logem, jménem zpracovatele
  (`pdf_company_contact_person`), firmou, IČO (`pdf_company_ico`) a kontaktem
  – editovatelné v Export – nastavení → Firemní údaje a branding.
- Vypnutý modul zmizí i z admin menu pluginu (Audit Dashboard, Chytrá
  indexace, Přesměrování, Export reportu, Export – nastavení) – položky se
  zobrazují jen pro aktivní moduly (`SEOB_Module_Manager::is_active()`).
  „Stav systému“ a „Nastavení“ zůstávají vždy dostupné, aby šlo modul znovu
  zapnout. Přímý přístup na URL vypnuté stránky zobrazí notice s odkazem na
  zapnutí (`templates/admin/page-module-disabled.php`).
- Stránka „Chytrá indexace“: sbalitelná nápověda vysvětlující Tier A/B/C,
  význam sloupců tabulky výsledků a tlačítek Schválit/Noindex, + popisky u
  každého nastavení (profil, mapování CPT/taxonomií), co konkrétně dělají a
  na co si dát pozor.
- **Stav systému**: tabulka modulů má nový sloupec „Akce“ s tlačítkem
  „Zapnout“/„Vypnout“ – jednoklikové (de)aktivování libovolného modulu přes
  AJAX (`seob_status_toggle_module`), bez nutnosti chodit do Nastavení a
  klikat „Uložit“. Platí pro všechny moduly.
- **Nový modul „Chytrá indexace“ (M14, MVP)**: vyhodnocuje kombinace
  obor/služba × lokalita a detaily firem v katalogu a navrhuje, které
  stránky indexovat (Tier A), které dát do noindexu (Tier B), a které
  konsolidovat canonicalem bez utility parametrů (Tier C). Generický a
  konfigurovatelný – v Nastavení → Chytrá indexace si admin namapuje typ
  obsahu pro detail firmy a taxonomie obor/lokalita/služba. Nová stránka
  „Chytrá indexace“ s analýzou (`SEOB_SmartIndexing_Catalog_Scanner`),
  přehledem návrhů a tlačítky Schválit/Noindex. Frontend integrace s Rank
  Math (`rank_math/frontend/canonical`, `rank_math/frontend/robots`) –
  aktivní mimo režim Dry-run. Nové tabulky `seo_booster_facet_rules`,
  `seo_booster_facet_urls`, `seo_booster_facet_signals` (`SEOB_DB_VERSION`
  → `0.4.0`). Modul je ve výchozím nastavení vypnutý (Nastavení → Moduly).
  Detaily v `docs/modules/smart-indexing.md`. `SEOB_VERSION` → `0.2.0`.
- Nastavení – tabulky „Výchozí schéma podle typu obsahu / kategorie“: nová volba
  „Výchozí (dle Rank Math…)“ jako první položka výběru – znamená, že SEO Booster Pro
  do schématu vůbec nezasahuje a platí čistě nastavení Rank Math (u typů obsahu se
  navíc zobrazí, jaký typ schématu je u Rank Math aktuálně nastaven jako výchozí).
  Tlačítko „Resetovat“ u každého řádku jedním kliknutím zruší vlastní override
  SEO Booster Pro a vrátí se k tomuto výchozímu chování (`SEOB_Schema_PostType_Ajax`,
  `SEOB_Schema_Category_Ajax`, `SEOB_Schema_Helper::get_rank_math_post_type_default()`).
  Dříve se neuložený řádek v UI tvářil jako explicitní volba „Běžná stránka“ (`off`),
  což mohlo při „Uložit vše“ omylem přepsat nastavení Rank Math.
- Audit Dashboard: tlačítko „Smazat scan“ u historie scanů pro ruční mazání starých
  běhů (`seob_scan_delete`). Nastavení → Audit Dashboard: nová volba „Historie scanů“
  (`history_limit`, výchozí 20) – po dokončení nového scanu se automaticky smažou
  nejstarší dokončené scany nad tento limit (`SEOB_Audit_ScanRunner::prune_history()`).
- **Společná infrastruktura**: `SEOB_Module_Manager` (registr modulů audit/
  redirects/pdf, závislosti, detekce Rank Math) nahrazuje ad-hoc inicializaci
  v `Plugin.php`. Nová tabulka `seo_booster_metrics` (`SEOB_DB_VERSION` →
  `0.3.0`) + `SEOB_Metrics` pro ukládání trendů (`audit.score_avg`,
  `redirects.unresolved_404_count`, `pdf.export_count`). Nová stránka
  **Stav systému** (`seob-status`) s health checky jednotlivých modulů a
  sparkline grafy trendů, integrovaná i do WP Site Health (Nástroje →
  Stav webu). Dokumentace modulů v `docs/modules/*.md`.
- Audit Dashboard: nový sloupec „Obsah“ zobrazuje, zda stránka splňuje minimální počet
  slov (thin content) – dosud byl tento nález vidět jen po rozkliknutí řádku.
- Nastavení – „Výchozí schéma podle typu obsahu/kategorie“: nově lze zvolit i
  „Běžná stránka (bez schématu / výchozí WebPage)“ (`TYPES['off']`) jako explicitní volbu.
  Tato volba skutečně přebije výchozí nastavení Rank Math pro daný typ obsahu/kategorii
  (`SEOB_Schema_Helper::filter_rich_snippet_meta()`), aniž by se zapisovalo do nastavení
  nebo postmeta Rank Math. Audit Dashboard u takto označených stránek už nehlásí
  „Chybí strukturovaná data“.
- Audit Dashboard: nový sloupec „Od minula“ a souhrn ukazují, které nálezy byly opravené
  od předchozího scanu (porovnání `issues` mezi aktuálním a minulým dokončeným scanem
  pro stejný příspěvek).
- Nastavení – tabulky schémat (typ obsahu i kategorie): tlačítko „Uložit vše“ pro hromadné uložení
  všech řádků jedním kliknutím.
- Nastavení – tabulky schémat (typ obsahu i kategorie): proklik na seznam příslušných příspěvků
  v adminu, ikona nápovědy u výběru schématu s popisem typu a kdy jej použít, a nová sbalitelná
  sekce „Schéma (strukturovaná data)“ vysvětlující prioritní pořadí pravidel a přehled všech typů.
- Nastavení: nová sekce „Výchozí schéma podle typu obsahu“ – umožňuje nastavit výchozí schéma
  pro celý typ obsahu (např. Příspěvky → Article, Landing page → Service), včetně vlastních
  post types bez kategorií (např. Crocoblock/JetEngine glosáře). Toto pravidlo je obecnější než
  „Výchozí schéma podle kategorie“, které má i nadále přednost (`SEOB_Schema_Helper::get_post_type_default()`,
  nový AJAX `SEOB_Schema_PostType_Ajax`, option `seob_default_schema_post_types`).
  JS logika obou tabulek (typ obsahu i kategorie) sjednocena do `assets/admin/js/schema-defaults.js`
  (dříve `schema-categories.js`).
- Export PDF reportu z auditu: nová stránka „Export reportu“ (`seob-report`), tlačítko „Export PDF“
  v Audit Dashboardu. Report obsahuje shrnutí auditu, popis nálezů s dopadem (pokud se neřeší) a přínosem
  (po opravě) a obchodní nabídku ve 3 variantách dle průměrného skóre (`maintenance`/`standard`/`comprehensive`).
  Texty nálezů, šablony nabídek i firemní údaje jsou editovatelné na samostatné stránce
  „Export – nastavení“ (`seob-pdf-settings`, ukládá se přes `seob_save_pdf_settings`),
  úvodní shrnutí a vybraná nabídka jsou editovatelné na stránce Export reportu před stažením PDF.
  PDF se generuje serverově přes vendorovaný TCPDF (`vendor/tcpdf/`, font DejaVuSans pro diakritiku),
  bez zápisu na disk (`SEOB_Pdf_Renderer::render()` → `Output('', 'S')` → přímé stažení přes `wp_ajax_seob_pdf_export`).
  Nový modul `modules.pdf` (zapnuto výchozí). Načítání dat reportu má 20s timeout s viditelnou
  chybovou hláškou při selhání a progress barem zobrazujícím uplynulý čas.
- `seo_booster_audit.schema_json` – výsledek scanu nyní ukládá efektivní typ schématu (`type`/`source`),
  dashboard tak po scanu zobrazuje reálná data místo defaultu (`SEOB_DB_VERSION`, `SEOB_Activator::maybe_upgrade()`
  spouští dbDelta migraci i bez reaktivace pluginu)
- Audit Dashboard: výběr historie dokončených scanů (`seob_scan_history` AJAX, `SEOB_Audit_ScanRunner::get_scan_history()`)
  – dropdown "Historie scanů" v toolbaru umožňuje zobrazit výsledky libovolného dřívějšího scanu
- Audit Dashboard: scan na vyžádání (dávkové zpracování s progress barem), skóre 0–100,
  kontroly title/description (chybí, délka v px, duplicity), H1/hierarchie nadpisů,
  alt texty obrázků, schema, noindex, thin content, focus keyword
- Inline editace SERP title/description s živým pixelovým náhledem a uložením do Rank Math meta
- Redirect Manager: log 404 požadavků, vytvoření 301 přesměrování jedním klikem,
  cache mapy přesměrování, denní úklid starých 404 záznamů

## [0.1.0] – 2026-06-10

### Přidáno
- Základní kostra pluginu (bootstrap, Settings, Activator, Plugin orchestrátor)
- DB schéma: `seo_booster_audit`, `seo_booster_scan_runs`, `seo_booster_ai_queue`, `seo_booster_links`
- Admin menu pod skupinou Grou.cz: Audit Dashboard, Přesměrování, Nastavení (placeholdery)
- Tailwind CSS build setup
- Plugin Update Checker (GitHub Releases)
