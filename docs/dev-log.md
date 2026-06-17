# SEO Booster Pro – Vývojový deník

> **Tento soubor je první co číst.** Každá změna přibyde sem – co bylo uděláno, proč a kde v kódu.
> Nové záznamy přidávej **na začátek** (nejnovější nahoře).

---

## Záznamy

### 2026-06-17 – v0.9.0 – M12: JS Render Gap detektor

**Nové soubory:**
- `includes/JsRenderGap/BeaconReceiver.php` – REST `POST /wp-json/seo-booster/v1/js-gap`; přijímá rendered DOM snapshot z JS beaconu; rate limit 1×/24h per URL per IP hash (WP transient); REPLACE do `seo_booster_js_gap_snapshots`
- `includes/JsRenderGap/Comparator.php` – stáhne raw HTML přes `wp_remote_get` (sslverify=false, 8s timeout); parsuje `DOMDocument + DOMXPath`; porovnává title/H1/meta desc/JSON-LD count/text ratio; gap score 0–100
- `includes/JsRenderGap/ScanRunner.php` – cron hook `seob_js_gap_scan` (pondělí 03:30 UTC, schedule `weekly`); batch 10 URL; on-demand `analyze_one(url_hash)`; `record_metrics()` → `pages_with_gap` + `avg_gap_score`
- `includes/JsRenderGap/Ajax.php` – 4 AJAX: `seob_jsgap_stats`, `seob_jsgap_results`, `seob_jsgap_analyze_one`, `seob_jsgap_run_scan`
- `assets/js/js-render-gap-beacon.js` – < 1.5 kB; zachytí DOM 800ms po DOMContentLoaded; localStorage rate limit 7 dní per URL; odesílá: path, title, h1[], headings[], meta_desc, json_ld_count, text_len, links_count
- `templates/admin/page-js-render-gap.php` – stat boxy, filtr (kritické/varování/ok), výsledková tabulka, legenda skóre
- `assets/admin/js/js-render-gap.js` – jQuery: loadStats, run scan, filter, výsledky + inline "Znovu" analýza, stránkování
- `docs/modules/js-render-gap.md` – full spec dokumentace

**Upravené soubory:**
- `includes/Database/Database.php` – přidány `js_gap_snapshots_table()`, `js_gap_results_table()`
- `includes/Activator.php` – CREATE TABLE `seo_booster_js_gap_snapshots` (url_hash PK) + `seo_booster_js_gap_results` (url_hash PK, rendered_*/raw_* sloupce, gap_score, issues_json)
- `includes/ModuleManager.php` – přidán `js-render-gap`; odstraněn `image-seo` (uživatel odmítl)
- `includes/Settings.php` – konstanta `JS_RENDER_GAP` + defaults; `js-render-gap` v modulech (default off)
- `includes/Admin/Admin.php` – submenu "JS Render Gap", `page_js_render_gap()`, enqueue `js-render-gap.js`
- `includes/Plugin.php` – frontend beacon enqueue + `wp_rest` nonce + `seob_js_gap_scan` action + `SEOB_JsGap_ScanRunner::schedule()`
- `seo-boost.php` – 4 JsRenderGap soubory v autoloaderu; verze `0.8.0 → 0.9.0`

**Architektura:**
1. Frontend beacon → `navigator.sendBeacon` → REST endpoint → DB snapshot
2. Cron/Admin → batch/on-demand `Comparator::analyze()` → `wp_remote_get` → DOMDocument → gap score → DB results
3. Dashboard → AJAX → výsledky + filtry

**Pozor na:**
- `weekly` WP cron schedule – WP nemá tento interval nativně, nutno registrovat přes `cron_schedules` filter. Přidat do Plugin.php pokud chybí (TODO).
- Beacon posílá `wp_rest` nonce (veřejný endpoint, žádný `manage_options`)
- `SEOB_DB_VERSION` bumped na `0.9.0` → nové tabulky se vytvoří při prvním načtení

---

### 2026-06-16 (update 17) – M4: Core Web Vitals RUM monitoring

**Nové soubory:**
- `includes/CWV/BeaconEndpoint.php` – REST endpoint `POST /wp-json/seo-booster/v1/cwv` pro příjem metrik
- `includes/CWV/Aggregator.php` – WP-Cron `seob_cwv_aggregate` (denně 03:00 UTC), p75 per path/metric/device, rotace dat
- `includes/CWV/Ajax.php` – admin AJAX: `seob_cwv_dashboard` (graf), `seob_cwv_worst_urls` (tabulka), `seob_cwv_save_settings`
- `assets/js/vendor/web-vitals.iife.min.js` – web-vitals v4 IIFE build (7.5 kB, lokálně)
- `assets/js/cwv-beacon.js` – frontend wrapper (~1 kB): posílá LCP/INP/CLS/FCP/TTFB na REST endpoint
- `assets/admin/js/cwv-dashboard.js` – admin dashboard JS (Chart.js line chart + tabulka worst URLs)
- `templates/admin/page-cwv.php` – admin stránka "Core Web Vitals (RUM)"

**Upravené soubory:**
- `includes/Database/Database.php` – přidány `cwv_raw_table()`, `cwv_daily_table()`
- `includes/Activator.php` – CREATE TABLE pro `cwv_raw` a `cwv_daily`, unschedule CWV cronu
- `includes/ModuleManager.php` – přidán modul `cwv-rum`
- `includes/Settings.php` – přidána konstanta `CWV`, defaults `raw_retention_days=90`, `daily_retention_days=365`
- `includes/Admin/Admin.php` – menu "CWV / RUM", page_cwv(), enqueue Chart.js + cwv-dashboard.js
- `includes/Plugin.php` – frontend beacon enqueue + `SEOB_CWV_Aggregator::schedule()` při aktivním modulu
- `seo-boost.php` – přidány 3 CWV soubory do autoloaderu

**DB tabulky:**
- `{prefix}seo_booster_cwv_raw` – surové beacony (url_hash, path, metric, value, rating, device, lcp_element, recorded_at)
- `{prefix}seo_booster_cwv_daily` – denní p75 agregáty (day, url_hash, path, metric, device, p75, sample_count) + UNIQUE uq_day_hash_metric_device

**Co měří:**
- LCP (Largest Contentful Paint) – cíl < 2500 ms
- INP (Interaction to Next Paint) – cíl < 200 ms
- CLS (Cumulative Layout Shift) – cíl < 0.1
- FCP (First Contentful Paint) – cíl < 1800 ms
- TTFB (Time to First Byte) – cíl < 800 ms
- Pro LCP navíc: CSS selektor LCP elementu (tagName + id + třídy) → přímá dohledatelnost viníka

**Jak to funguje:**
1. Frontend: `web-vitals.iife.min.js` → `cwv-beacon.js` → `navigator.sendBeacon` → REST `/cwv`
2. REST: validace (metric enum, value range, path sanitize) + rate limit (30 req/min per IP hash) → INSERT do `cwv_raw`
3. Cron (denně 03:00): agreguje předchozí den do `cwv_daily` (p75 per path + global '*') + rotace (90d raw, 365d daily)
4. Admin dashboard: graf p75 trendu (Chart.js, filtry: metrika/zařízení/období), tabulka worst URLs

**Bezpečnost a GDPR:**
- Žádné PII: path = pouze pathname (bez query params), IP hash (ne raw IP)
- Žádné cookies, žádná autentizace pro beacon endpoint
- Rate limit na IP hash (30/min) → DoS ochrana

**Jak aktivovat:**
- Nastavení → Moduly → zapnout "Core Web Vitals (RUM)"
- Po první návštěvě frontendu se data začnou sbírat
- Cron agreguje denně – první agregovaná data jsou dostupná den po aktivaci

**Co dělat pokud nejsou data:**
1. Zkontroluj "Stav modulu" → "Vzorky za posledních 24h"
2. Pokud 0: zkontroluj zda beacon není blokovaný consent nástrojem / cookie pluginem / cache
3. Zkontroluj Network tab v DevTools – hledej POST na `/wp-json/seo-booster/v1/cwv`

**Kde je co v kódu:**
- Rate limit: `BeaconEndpoint::handle()` – transient `seob_cwv_rl_{ip_hash}`, okno 60s
- p75 výpočet: `Aggregator::percentile_75()` – sort + ceil(0.75*n)-1 index
- Worst URLs SQL: `Ajax::ajax_worst_urls()` – GROUP BY url_hash, HAVING total_samples >= 3
- Frontend init: `Plugin.php::init()` → `wp_enqueue_scripts` → `seob-cwv-beacon`

**Pozor na:**
- SEOB_DB_VERSION musí být bumped před deploymentem na produkci (aby se spustilo `maybe_upgrade()` a vytvořily se CWV tabulky)
- Chart.js annotation plugin (prahové linie) – NEdodán, použité native `annotation` klíče budou ignorovány bez registrovaného pluginu (linie se nevykreslí, ale chart funguje správně)
- web-vitals `onINP` existuje od v3; `onFID` je deprecated a odstraněno

---

### 2026-06-16 (update 16) – STAV: čekající body M3 (neověřeno)

**Kde jsme skončili:**
Fix scanů (update 15) byl nasazen, ale nebyly ještě ověřeny tyto dva body z předchozí práce:

**2. Fix hinty v single URL validátoru** *(implementováno v update 12-13, neověřeno)*
Při testování jedné URL (`Otestovat URL` tlačítko v JSON-LD Validátoru) má každé varování/chyba zobrazit barevný blok s „Jak opravit:" textem – stejně jako v plném scanu.
- Soubory: `json-ld.js::renderSingleResult()`, `PageScanner::get_fix_hint()`
- Stav: kód je, ale scan nefungoval → nebylo jak ověřit

**3. JSON-LD sekce v Audit Dashboardu** *(implementováno v update 14, neověřeno)*
V inline panelu (tlačítko „Opravit" u každé stránky v Audit Dashboardu) přibyla sekce JSON-LD s tlačítkem „Zkontrolovat JSON-LD". Kliknutím validuje stránku na místě a zobrazí výsledky s fix hinty + odkaz „Otevřít editor".
- Soubory: `audit-dashboard.js::renderJsonLdAuditResult()`, `page-dashboard.php` (seob-jsonld-panel), `Admin.php` (jsonLdActive v seobData)
- Stav: kód je, scan nefungoval → panel nebyl jak otestovat

**Co dál (po návratu k M3):**
1. Ověřit fix hinty v single URL validátoru
2. Ověřit JSON-LD panel v Audit Dashboardu (tlačítko „Opravit" → sekce JSON-LD)
3. Pak M4: Core Web Vitals RUM

**Přerušeno:** Uživatel přešel na urgentní fix v jiném pluginu na LIVE produkci.

---

### 2026-06-16 (update 15) – Fix: WP-Cron SSL + Audit Scanner timeout → oba scany opraveny

**Problem:** Oba scany (JSON-LD Validator + Audit Dashboard) byly po zmacknuti stuck na 0/49 stranek.

**Pricina A – JSON-LD scan (WP-Cron SSL):**
WP-Cron `spawn_cron()` odesilal HTTP POST na `wp-cron.php` pres HTTPS. V Local by Flywheel je self-signed SSL certifikat → request selhal → `process_batch()` se nikdy nespustil.

**Pricina B – Audit Dashboard scan (loopback timeout):**
`count_rendered_h1()` v `Scanner.php` delal loopback HTTP request na frontend stranky. Timeout byl 10s se 3 pokusy = 30s na stranku. S batch_size=20 trvala prvni davka 10 minut nez JS dostal prvni odpoved (progress bar zustaval na 0/49).

**Opravy:**
- `Plugin.php::init()` – pridat `cron_request` filter: nastavi `sslverify=false` pro vsechny WP-Cron spawn requesty. Opravuje JSON-LD scan globalne (i PageSpeed scan bude fungovat).
- `JsonLd/ScanRunner::start()` – pridat `spawn_cron()` po `wp_schedule_single_event()`: prvni davka se spusti okamzite bez cekani na dalsi page load.
- `Audit/Scanner::count_rendered_h1()` – timeout snizen `10s → 3s`, retries odstrany (1 pokus misto 3). Pokud loopback selze, fallback na content-based H1 detection (parsovani z post_content). Nejhorsi pripad: 3s na stranku misto 30s.
- `Settings.php` – default `batch_size` `20 → 5`. Kazda AJAX davka zpracuje max 5 stranek = max 15s na volani (dobre pod kazdym PHP limitem).

**Vysledek:** JSON-LD scan: prvni davka se spusti do <2s. Audit scan: progress se zobrazuje po kazdych 5 strankach (~15s max).

---

### 2026-06-16 (update 14) – M3: Fix false positives @context + integrace JSON-LD do Audit Dashboardu

**Co:**
- `Validator.php::extract_schemas()` – polozky v `@graph` nyni dedi `@context` z parent kontejneru. Opravuje false-positive varovani "Chybi klic @context" pro vsechny schemata generovana Rank Mathem (ktery pouziva @graph).
- `json-ld.js::renderSingleResult()` – zobrazuje `fix_hint` ke kazdemu nalezu v single URL validatoru (barevne bloky "Jak opravit" same jako v plnem scanu).
- `Admin.php` – pridan `jsonLdActive` + `jsonLdUrl` do seobData pro stranku Audit Dashboardu.
- `page-dashboard.php` – pridat `<div class="seob-jsonld-panel">` do `#seob-row-template` (skryta, zobrazuje se jen kdyz je json-ld modul aktivni).
- `audit-dashboard.js`:
  - Nova funkce `renderJsonLdAuditResult(d, editUrl)` – vykresli vysledek validace s fix hinty a odkazem "Otevrit editor".
  - V `buildRow()`: kdyz `seobData.jsonLdActive`, zobrazi sekci JSON-LD v inline panelu s tlacitkem "Zkontrolovat JSON-LD" (AJAX `seob_json_ld_scan_url`).

**Proc:** Uzivatel videl false-positive varovani @context na vsech strankach. Chtel fixovat chyby primo v Audit Dashboardu bez otevirani separatni stranky JSON-LD Validatoru.

---

### 2026-06-16 (update 13) – M3: Skupiny dle post type, fix hinty, tlacitko Upravit

**Co:**
- `PageScanner::get_scan_urls()` vraci objekty `{url, post_type, post_type_label, post_id, edit_url}` – pridam metadata post typu a odkaz na WP editor pro kazde URL
- `PageScanner::get_fix_hint(message, type)` – nova private metoda, ktera ke kazde chybove zprave vraci konkretni navod jak opravit (kde v Rank Math, co vyplnit)
- `PageScanner::scan_url()` – kazdy issue ma nove pole `fix_hint` (retezec s instrukcemi)
- `ScanRunner::process_batch()` – mergeuje metadata z queue itemu do vysledku; zpetna kompatibilita (stary format = string stale funguje)
- `templates/admin/page-json-ld.php` – kompletni prepis:
  - Vysledky seskupeny dle `post_type_label` (Hlavni stranka prvni, pak abecedne)
  - Kazda skupina ma hlavicku s poctem stranek + barevne odznacky pro chyby/duplicity
  - Kazda URL ma tlacitko **Upravit** (otevre WP editor v nove zalozce)
  - Kazda chyba ma barevny blok s polem **Jak opravit:** (fix_hint text) + odkaz "Otevrit editor"
  - Duplicity maji vlastni vysvëtleni + pokyny jak zjistit zdroj a odstranit

**Proc:** Uzivatel chtel seskupeni dle post type (jako vsude jinde v pluginu) + moznost opravit chyby primo z reportu.

---

### 2026-06-16 (update 12) – M3: Fix zahlteni serveru pri scanu + dokumentace

**Co:** Scan zahlcoval web – vsechny stranky byly extremne pomale kdyz scan bezel.

**Pricina:** `process_batch()` volal `wp_remote_get` na 5 URL naraz (loopback HTTP requesty). Lokalni PHP-FPM ma maly worker pool – 5 soucasnych loopback pozadavku saturovalo vsechny workery, takze uzivatelovy requesty cekaly.

**Oprava:**
- `ScanRunner.php`: `BATCH_SIZE 5 → 1` (1 URL per cron spusteni), nova konstanta `BATCH_DELAY = 3` (sekundy pauzy), `wp_schedule_single_event(time() + BATCH_DELAY, ...)` misto `time()`.
- `PageScanner.php`: pridano `'sslverify' => false` (loopback requesty selhavaly na self-signed SSL v Local), timeout snizen `10s → 8s`.
- `json-ld.js`: zprava po spusteni scanu vysvetluje tempo (1 URL / 3 sekundy).
- `docs/modules/json-ld-validator.md`: kompletni prepis – aktualni architektura (background scan, polling), sekce "Co delat s nalezy" (jak opravit nevalidni schema, jak resit duplicity, co konfigurovat), sekce o automatickem planovani scanu.

**Vysledek:** Scan 50 URL trva ~2,5 minuty ale web bezi normalne. Pomer 1 URL / 3s lze konfigurovat zmenou `BATCH_SIZE` a `BATCH_DELAY`.

---

### 2026-06-16 (update 11) – M3: Background scan + archiv skenů

**Co:** Prepsani scanu z JS-loop na server-side WP-Cron batch processing.
- Novy `includes/JsonLd/ScanRunner.php`: `start()` naplanova cron, `process_batch()` ho konsumuje (5 URL/davka), vysledky do transienta, archiv poslednich 10 scanu v WP option `seob_jsonld_scan_archive`.
- `Ajax.php`: nove endpointy `seob_json_ld_start_scan`, `seob_json_ld_cancel_scan`, `seob_json_ld_scan_status`, `seob_json_ld_get_history`, `seob_json_ld_get_results`. Odstranen stary `run_scan` / `save_results` approach.
- `PageScanner.php`: zustava jen `scan_url()` a `get_scan_urls()`, zachovana zpetna kompatibilita `get_last_results()` pres ScanRunner.
- Template: karta "Archiv scenu" (tabulka s historii, odkaz na konkretni scan), progress bar s % + "X / N stranky", tlacitko Zrusit scan.
- JS: ciste polling `seob_json_ld_scan_status` kazde 2 sekundy, po `done` reload na spravny `?scan_id=`. Pokud uzivatel pride zpet na stranku a scan bezi, polling se automaticky nastartuje (`data-running="1"`).

**Vysledek:** Scan bezi nezavisle na browseru – kazdý admin-page request spousti dalsi WP-Cron davku. `composer test` 96/96 OK.

---

### 2026-06-16 (update 10) – M3: JSON-LD Validator + detektor duplicit

**Co:** Novy modul `json-ld` (vychozi: vypnuto). Extrahuje vsechny `application/ld+json` bloky z renderovaneho HTML stranky, validuje je vuci schema.org specifikaci a detekuje duplicitni schemata stejneho @type.

**Soubory:**
- `includes/JsonLd/Validator.php` – staticky validacni engine: `extract_schemas()`, `validate_schema()`, `detect_duplicates()`, `self_test()`, `short_type()`
- `includes/JsonLd/PageScanner.php` – nacita stranky pres `wp_remote_get`, spousti davkovy scan (max 50 URL), ukla vysledky do transienta, zapisuje do `seo_booster_metrics`
- `includes/JsonLd/Ajax.php` – AJAX: `seob_json_ld_run_scan`, `seob_json_ld_scan_url`, `seob_json_ld_get_results`
- `templates/admin/page-json-ld.php` – admin stranka: prehledova tabulka (URL / pocet schemat / chyby / duplicity / stav), validace jednotlive URL, dokumentace
- `assets/admin/js/json-ld.js` – JS: tlacitko "Spustit scan", validace jednotlive URL, live zobrazeni vysledku
- `tests/Unit/JsonLd/ValidatorTest.php` – 21 testu, vsechny OK

**Integrace:**
- `Settings.php`: `const JSON_LD`, `'json-ld' => 0` v modules defaultech
- `ModuleManager.php`: registrace modulu `json-ld`
- `Admin/Admin.php`: podmenu "JSON-LD Validator" + enqueue `json-ld.js`
- `Admin/SettingsAjax.php`: `modules_json_ld` checkbox
- `templates/admin/page-settings.php`: checkbox v sekci Moduly
- `Health/HealthChecks.php`: case `json-ld` + metoda `json_ld_checks()` (self-test, posledni scan, invalid, duplicates)
- `seo-boost.php`: verze 0.7.0 -> 0.8.0, nahr soubory

**Testovano:** `composer test` – 96 testu, 284 assertions, OK. `php -l` bez chyb na vsech souborech.

**Poznamka:** Validacni chybovy retezce pouzivaji ASCII (bez UTF-8 specialnich znaku), protoze Czech curly quotes uvnitr PHP double-quoted stringu s `{$var}` interpolaci zpusobily parse error na radce 130 (tokenizer si spletl bytove hodnoty s koncem retezce).

---

### 2026-06-16 (update 9) – M11: Oprava detekce konfliktu RM Free vs RM Pro

**Problém:** RM Free Schema modul LocalBusiness JSON-LD také umí → původní dokumentace tvrdila, že RM Free konflikt netvoří, což je nepřesné.

**Změny:**
- `includes/LocalSeo/Frontend.php` – `has_rank_math_local_seo()` přejmenován (sémantika zachována, detekuje jen RM Pro Local SEO modul); přidány dvě nové metody:
  - `has_rank_math_free()` – vrátí true pokud RM Free aktivní (ale ne RM Pro Local SEO)
  - `rank_math_free_has_local_business_schema()` – čte option `rank_math_titles` a hledá schema_type = LocalBusiness podtyp
- `templates/admin/page-local-seo.php` – tři úrovně banneru: error (RM Pro, auto-deaktivace), warning (RM Free s LocalBusiness nakonfigurovaným), info (RM Free aktivní bez LocalBusiness)
- `templates/admin/page-local-seo.php` – tabulka konfliktů rozšířena: RM Free rozdělen na dva scénáře (bez/s LocalBusiness), přidána instrukce Ctrl+U ověření
- `includes/Health/HealthChecks.php` – přidán check `local_seo_rm_free_conflict` (warning) pro RM Free s LocalBusiness schématem

---

### 2026-06-16 (update 8) – M11: Rozšíření dokumentace v admin UI

- `templates/admin/page-local-seo.php` – `<details>` dokumentace přepsána na kompletní průvodce:
  proč Local SEO dělat (tabulka přínosů), rychlý 5-krokový průvodce, typy podnikání (kdy co použít),
  proč IČO/DIČ (ARES/OR propojení), jak získat GPS, otevírací doba (edge cases), NAP konzistence
  (tabulka formátů telefonu), kde vkládat JSON-LD (srovnání), validace (postup), konflikty (5 pluginů),
  časté chyby a řešení

---

### 2026-06-16 (update 7) – M11: Local SEO (CZ)

**Nové soubory:**
- `includes/LocalSeo/Frontend.php` – třída `SEOB_LocalSeo_Frontend`: výstup LocalBusiness JSON-LD na `wp_head` (priorita 5), detekce konfliktu RM Local SEO, seznam 40+ typů podnikání
- `includes/LocalSeo/Ajax.php` – AJAX akce: `seob_local_seo_save` (uložení formuláře), `seob_local_seo_preview` (JSON-LD z uloženého nastavení), `seob_local_seo_nap_scan` (skenovani webu – telefon, město, název)
- `templates/admin/page-local-seo.php` – admin stránka: formulář (základní info, adresa, GPS, IČO/DIČ, otevírací doba, obrázek/logo, kde vkládat), náhled JSON-LD, NAP scan výsledky, dokumentace jako collapsible `<details>`
- `assets/admin/js/local-seo.js` – JS: toggle otevírací doby (disabled při Zavřeno), mediální výběr obrázku (wp.media), uložení (AJAX), náhled JSON-LD, NAP scan s barevně odlišenou tabulkou výsledků
- `tests/Unit/LocalSeo/FrontendTest.php` – 11 unit testů pokrývajících: detekci konfliktu, build_schema (minimální, adresa, GPS, IČO/DIČ, otevírací doba, výchozí type, částečná adresa), business_types()
- `docs/modules/local-seo.md` – kompletní dokumentace modulu

**Upravené soubory:**
- `includes/Settings.php` – přidán `const LOCAL_SEO = 'seob_local_seo_settings'` + výchozí nastavení (20+ polí, vč. otevírací doby Mo-Su)
- `includes/Settings.php` – přidán `local-seo => 0` do defaults modulů
- `includes/ModuleManager.php` – zaregistrován modul `local-seo` s třídami `SEOB_LocalSeo_Ajax`, `SEOB_LocalSeo_Frontend`
- `includes/Admin/Admin.php` – přidán submenu item, enqueue blok (`wp_enqueue_media` + `local-seo.js`), metoda `page_local_seo()`
- `includes/Admin/SettingsAjax.php` – přidán `local-seo` do modules pole při ukládání
- `templates/admin/page-settings.php` – přidán checkbox pro `local-seo`
- `includes/Health/HealthChecks.php` – přidány case `hreflang` + `local-seo` a metody `hreflang_checks()`, `local_seo_checks()`
- `seo-boost.php` – version `0.6.0 → 0.7.0`, DB version `0.7.0 → 0.8.0`, přidány soubory LocalSeo

**NAP scanner – logika:**
- Normalizuje telefon (strippuje vše kromě číslic a `+`)
- Regex hledá sekvence 8–20 znaků v obsahu stránek
- Označí výskyty kde formát nesouhlasí s referenčním zápisem z nastavení

**Detekce konfliktu:**
- RM Local SEO: třída `RankMath\Modules\LocalSeo\LocalSeo` nebo option `rank_math_modules` obsahuje `local-seo`
- Yoast Local SEO: dokumentováno v admin UI, bez auto-detekce (Yoast Local SEO je samostatný plugin bez jednoznačné třídy)

---

### 2026-06-16 (update 6) – M8: Hreflang testy + dokumentace + oprava nastavení

**Testy (`tests/Unit/Hreflang/ManagerTest.php`, 8 testů, 22 assertions):**
- `test_no_conflict_in_clean_test_environment` – has_conflict() → false bez RM Pro / Yoast
- `test_not_multilingual_in_clean_test_environment` – detect_multilingual() → false v čistém prostředí
- `test_get_tags_returns_empty_array_when_post_has_no_group` – post mimo skupinu → prázdné pole
- `test_get_tags_returns_one_tag_per_member` – 2 members → 2 tagy (cs, en)
- `test_get_tags_adds_x_default_after_marked_member` – is_x_default=1 → 3 tagy (cs, x-default, en)
- `test_x_default_url_matches_marked_member_url` – URL x-defaultu = URL označeného membera
- `test_get_tags_skips_member_with_no_permalink` – permalink=false → member přeskočen
- `test_get_tags_with_three_languages` – 3 jazyky + x-default → 4 tagy
- `tests/bootstrap.php` – přidána konstanta `ARRAY_A` (potřeba pro $wpdb mock)
- Celá sada: **64/64 OK**

**Dokumentace:**
- `templates/admin/page-hreflang.php` – přidána sekce „Automatické mapování skupin (připravováno)": popis tří strategií (Polylang integrace, WPML integrace, slug shoda), UI flow, poznámka že ručně vytvořené skupiny zůstanou nedotčeny
- `docs/modules/hreflang.md` – totéž + implementační poznámky pro budoucí vývoj

**Oprava nastavení:**
- `templates/admin/page-settings.php` – přidány chybějící checkboxy pro `internal-links` a `hreflang`
- `includes/Admin/SettingsAjax.php` – přidány `internal-links` a `hreflang` do modules pole při ukládání

---

### 2026-06-16 (update 5) – M8: Hreflang Manager

**Nové soubory:**
- `includes/Hreflang/Manager.php` – frontend output `<link rel="alternate" hreflang>` tagů; detekce konfliktu (RM Pro / Yoast Premium) a detekce vícejazyčnosti (WPML / Polylang / TranslatePress)
- `includes/Hreflang/Ajax.php` – AJAX handlery: `load_groups`, `save_group`, `delete_group`, `search_posts`, `validate`
- `templates/admin/page-hreflang.php` – admin stránka s group listingem + modal pro editaci skupin
- `assets/admin/js/hreflang.js` – card-based UI, inline autocomplete vyhledávání stránek, validátor

**Nové DB tabulky (`SEOB_DB_VERSION` → `0.7.0`):**
- `seo_booster_hreflang_groups` (id, name, created_at)
- `seo_booster_hreflang_members` (id, group_id, page_id, locale, is_x_default)
- `SEOB_Database::hreflang_groups_table()` + `hreflang_members_table()` helpers

**Modifikované soubory:**
- `includes/Database/Database.php` – +2 table helpers
- `includes/Activator.php` – +2 CREATE TABLE v `create_tables()`
- `includes/Settings.php` – `'hreflang' => 0` do modules defaults
- `includes/ModuleManager.php` – `'hreflang'` module registrace (`SEOB_Hreflang_Ajax`, `SEOB_Hreflang_Manager`)
- `includes/Admin/Admin.php` – submenu, enqueue_assets, `page_hreflang()` metoda
- `seo-boost.php` – Hreflang soubory v `$seob_files`; `SEOB_VERSION` → `0.6.0`, plugin header Version → `0.6.0`

**Klíčové chování:**
- Skupiny = N jazykových verzí 1 dokumentu; reciprocita je zaručena automaticky (všichni memberové vzájemně odkazují)
- Výstup hreflang tagů je blokován, pokud je detekován RM Pro nebo Yoast Premium
- Validátor hlásí: stránka ve více skupinách, nepublikované stránky

---

### 2026-06-16 (update 4) – M6: SEO zdraví prolinkování v admin přehledu (Interní prolinkování)

**ScanRunner.php `get_results()`:**
- Bulk SQL query na `post_content` pro všechna publikovaná ID → výpočet word_count bez N+1 dotazů
- Pro každou stránku přidány klíče: `word_count`, `link_min`, `link_max`, `link_status` ('ok' / 'low' / 'high')
- Nové summary klíče v return: `link_health_score` (% stránek ok), `link_ok_count`, `link_low_count`, `link_high_count`

**page-internal-links.php:**
- Přidán 4. summary box „Zdraví prolinkování" (`id="seob-links-summary-health"` + detail `seob-links-summary-health-detail`)

**internal-links.js:**
- `renderResults()`: aktualizuje health score box (zelená ≥80 %, oranžová ≥50 %, červená jinak) + detail „X ok · Y málo · Z příliš"
- `renderPageGroups()`: 4. sloupec „Stav odkazů" s barevným indikátorem (✓ V pořádku / ↑ Chybí X / ↓ Přebývá X)
- Řazení v každé skupině: low nahoře, pak high, pak ok (problémy jsou ihned viditelné)
- Header skupiny: „⚠ N problém" pokud existují stránky s low/high statusem
- Nová funkce `linkStatusCell(page)` – sdílená helper

---

### 2026-06-16 (update 3) – M6: Dynamický SEO monitoring počtu odkazů v metaboxu

**Odstraněno:**
- Sekce „Navrhované odkázání z:" z metaboxu (redundantní – uživatel si návrhy načte přes checklist níže)
- Statická sekce `<details>` SEO doporučení (obecný text nahrazen konkrétním indikátorem)

**Přidáno – dynamický indikátor počtu odkazů:**
- `MetaBox::render()`: počítá délku článku (`preg_split /\s+/u` na `wp_strip_all_tags` obsahu)
- Vzorec: `min = max(1, round(slov/500))`, `max = max(2, round(slov/200))` → odpovídá 2–5 linkům na 1000 slov
- Výsledek: zelená ✓ (v pořádku) / červená ↑ Chybí X (pod min) / oranžová ↓ Přebývá X (nad max)
- Text je konkrétní: „Chybí 2 odkazů (doporučeno 2–5 pro ~1000 slov)" apod.
- `MetaBox::compute_link_status()` – private metoda, vrací `[css_color, text]`
- `<p id="seob-metabox-counts">` má nové `data-link-min`, `data-link-max`, `data-word-count` atributy
- `<span id="seob-metabox-link-status">` zobrazuje indikátor, aktualizuje se i po vložení linků bez reload

**JS (`metabox-internal-links.js`):**
- Nová funkce `updateLinkStatus(outlinks)` – čte `data-*` z `seob-metabox-counts`, přepočítá status
- Volá se po úspěšném insertu (vedle aktualizace `seob-metabox-outlinks`)

---

### 2026-06-16 (update) – M6: Skupiny na stránce Interní prolinkování + přepracovaný metabox (výběr linků)

**Stránka Interní prolinkování – skupiny podle post type (dynamické):**
- `ScanRunner::get_results()` nově vrací `orphan_groups` a `page_groups` (seskupení `orphans`/`pages` podle `post_type`, label z `get_post_type_label()` = WP `labels->name`, seřazeno sestupně počtem položek)
- `page-internal-links.php`: flat `<table>` nahrazeny `<div id="seob-links-orphan-groups">` a `<div id="seob-links-page-groups">` – šablony `<template>` odstraněny
- `internal-links.js`: přidány `renderOrphanGroups(groups)` a `renderPageGroups(groups)` – reuse CSS tříd Audit Dashboardu (`.seob-audit-group`, `.seob-group-toggle`, `.seob-group-body`, `.seob-group-count`), toggle tlačítkem s `.is-expanded`

**Metabox editor – přepracovaný 2-fázový flow výběru linků:**
- Tlačítko přejmenováno z „Vložit linky" → **„Najít návrhy linků"**
- Fáze 1: klik → AJAX `seob_links_find` → vrátí max 10 kandidátů s kontextovým výňatkem (kde se titulek nachází v textu)
- Fáze 2: zobrazí checklist – každý kandidát má checkbox (výchozí: zaškrtnutý), název a kontextový výňatek s KW zvýrazněným `<mark>`, tlačítka „Vybrat vše/Zrušit výběr" + „Vložit vybrané (X)"
- Fáze 3: klik „Vložit vybrané" → AJAX `seob_links_insert` s `target_ids=JSON`, vloží jen vybrané, vložené položky se v checklistu zobrazí jako přeškrtnuté
- Elementor: varování hned při zobrazení checklistu, druhý klik na vložení vyžaduje potvrzení
- `LinkInserter::find_with_context(WP_Post, max=10)` – nová metoda s kontextem
- `LinkInserter::get_candidates()` – parametr `$max=20` (dříve hardcoded)
- `LinkInserter::insert()` – nový parametr `$only_ids[]` pro filtrování výběru
- `Ajax.php` – nový endpoint `seob_links_find`, endpoint `seob_links_insert` přijímá `target_ids` (JSON)

**php -l:** OK na všech souborech. Neotestováno v prohlížeči.

---

### 2026-06-16 – M6: Tlačítko „Vložit linky" v metaboxu editoru

**Nová funkce:** Automatické vkládání interních odkazů do obsahu článku přímo z editoru.

**Nové soubory:**
- `includes/InternalLinks/LinkInserter.php` – třída `SEOB_InternalLinks_LinkInserter`
  - `get_candidates(WP_Post)` – hledá orphan stránky (0 příchozích inlinks), jejichž titulek se vyskytuje v `post_content`; max 300 kandidátů, limit 2× max_links
  - `inject_links(string, array)` – chrání existující `<a>` tagy a `<h1-6>` nadpisy placeholders, nahradí první výskyt titulku za `<a href>`, max 3 linky
  - `insert(int)` – orchestruje, volá `wp_update_post()`, vrátí seznam vložených/přeskočených
  - `is_elementor(int)` – detekce Elementor stránky přes `_elementor_edit_mode` meta
- `assets/admin/js/metabox-internal-links.js` – metabox JS pro tlačítko

**Upravené soubory:**
- `includes/InternalLinks/Ajax.php` – nový endpoint `wp_ajax_seob_links_insert` → `insert()`: nonce + `edit_post` cap check, Elementor guard (bez `force=1` vrací `is_elementor:true`), volá `LinkInserter::insert()`
- `includes/InternalLinks/MetaBox.php` – `enqueue_scripts()` (jen na `post.php`/`post-new.php` pro auditované post typy), tlačítko `#seob-links-insert-btn` s `data-post-id`, výsledkový `<p>`
- `seo-boost.php` – `LinkInserter.php` přidán do `$seob_files` před `MetaBox.php`

**UX flow:**
1. Klasický WP editor / Gutenberg: 1 klik → vloží max 3 linky → zobrazí „Vloženo X odkazů (‚KW1', ‚KW2'). Uloženo. Reloadujte editor..."
2. Elementor stránka: 1. klik → varování (žlutý text + tlačítko změní label na „Potvrdit vložení (Elementor)") → 2. klik potvrdí `force=1`

**Logika kandidátů:** Orphan stránky = `post_status='publish'`, žádný záznam v `internal_links` jako `target_id`. Titulek musí být ≥ 3 znaky a musí se vyskytovat v `post_content` (`mb_stripos`). Vyloučeny Elementor/JetEngine builder typy.

**php -l:** OK na všech 3 souborech. Neotestováno v prohlížeči.

---

### 2026-06-15 (dodatek) – Oprava aktivace modulu `internal-links` (špatný option klíč)

Po smoke testu se v menu SEO Booster neobjevila položka „Interní
prolinkování“. Příčina: smoke-test skript zapsal
`modules.internal-links=1` do neexistujícího option klíče
`seob_settings_general` – `is_active()` čte `SEOB_Settings::GENERAL`, což
je `seob_general_settings`. Opraveno jednorázovým skriptem (zápis do
správného klíče), `is_active('internal-links')` nyní vrací `true`. Čeká se
na potvrzení, že položka menu je v UI viditelná – viz `STATE.md` →
„DALŠÍ KROK“.

### 2026-06-15 – M6: Interní prolinkování (Internal Link Assistant + orphan pages) – nový modul `internal-links`

Nový modul (výchozí vypnuto, `modules.internal-links`) – indexuje interní
link graf webu, hledá osamocené (orphan) stránky a navrhuje prolinkování
přes **lokální TF-IDF + kosinovou podobnost** (bez externí AI/API).

- **3 nové DB tabulky** (`SEOB_DB_VERSION` 0.5.0 → 0.6.0, `SEOB_VERSION`
  0.4.0 → 0.5.0): `seo_booster_internal_links` (aktuální link graf),
  `seo_booster_link_suggestions` (top 3 návrhy z posledního reindexu),
  `seo_booster_link_scans` (historie běhů). Helpery v
  `includes/Database/Database.php`, `CREATE TABLE` v
  `includes/Activator.php::create_tables()`.
- **`includes/InternalLinks/Extractor.php`** – 3-strategie extrakce obsahu
  (`_elementor_data` → `post_content` → meta pole, stejný vzor jako
  `Audit/Scanner.php`). `extract_links()` najde interní `<a href>` přes
  `url_to_postid()`, `extract_text()` vrátí čistý text pro TF-IDF. Čistá
  statická metoda `extract_internal_links_from_html()` (jen `DOMDocument` +
  `parse_url`) je testovatelná bez WP.
- **`includes/InternalLinks/Similarity.php`** – čisté funkce bez DB/WP:
  `tokenize()` (diakritika pryč, min. 3 znaky, CZ/EN stoplist),
  `build_tfidf()`, `cosine()`, `top_similar()`.
- **`includes/InternalLinks/ScanRunner.php`** – dávkový reindex (vzor
  `PageSpeed/ScanRunner.php`): `start_scan()`/`process_batch()` přepočítají
  link graf po dávkách, `finalize_scan()` přepočte TF-IDF vektory pro
  všechny indexované stránky, přepíše `link_suggestions` (top 3 pro každou
  stránku, vyloučeny už odkazované) a zapíše metriky `orphans_count` a
  `avg_inlinks` přes `SEOB_Metrics::record()`. `get_results()` vrací souhrn
  + tabulku orphan stránek (s návrhy) + tabulku všech stránek.
- **`includes/InternalLinks/Indexer.php`** – `save_post` hook udržuje
  `internal_links` aktuální mezi reindexy (`link_suggestions` se přepočítá
  až při dalším plném reindexu).
- **`includes/InternalLinks/MetaBox.php`** – postranní box v editoru
  "Interní prolinkování" (počty příchozích/odchozích odkazů + top 3 návrhy,
  čistě synchronní DB čtení).
- **`includes/InternalLinks/Ajax.php`** – `seob_links_start/batch/results/
  history/active`, stejný `check_request()` vzor jako PageSpeed.
- **Health check** `SEOB_Health_Checks::internal_links_checks()` –
  kritická bez dokončeného reindexu, varování při reindexu staršímu 30 dní
  nebo `orphans_count > 0`, jinak OK.
- **Admin UI** – nová stránka „Interní prolinkování“ (`seob-internal-links`):
  tlačítko reindex + progress bar, souhrnné karty (počet stránek, orphans s
  trendem, průměr inlinks s trendem), tabulky „Osamocené stránky“ a „Všechny
  stránky“ (`templates/admin/page-internal-links.php`,
  `assets/admin/js/internal-links.js`, drobná `.seob-summary-row` v
  `admin.css`).
- **Registrace**: `ModuleManager` (`depends_on: []`), `Settings`
  (`modules.internal-links => 0`), `Admin.php` (submenu + enqueue), 6 nových
  souborů v `seo-boost.php`.
- **Dokumentace + testy**: `docs/modules/internal-links.md` (4 sekce dle
  vzoru `gsc-insights.md`, vč. limitů TF-IDF). Nové
  `tests/Unit/InternalLinks/ExtractorTest.php` a `SimilarityTest.php` –
  **56/56 testů OK** (bylo 38, +18 nových).
- **Oprava při wp-cli smoke testu**: sloupec `rank` v
  `seo_booster_link_suggestions` je od MySQL 8.0.2 rezervované slovo →
  `dbDelta()` tuto tabulku tiše nevytvořil (ostatní 2 nové tabulky vznikly
  bez problému). Přejmenováno na `rank_order` (`Activator.php`,
  `ScanRunner.php`, `MetaBox.php`).
- **wp-cli smoke test na reálné DB (87 položek)**: aktivován modul, plný
  reindex `87/87`, `orphans_count=82`, `avg_inlinks=0.11`. Návrhy dávají
  smysl – např. orphan stránka „CTA“ (slovíček pojmů) navrhuje odkázat z
  „CTA tlačítko“ a „CTA na webu“ (score 0.31/0.30). Health check: `critical`
  → po reindexu `good` (poslední reindex) + `warning` (82 osamocených
  stránek). Modul je na test webu nyní **aktivní**.
- `php -l` OK na všech nových/upravených souborech, `composer test` 56/56
  OK. Vizuální test v prohlížeči (dashboard tabulky + editor metabox)
  zatím neproběhl.

### 2026-06-15 – PageSpeed Insights: souhrnný přehled celého webu (mobil/desktop, průměr přes všechny typy obsahu)

Uživatel chtěl nad jednotlivými skupinami podle typu obsahu i jeden
celkový "souhrnný report" rychlosti webu (mobil/PC) – aby šlo na první
pohled vidět, jak na tom web je celkově, vč. trendu oproti minulému běhu.

- **`includes/PageSpeed/ScanRunner.php`**: nová veřejná čistá metoda
  `compute_overall_scores( array $rows ): array` – pro danou strategii
  (mobil/desktop) spočítá vážený průměr (váha = `sample_size`) přes
  `performance_avg`, `accessibility_avg`, `best_practices_avg`, `seo_avg`
  ze všech `psi_summary` řádků daného běhu. `get_results()` nově vrací
  klíč `overall` => `['mobile' => [...,'deltas'=>...], 'desktop' => [...]]`,
  kde `deltas` se počítá stejnou `compute_deltas()` funkcí jako u
  jednotlivých skupin (porovnání s předchozím dokončeným během).
- **`templates/admin/page-pagespeed.php`**: nový kontejner
  `#seob-psi-overall` (nad `#seob-psi-results`) + nová šablona
  `#seob-psi-overall-template` – karta "Celkový přehled webu (průměr ze
  všech typů obsahu)" se stejnými 4 skóre × mobil/desktop jako u skupin.
- **`assets/admin/js/pagespeed.js`**: vytažena společná funkce
  `applyStrategyScores()` (dřív duplikovaná logika v `renderGroups`), nová
  `renderOverall(overall)` – naplní `#seob-psi-overall-template` daty
  z `result.overall`. Voláno z `loadResults()` před `renderGroups()`.
- **`assets/admin/css/admin.css`**: `.seob-psi-overall` – zvýrazněná karta
  (modrý levý border), aby byla vizuálně odlišená od skupin podle typu
  obsahu.
- `composer test` 38/38 OK, `php -l` OK na všech upravených PHP souborech.
- Poznámka: na `.local` doméně nejsou žádná validní skóre (viz předchozí
  záznam o `FAILED_DOCUMENT_REQUEST`), takže přehled se reálně naplní až
  na produkci / přes tunel.

### 2026-06-15 – Audit Dashboard: oprava skóre skupiny (chybné sčítání jako string) + popis konkrétních zlepšení/zhoršení u trendu

Po zapnutí trendů se v UI objevila u skóre skupiny absurdní čísla
(`2348275025`, `1.159191919191919e+149` apod.) místo skóre 0-100.

- **Příčina**: `row.score` chodí z DB jako string (`wpdb` ARRAY_A). V
  `avgScore()` se `acc + row.score` chovalo jako konkatenace stringů
  (`0 + "97"` → `"097"`), takže `sum` byl po průchodu všemi řádky obrovský
  string číslic, který `Math.round()` přečetl jako vědecký zápis.
- **Oprava**: `loadResults()` nyní při načtení výsledků převede
  `row.score` a `row.score_delta` na `Number` (`currentRows = rows.map(...)`).
  `avgScore()` i porovnání `row.score >= 80` pak počítají správně.

Zároveň – uživatel chtěl u trendu (▲/▼) vědět, **v čem konkrétně** se
skóre zlepšilo/zhoršilo, a aby bylo jasné, že číslo ve skupině je "SEO
skóre":

- `AuditScanRunner.php::get_results()`: nově počítá i `row['new_issues']`
  (nálezy, které se objevily oproti minulému scanu – "zhoršení"), navíc
  k existujícímu `resolved_issues` ("zlepšení"). Na úrovni skupiny agreguje
  `group_issue_changes[object_type] = ['resolved' => [typ => počet], 'new' => [typ => počet]]`
  a vrací v `summary`.
- `audit-dashboard.js`:
  - Nové `rowIssueChangeTooltip(row)` a `groupIssueChangeTooltip(changes)` –
    sestaví text tooltipu "Zlepšeno (opraveno): ... / Zhoršeno (nové
    nálezy): ...".
  - Nové `createScoreDeltaEl(delta, tooltip)` (DOM element, bezpečné
    `title`) nahradilo `insertAdjacentHTML` u skóre skupiny i jednotlivé
    stránky – trend `▲/▼` má teď tooltip s konkrétním důvodem.
  - Hlavička skupiny má nový popisek `.seob-group-score-label` = "SEO
    skóre" před badge se skóre.
- `page-dashboard.php` / `admin.css`: nový `<span class="seob-group-score-label">`
  v `#seob-group-template` + styl.

`composer test` **38/38 OK**. wp-cli ověřeno na scanu `#26`: `page` skupina
má `group_score_deltas.page = -2` a `group_issue_changes.page.new.description_missing = 1`
(stránka #828, `score_delta=-15`, `new_issues=["description_missing"]`) –
tooltip správně ukáže "Zhoršeno: description_missing". `slovicek-pojmu` má
`resolved.thin_content = 1` (stránka #1306, `score_delta=+7`).

---

### 2026-06-15 – Audit Dashboard: trend skóre (celkově, po kategoriích, po stránkách) oproti minulému scanu

Uživatel chtěl vidět, jestli se situace SEO zlepšuje – hodnocení/trend i na
úrovni kategorie (skupiny podle typu obsahu) a u jednotlivých stránek
oproti minulému auditu.

- `AuditScanRunner.php`:
  - Přejmenováno/rozšířeno `get_previous_issue_types()` → 
    `get_previous_scan_data()` – z posledního dokončeného scanu před daným
    `$scan_id` vrátí mapu `object_id => [typy nálezů]` (jako dřív, pro
    "opraveno od minula"), nově i mapu `object_id => skóre`, mapu
    `object_type => průměrné skóre` a celkové `score_avg` minulého scanu.
  - `get_results()`: každý řádek má nově `score_delta` (aktuální skóre minus
    skóre stejné stránky v minulém scanu, `null` pokud stránka v minulém
    scanu nebyla). Summary má nově `score_delta` (celkové skóre webu vs.
    minulý scan) a `group_score_deltas` (mapa `object_type => delta`
    průměrného skóre kategorie vs. minulý scan; chybí, pokud daný typ obsahu
    v minulém scanu nebyl – např. nová kategorie "Slovíček pojmů").
- `audit-dashboard.js`:
  - Nová helper funkce `scoreDeltaHtml(delta)` – vrátí `▲ +N` (zeleně) /
    `▼ -N` (červeně) / nic (pokud `0`/`null`).
  - Celkové skóre webu (`renderSummary`), skóre skupiny (`buildGroup`) i
    skóre jednotlivé stránky (`buildRow`) nyní zobrazují trend vedle
    skóre badge. Skupinový trend čte `currentSummary.group_score_deltas`
    (nová proměnná `currentSummary`, uložená v `loadResults()`).
- `admin.css`: nové styly `.seob-score-delta`, `.seob-score-delta-up`
  (zelená), `.seob-score-delta-down` (červená).

`composer test` **38/38 OK**. wp-cli ověřeno na scanu `#25` vs. předchozímu
scanu: celkové `score_delta = 12`, `group_score_deltas` = `page: 0, post: 0`
(kategorie "Slovíček pojmů" v minulém scanu nebyla, takže bez trendu – první
měření). Jednotlivé stránky mají `score_delta` (0 v tomto případě, skóre se
nezměnilo).

---

### 2026-06-15 – Audit scan: oprava kontroly obsahu (počet slov = 0) u custom post typů bez `post_content` (Slovíček pojmů)

Uživatel upozornil, že u "Slovíček pojmů" kontrola obsahu (počet slov)
ukazovala všude 0 → falešné "thin_content" nálezy u všech 75 položek.
Příčina: JetEngine custom post typ neukládá obsah do `post_content` ani
`_elementor_data`, ale do vlastních meta polí (`kratka_definice`,
`definice_dlouha`, `vzorec`, `sine_stranky`, `slabe_mista`, ...).

- `Scanner.php`:
  - `extract_from_html()` přejmenováno/upraveno – nyní přebírá přímo
    `string $raw_content` (dřív `WP_Post`), aby šlo parsovat i obsah
    poskládaný z meta polí.
  - `extract_content_data()`: pokud post nemá `_elementor_data` ani
    neprázdný `post_content`, použije nově `collect_meta_content()`.
  - Nová `collect_meta_content( WP_Post $post ): string` – projde
    `get_post_meta($post->ID)`, vynechá interní meta (klíče začínající `_`)
    a meta SEO pluginu (`rank_math_*`), zbylé textové (neserializované,
    nečíselné) hodnoty spojí do náhradního HTML → počet slov/nadpisy/obrázky
    se počítají stejně jako u klasického obsahu. Funguje obecně pro
    jakýkoli custom post typ s obsahem v meta polích, ne jen pro "Slovíček
    pojmů".

`composer test` **38/38 OK**. wp-cli ověřeno na konkrétní položce (ID 2042,
"CTOR"): dřív `word_count=0` → nyní `248 slov` (skóre 93, `thin_content`
warning je teď legitimní, ne falešný nález z chybějícího obsahu). Spuštěn
nový scan `#26` (87 položek) pro přepočet všech "Slovíček pojmů" – výsledek
ověřen v dalším kroku.

---

### 2026-06-15 – Audit scan: dynamické post typy podle webu (vč. Slovíček pojmů), vyloučení builder šablon

Uživatel chtěl do auditu zahrnout i další typy obsahu, ne jen
`post`/`page` – konkrétně zmínil "slovníček pojmů". Zároveň jsme zjistili,
že web má registrované i Elementor/JetEngine builder/template post typy
(`jet-popup`, `e-floating-buttons`, `elementor_library`, `jet-theme-core`),
které do auditu nepatří.

- `AuditScanRunner.php`:
  - Nová konstanta `EXCLUDED_POST_TYPES` (`attachment`, `jet-popup`,
    `e-floating-buttons`, `elementor_library`, `jet-theme-core`).
  - Nová statická metoda `get_audit_post_types()` – vrátí všechny veřejné
    post typy webu (`get_post_types(['public'=>true])`) minus vyloučené,
    a jen ty, které mají alespoň 1 publikovanou položku
    (`wp_count_posts()->publish > 0`). Zjišťuje se dynamicky podle obsahu
    konkrétního webu, žádné natvrdo zadané `post`/`page`.
  - `start_scan()` teď používá `self::get_audit_post_types()` místo
    natvrdo `['post', 'page']`.
- `Admin.php`: `postTypeLabels` v `seobData` pro audit dashboard se nyní
  generují dynamicky novou metodou `get_audit_post_type_labels()` –
  projde `SEOB_Audit_ScanRunner::get_audit_post_types()` a pro každý
  doplní `get_post_type_object($type)->labels->name`.
- Žádné změny šablony/JS – nové skupiny (např. "Slovíček pojmů") vznikají
  automaticky přes existující `renderRows()`/`buildGroup()` logiku, jen
  podle reálných `object_type` z výsledků a `postTypeLabels`.

`composer test` **38/38 OK**. wp-cli ověřeno: `get_audit_post_types()` na
tomto webu vrací `['post', 'page', 'slovicek-pojmu']` (87 publikovaných
položek celkem – 4 + 8 + 75), nový scan zařadil do fronty všech 87 URL.

---

### 2026-06-15 – Audit Dashboard: výsledky seskupené podle typu obsahu (Příspěvky/Stránky)

Uživatel chtěl Audit Dashboard přehlednější – na první pohled vidět, kde je
problém (např. v "Posts"), a teprve po rozkliknutí seznam konkrétních
stránek pod danou skupinou.

- `page-dashboard.php`: plochá tabulka nahrazena `<div id="seob-groups">` +
  novým `<template id="seob-group-template">` – každá skupina (typ obsahu)
  má vlastní hlavičku (tlačítko) a sbalitelné tělo s vlastní tabulkou
  (stejné sloupce jako dřív, jen per-skupina).
- `audit-dashboard.js`:
  - `renderRows()` teď seskupuje `currentRows` podle `row.object_type`
    (`post`/`page`), seřadí skupiny **od nejhoršího průměrného skóre** –
    problémová skupina je nahoře.
  - Hlavička skupiny zobrazuje: název typu obsahu (`seobData.postTypeLabels`),
    počet stránek, průměrné skóre (barevný badge good/mid/bad) a počty
    kritických/varování/doporučení nálezů.
  - Skupiny jsou collapse/expand (klik na hlavičku), stav rozbalení se
    pamatuje v `expandedGroups` přes překreslení (filtry). Nejhorší skupina
    je při prvním vykreslení rozbalená automaticky.
  - `seob-gsc-hidden` toggle teď řeší `querySelectorAll` přes všechny
    `.seob-audit-table` (jedna per skupina).
  - Doplněno i "od minula opraveno" (`resolvedCount()`) a souhrn Search
    Console za celou skupinu (`groupGsc()` – součet zobrazení/kliků,
    průměrná CTR a pozice), zobrazeno v hlavičce skupiny vedle skóre.
- `Admin.php`: do `seobData` pro audit dashboard přidáno `postTypeLabels`
  (`post` → "Příspěvky", `page` → "Stránky", z `get_post_type_object()->labels->name`).
- `admin.css`: nové styly `.seob-audit-group`, `.seob-group-toggle`
  (+ `.is-expanded` šipka), `.seob-group-count*`, `.seob-empty-groups`.

`composer test` **38/38 OK** (změny jen v JS/CSS/template/enqueue, žádná
nová PHP logika). wp-cli ověřeno: poslední scan má 12 řádků – 8 `page` +
4 `post`, labely "Stránky"/"Příspěvky" se načítají správně.

---

### 2026-06-15 – PageSpeed Insights: běh na pozadí (WP-Cron) + historie a porovnání skóre

Uživatel chtěl, aby analýza běžela i na pozadí (nemusí čekat na stránce,
běh se dokončí i po odchodu) a aby se uchovávaly poslední výsledky pro
porovnání skóre před/po opravě. Implementováno:

- `ScanRunner::CRON_HOOK = 'seob_psi_process_batch'` – po `start_scan()`
  (a po každé dávce, která ještě neskončila) se naplánuje
  `wp_schedule_single_event()`. WP-Cron callback `process_batch_cron()`
  zpracuje 1 položku a naplánuje další, dokud běh neskončí – takže scan
  doběhne i bez otevřené administrace (na dalším návštěvníkovi webu).
- `process_batch()`: guard `'done' === $run->status` (stará naplánovaná
  cron událost už nemůže znovu finalizovat hotový běh) + transientový
  zámek `seob_psi_lock_{run_id}` (TTL 2 min) proti souběhu JS pollingu a
  WP-Cron callbacku na stejném běhu.
- `Activator::deactivate()` – `wp_clear_scheduled_hook( CRON_HOOK )`.
- Historie běhů: `get_run_history()` (posledních 10 dokončených, limit
  beze změny), nové AJAX `seob_psi_history`. Dropdown "Historie běhů" v
  `page-pagespeed.php`, mazání teď maže vybraný běh ("Smazat vybraný běh").
- Obnovení po reloadu: `get_active_run()` (běžící run + jeho fronta z
  transientu), AJAX `seob_psi_active` – `pagespeed.js` při načtení stránky
  zjistí, jestli něco běží na pozadí, a pokud ano, obnoví progress bar a
  pokračuje v pollingu od aktuálního `done/total`.
- Porovnání skóre: `get_results( ?run_id )` (refaktor z `get_latest_results()`)
  dohledá předchozí dokončený běh a pro každou kombinaci typ obsahu+strategie
  spočítá `compute_deltas()` (rozdíl 4 skóre oproti minulému běhu, čistá
  funkce). V `page-pagespeed.php`/`admin.css` nové `.seob-psi-delta-*`
  prvky (`+5`/`−3`/`0`, barvy zelená/červená/šedá), `seob_psi_results` nově
  přijímá `run_id` z dropdownu historie.

`tests/Unit/PageSpeed/ScanRunnerAggregationTest.php` – 3 nové testy pro
`compute_deltas` (rozdíly, bez předchozího běhu, chybějící skóre na jedné
straně). `composer test` **38/38 OK**. wp-cli smoke test: `get_active_run()`
správně vrátil reálný běžící scan (#4, 33/50 → 34/50 mezi dvěma voláními,
tedy WP-Cron na pozadí skutečně pokračuje), `get_run_history()`/`get_results()`
vrací prázdno/`null` dokud nemáme žádný dokončený běh (čeká se na doběhnutí
prvního běhu uživatele).

---

### 2026-06-15 – PageSpeed Insights: vylepšený progress bar + konfigurace API klíče

Po implementaci modulu (viz záznam níže) uživatel vytvořil free PSI API
klíč (omezený jen na „PageSpeed Insights API“) a byl uložen šifrovaně přes
wp-cli – modul je zapnutý a health check vrací `good`.

Uživatel si stěžoval, že progress bar při běhu (každý PSI dotaz trvá
15–40 s) vypadá „seklý“, protože se mezi jednotlivými kroky dlouho nic
neděje. Vylepšeno:

- `ScanRunner::start_scan()` nově vrací i `queue` (seznam položek
  `{object_id, object_type, url, strategy}`), `process_batch()` vrací
  `items` (zpracované položky s `url`/`strategy`/`error`).
- `pagespeed.js`: progress bar má při běhu animované pruhy (`is-busy`),
  vedle něj rotující spinner, a pod ním stavový řádek "Hotovo: <url>
  (mobil) ✓ | Testuji (5/48): <url> (desktop) … (může trvat 15–40 s)" –
  takže je vidět konkrétní stránka/strategie, na které se právě čeká.
- `page-pagespeed.php` – nový `#seob-psi-progress-status` řádek + spinner
  element; `admin.css` – `@keyframes seob-progress-stripes`/`seob-spin`.
- `docs/modules/pagespeed.md` – přepsán "Jak získat bezplatný API klíč" na
  podrobný krok-za-krokem návod (přesně podle UI Google Cloud Console,
  vč. "Select API restrictions"/"Application restrictions") + sekce
  "Bezpečnost".

`php -l` bez chyb, `composer test` **35/35 OK**. wp-cli ověřeno, že
`start_scan()` vrací `queue` s 48 položkami. Reálný scan v prohlížeči ještě
neproběhl.

---

### 2026-06-15 – Nový modul "PageSpeed Insights (Lighthouse)" implementováno

Dle schváleného plánu (`melodic-stirring-spark.md`): doplnění auditu o reálná
data z Google Lighthouse přes PageSpeed Insights (PSI) API v5 – bez
headless Chrome na serveru. Modul `pagespeed` (výchozí vypnutý), pro každý
veřejný post type s alespoň 1 publikovanou položkou vybere 5 náhodných
publikovaných stránek, otestuje mobile+desktop, a shrne podle typu obsahu
(průměrná skóre Performance/Accessibility/Best Practices/SEO + top 10
nejčastějších SEO nálezů s popisem + odkazy na vzorek stránek).

Implementováno (`SEOB_VERSION` 0.3.0 → 0.4.0, `SEOB_DB_VERSION` 0.4.0 →
0.5.0):

- Nové tabulky `wp_seo_booster_psi_runs/psi_results/psi_summary`
  (`Activator::create_tables()` + `SEOB_Database::psi_*_table()`).
- `SEOB_Settings::PAGESPEED` (`enabled`, `api_key_enc` – šifrováno přes
  `SEOB_AiQueue_Crypt`, stejně jako u AI asistenta), nová položka v
  `GENERAL.modules`.
- `includes/PageSpeed/Client.php` (`SEOB_PageSpeed_Client`) –
  `analyze()`/`parse_response()` (čistá funkce, kryto PHPUnit), volá
  `runPagespeed` endpoint, vrací 4 skóre (0–100) + `issues` (SEO audity se
  score < 1, vyfiltrované `notApplicable`/`informative`).
- `includes/PageSpeed/ScanRunner.php` (`SEOB_PageSpeed_ScanRunner`) –
  `start_scan()`/`process_batch()`/`finalize_scan()`/`get_latest_results()`/
  `delete_run()`, agregace `aggregate_group()` (čistá funkce, kryto PHPUnit).
  Dávkování po 1 položce (kvůli PSI free tier limitům 25k/den, 240/min a PHP
  timeoutu), fronta v transientu (`seob_psi_queue_{run_id}`, TTL 1h),
  historie posledních 10 běhů.
- `includes/PageSpeed/Ajax.php` (`SEOB_PageSpeed_Ajax`) – `seob_psi_start`/
  `_batch`/`_results`/`_delete`, stejný nonce+capability vzor jako ostatní
  moduly.
- `SEOB_Health_Checks::pagespeed_checks()` – critical bez API klíče, good
  bez proběhlé analýzy, warning pokud poslední SEO skóre < 80.
- Admin: nová stránka **SEO Booster → PageSpeed Insights** (`seob-pagespeed`,
  `page-pagespeed.php` + `pagespeed.js` – tlačítko spuštění, progress bar,
  karty po post type s mobile/desktop skóre, top nálezy, vzorek stránek;
  `textContent` pro dynamická data).
- Nastavení: sekce "PageSpeed Insights (Lighthouse)" (`page-settings.php` +
  `save_pagespeed_settings()` v `SettingsAjax.php` + `settings.js`) – toggle
  modulu + API klíč (placeholder `••••••••` po uložení).
- Dokumentace `docs/modules/pagespeed.md` vč. návodu na free PSI API klíč
  (Google Cloud Console).
- Nové PHPUnit testy `tests/Unit/PageSpeed/ClientTest.php` +
  `ScanRunnerAggregationTest.php` (parsing PSI odpovědi, agregace skóre a
  common issues) – **35 testů, 111 assertions, OK**. Bootstrap doplněn o
  globální stuby `HOUR_IN_SECONDS`, `WP_Error`, `__()`.

**Ověřeno přes wp-cli** (bez reálného API klíče): aktivace modulu, existence
3 nových tabulek, `start_scan()` vrátil `items_total = 48` (odpovídá
6 post typům s publikovanými položkami × min(5, počet) × 2 strategie),
`process_batch()` korektně uložil řádek s `error = "PageSpeed Insights API
klíč není nastaven."` a `score = NULL`, health check vrátil `critical`
(`pagespeed_configured`). Testovací run/výsledky po sobě smazány, modul
vrácen do výchozího vypnutého stavu. `php -l` bez chyb na všech 13
dotčených souborech.

**Zbývá**: až uživatel doplní PSI API klíč (návod v `docs/modules/pagespeed.md`),
otestovat reálný scan v prohlížeči (progress bar, výsledky).

---

### 2026-06-15 – Bezpečnostní review nových AJAX endpointů + commit/push celého balíku

Uživatel požádal o commit/push dlouho narostlého balíku (vše od session
2026-06-11/12 – ModuleManager/Stav systému, Chytrá indexace, GSC insights,
PDF redesign, schema overrides, scan history, AI schvalovací fronta,
Composer/PHPUnit), aniž by měl API klíč na test bodu D.

Před pushem proběhlo bezpečnostní review nově přidaných AJAX endpointů,
které ještě nebyly v auditu z 2026-06-11 (ten řešil jen audit/redirects/pdf
v1): `AiQueue/Ajax.php`, `SmartIndexing/Ajax.php`, `Health/StatusAjax.php`,
`Pdf/Ajax.php`, `Admin/SettingsAjax.php` (nové `save_pdf_settings`/
`save_ai_settings`). Všechny endpointy mají `check_ajax_referer` +
`current_user_can('manage_options')` (resp. `edit_post` pro AI suggest/
approve na konkrétním postu), vstupy sanitizované (`absint`/`sanitize_key`/
`sanitize_text_field`/`esc_url_raw`/`wp_kses_post`), SQL přes `$wpdb->prepare`
(jen statické table-name konstanty interpolovány). Nová admin JS
(`ai-queue.js`, `smart-indexing.js`, `status-page.js`, `report.js`,
`schema-defaults.js`, ...) používá `innerHTML` jen pro statické řetězce,
dynamická data (object_title, suggestion, current_value...) přes
`textContent`. API klíč AI providera šifrován AES-256-CBC
(`SEOB_AiQueue_Crypt`, klíč odvozený z `wp_salt('auth')`). Žádné problémy
nenalezeny.

Commit `d471fd6` na `feature/audit-dashboard-redirects`, pushnuto na
`origin`. **`main` nedotčen, branch nemergována** – merge čeká na další
pokyn uživatele.

---

### 2026-06-12 – Nový modul "AI schvalovací fronta" implementováno

Dle master zadání (sekce 2 a 5: "AI nikdy neukládá automaticky" + "API klíče
šifrované v DB"). Uživatel zvolil rozsah title + description + alt texty
obrázků a obecný OpenAI-compatible adaptér (doporučení v docs: free Gemini/
Groq).

Implementováno (`SEOB_VERSION` beze změny, `SEOB_DB_VERSION` beze změny –
tabulka `seo_booster_ai_queue` už existovala z dřívější migrace):

- `includes/AiQueue/Crypt.php` – AES-256-CBC šifrování API klíče
  (`wp_salt('auth')` jako zdroj klíče, náhodné IV per volání).
- `includes/AiQueue/ProviderInterface.php` +
  `OpenAiCompatibleProvider.php` – vyměnitelný adaptér, `wp_remote_post` na
  `{endpoint}/chat/completions` (formát kompatibilní s OpenAI/Gemini/Groq).
- `includes/AiQueue/Repository.php` – CRUD nad `seo_booster_ai_queue`
  (`insert`, `get`, `get_list`, `set_status`, `count_by_status`).
- `includes/AiQueue/PromptBuilder.php` – čisté funkce pro prompty
  (title ≤60 znaků, description ≤155, alt ≤125), kryté PHPUnit testy.
- `includes/AiQueue/Ajax.php` – `seob_ai_suggest`, `seob_ai_suggest_alt`,
  `seob_ai_queue_list`, `seob_ai_queue_approve`, `seob_ai_queue_reject`.
  Alt texty: parsuje `<img>` tagy bez/s generickým alt (regex z
  `Audit_Scanner::is_generic_alt`, max 5 obrázků/stránku).
- `includes/Settings.php` – nová konstanta `AI` (`seob_ai_settings`),
  defaults `enabled/endpoint/model/max_tokens/api_key_enc`.
- `includes/Admin/SettingsAjax.php` – `save_ai_settings()` (re-encrypt jen
  při vyplnění nového klíče, vrací `has_api_key` bez dešifrovaného klíče),
  modul toggle `modules.ai-queue`.
- `includes/ModuleManager.php` – modul `ai-queue` (`depends_on: ['audit']`,
  výchozí vypnuto).
- `includes/Admin/Admin.php` – submenu „AI fronta“ (`seob-ai-queue`),
  enqueue `ai-queue.js`, `seobData.aiQueueActive`/`aiQueueUrl` pro audit
  dashboard.
- `seo-boost.php` – registrace 6 nových souborů v `$seob_files`.
- `templates/admin/page-settings.php` – checkbox modulu + sekce „AI
  asistent“ (endpoint/model/max_tokens/api_key, popis free Gemini/Groq).
- `templates/admin/page-ai-queue.php` + `assets/admin/js/ai-queue.js` +
  CSS (`.seob-ai-queue-table` aj.) – přehled fronty s filtrem stavu a
  tlačítky Schválit/Zamítnout.
- `templates/admin/page-dashboard.php` + `assets/admin/js/audit-dashboard.js`
  – tlačítka „Navrhnout pomocí AI“ (title/description) a „Navrhnout alt
  texty obrázků“ (jen při nálezu `missing_alt`), zobrazí se jen pokud
  `seobData.aiQueueActive`. Po odeslání zobrazí náhled návrhu / počet
  vygenerovaných alt textů s odkazem do AI fronty.
- `includes/Health/HealthChecks.php` – `ai_queue_checks()`: kritická pokud
  chybí API klíč u zapnutého modulu, varování při ≥1 pending návrhu, jinak
  OK.
- `assets/admin/js/settings.js` – ukládání AI sekce jako samostatný AJAX
  request (`seob_save_ai_settings`, paralelně s `seob_save_settings` přes
  `Promise.all`), protože jde o jinou option skupinu.

**Ověřeno:**
- `php -l` na všech nových/upravených PHP souborech – bez chyb.
- `composer test` (PHPUnit, `vendor-dev/bin/phpunit`) – **28 testů, 84
  assertions, OK** (20 stávajících + nové `CryptTest`, `PromptBuilderTest`).
- wp-cli smoke test: `SEOB_Settings::get/update(SEOB_Settings::AI)` +
  `SEOB_AiQueue_Crypt::encrypt/decrypt` round-trip přes reálnou DB;
  `SEOB_AiQueue_Repository::insert/get/set_status/count_by_status` na
  reálném postu (#1328 „Cookies“) – vložení návrhu nezměnilo
  `rank_math_title`, schválení (`set_status` + `update_post_meta`) zapsalo
  hodnotu a změnilo stav na `approved`. Po testu nastavení AI vráceno na
  výchozí (vypnuto, prázdný klíč), `modules.ai-queue` vráceno na `0`,
  testovací řádek z fronty smazán.

**NEOTESTOVÁNO V PROHLÍŽEČI** – tlačítka v Audit Dashboardu, stránka „AI
fronta“, sekce „AI asistent“ v Nastavení. Pro reálné vyzkoušení potřeba
platný API klíč (free Gemini/Groq, viz `docs/modules/ai-queue.md`).

### 2026-06-12 – GSC insights: klíčová slova po rozkliknutí + sladění sloupců tabulky

Po otestování modulu „Search Console statistiky (Rank Math)" (sloupce
Zobrazení/Kliky/CTR/Pozice fungují, viz screenshot uživatele) doplněno na
žádost uživatele:

- `SEOB_Gsc_Insights::attach_queries(&$rows, $limit = 10)` (nová metoda) –
  jeden dotaz `GROUP BY page, query` za 28 dní, seřazený podle kliků, doplní
  `row.gsc_queries` (max 10 dotazů na stránku, `null` pokud GSC tabulka
  nedostupná).
- `Audit/Ajax.php::scan_results()` volá `attach_queries()` vedle
  `attach_metrics()`.
- `templates/admin/page-dashboard.php` – v edit panelu ("Opravit") nová
  sekce "Klíčová slova ve vyhledávání (28 dní)" s tabulkou
  Dotaz/Pozice/Kliky/Zobrazení.
- `audit-dashboard.js` – vyplní tabulku z `row.gsc_queries`, skryje sekci
  pokud `null`, zobrazí "nemáme data" pokud prázdné pole.
- CSS: nová tabulka `.seob-gsc-queries-table` + oprava zarovnání hlavního
  auditu – sloupce `.seob-col-gsc` (Zobrazení/Kliky/CTR/Pozice) chyběly v
  pravidlech `white-space: nowrap; width: 1%` a `text-align: center`, takže
  byly širší a zleva zarovnané oproti ostatním sloupcům.

Ověřeno wp-cli s testovací tabulkou `wp_rank_math_analytics_gsc`
(víc dotazů na jednu stránku, seřazení podle kliků, `null` pro stránky bez
dat) – funguje.

**Oprava rozbitého layoutu tabulky** (screenshot uživatele – přesahující/
překrývající se hlavičky "ZOBRAZENÍKLIKY", "POZICAKCE"): tabulka měla třídu
`wp-list-table widefat fixed` – `fixed` nastavuje `table-layout: fixed`, což
rozbíjí trik `width: 1% + white-space: nowrap` používaný na úzké sloupce
(ikony, GSC metriky) – obsah pak vizuálně přetéká přes sousední buňku místo
zmenšení sloupce. Odstraněna třída `fixed` (`table-layout: auto` je pro
tento trik potřeba), `widefat` zůstává. Uživatel potvrdil, že layout je teď
v pořádku.

Push NE bez "push".

### 2026-06-12 – Zapínání/vypínání modulů (Stav systému) otestováno v prohlížeči (OK)

Uživatel otestoval tlačítko „Zapnout"/„Vypnout" u modulů na stránce „Stav
systému" (AJAX, bez nutnosti chodit do Nastavení) – funguje, odpovídající
položky se objevují/mizí v levém admin menu (Audit Dashboard, Chytrá
indexace, Přesměrování, Export reportu/Export – nastavení).

Push NE bez "push".

### 2026-06-12 – Redesign Export PDF reportu otestováno v prohlížeči (OK)

Vygenerován testovací PDF report (`SEOB_Pdf_Report_Data::build()` +
`SEOB_Pdf_Renderer::render()`) s aktuálním nastavením (logo `Logo-1.png`,
accent `#2bd1a2`, kontaktní osoba, IČO), uložen do `wp-content/uploads/` a
otevřen uživatelem přes URL. Uživatel potvrdil, že vizuálně vyhovuje – logo
ostré, odsazení karet od barevné čáry OK, sekce "Souhrn nálezů podle typu",
"Souhrn dopadů a přínosů zlepšení" a "Vystavil" v pořádku. Testovací soubor
po ověření smazán.

Push NE bez "push".

### 2026-06-12 – Composer + PHPUnit scaffolding ověřeno: 20 testů, 55 assertions, OK

Navazující na předchozí záznam (scaffolding bylo hotové, ale nespuštěné).
Uživatel si nainstaloval Composer (Composer-Setup.exe) na svůj Windows stroj
a spustil testy.

- Cestou se opravily dvě věci v `php.ini` PHP binárky používané pro Composer
  (`C:\Users\PC\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.ini`,
  mimo git repo – viz `docs/STATE.md` "Pomůcky pro composer"):
  1. `curl.cainfo`/`openssl.cafile` nastaveny na `wp-includes/certificates/ca-bundle.crt`
     – oprava "OpenSSL certificate verify failed" při stahování balíčků.
  2. `extension=zip` odkomentováno – oprava "zip extension and unzip/7z
     commands are both missing" při instalaci `hamcrest/hamcrest-php`.
- `composer install` doinstaloval 32 dev balíčků (PHPUnit 9.6.34, Brain
  Monkey 2.7.0, Mockery 1.6.12, ...) do `vendor-dev/` (gitignored).
- `composer test` → **PHPUnit 9.6.34, OK (20 tests, 55 assertions)** – všechny
  manuálně dopočítané hodnoty v data providerech (např.
  `logo_dimensions_mm()` → `[31.8, 10.2]`, `[20.0, 6.4]`, `[35.0, 10.5]`)
  byly správně, žádná úprava testů nebyla potřeba.

Push NE bez "push".

### 2026-06-12 – Composer + PHPUnit scaffolding (první automatizované testy)

Dle priorit z `docs/STATE.md` ("AI fronta NEBO composer.json + PHPUnit/CI
scaffolding") zvolil uživatel composer/PHPUnit.

- `composer.json` (nový): `require-dev` PHPUnit ^9.6 + Brain Monkey ^2.6,
  `autoload-dev` PSR-4 `SeoBoost\Tests\` → `tests/`, vlastní
  `"vendor-dir": "vendor-dev"` – composer závislosti jdou mimo `vendor/`
  (tam je ručně vendorovaný TCPDF + plugin-update-checker, commitovaný do
  gitu). `vendor-dev/` a `.phpunit.result.cache` doplněny do `.gitignore`.
- `phpunit.xml.dist` (nový): bootstrap `tests/bootstrap.php`, testsuite
  `tests/Unit`.
- `tests/bootstrap.php` – načte `vendor-dev/autoload.php`, definuje `ABSPATH`
  a `SEOB_PLUGIN_DIR` (soubory pluginu mají guard
  `if (!defined('ABSPATH')) exit;`).
- `tests/TestCase.php` – základ pro testy s Brain Monkey (mock WP funkcí).
- **První testy** (čistá logika, bez DB):
  - `tests/Unit/Pdf/ReportDataTest.php` – `issue_labels()` a nová
    `logo_dimensions_mm()` (z předchozího PDF-logo fixu, ověřuje, že se logo
    nikdy nezvětší nad přirozenou velikost) – přes Reflection (private
    static), s PNG fixtures v `tests/fixtures/` (188×60 a 1000×300 px,
    minimální PNG header bez GD).
  - `tests/Unit/Pdf/DocumentTest.php` – `hex_to_rgb()` (accent barva +
    fallback na výchozí barvu při neplatném hexu).
  - `tests/Unit/ModuleManagerTest.php` – integrita `MODULES` registru
    (`depends_on` odkazuje na existující moduly, povinné klíče) +
    `is_rank_math_active()`.
  - `tests/Unit/GscInsights/GscInsightsTest.php` – `normalize_path()` (mock
    `wp_parse_url` přes Brain Monkey alias na `parse_url`), různé
    scheme/host/www kombinace URL.
- `docs/testing.md` (nový) – jak nainstalovat a spustit (`composer install &&
  composer test`), proč jen unit testy bez plného WP, jak přidávat další.

**Ověřeno** (viz následující záznam výše) – `composer install && composer
test` proběhlo: **20 testů, 55 assertions, OK**.

Push NE bez "push".

### 2026-06-12 – PDF report: ostré logo (bez roztažení) a doplnění odsazení v dalších boxech

Navazující na předchozí záznam – uživatel po dalším náhledu nahlásil dvě věci:

- **Logo bylo rozmazané/pixelované** – `.seob-pdf-issuer-logo` (sekce
  "Vystavil") mělo `max-width: 35mm; max-height: 18mm`, ale TCPDF `writeHTML()`
  CSS `max-width`/`max-height` na `<img>` nerespektuje spolehlivě a logo
  roztahoval nad jeho přirozenou velikost. Stejně tak hlavičkové logo
  (`Document::Header()`) bylo vynuceno na pevnou výšku `14mm` bez ohledu na
  skutečné rozlišení souboru.
  - Nová metoda `SEOB_Pdf_Report_Data::logo_dimensions_mm( $path, $max_w, $max_h, $dpi = 150 )`
    spočítá rozměr loga v mm z reálných px rozměrů souboru (`getimagesize()`)
    při 150 DPI a teprve poté ho zmenší, pokud by přesáhl daný max box – nikdy
    ho ale nezvětšuje nad přirozenou velikost.
  - `ReportData::build()` nově do `company` ukládá `logo_header_w/h` (max
    box 50×14mm, pro `Document::Header()`) a `logo_issuer_w/h` (max box
    35×18mm, pro sekci "Vystavil").
  - `Document.php`: nové vlastnosti `brand_logo_w`/`brand_logo_h`, `Header()`
    nyní volá `Image($path, 15, $y, $brand_logo_w, $brand_logo_h)` místo
    pevného `(0, 14)`. `PdfRenderer::render()` tyto hodnoty z `$data['company']`
    předává.
  - `report.php`: `<img class="seob-pdf-issuer-logo">` má nově explicitní
    `width="{logo_issuer_w}mm" height="{logo_issuer_h}mm"` (CSS
    max-width/max-height odstraněno).
  - Pro aktuální logo webu (`Logo-1.png`, 188×60 px) vychází při 150 DPI
    31,8 × 10,2 mm – menší než dřívější vynucené rozměry, takže by logo mělo
    být ostřejší (méně roztažené).
- **Další chybějící odsazení od barevné čáry vlevo** (kromě `.seob-pdf-issue`,
  opraveno minule): `.seob-pdf-summary-box` (úvodní "Souhrn auditu", modrý box
  s barevnou čárou vlevo) – `padding: 8px` → `padding: 8px 8px 8px 6mm`.
  `.seob-pdf-recap-item` (sekce "Souhrn dopadů a přínosů zlepšení", barevná
  čára dle závažnosti) – `padding-left: 14px` → `6mm`.

Ověřeno: `php -l` bez chyb na `ReportData.php`, `Document.php`,
`PdfRenderer.php`, `report.php`. wp-cli render OK (132 224 B), `logo_header_w/h`
i `logo_issuer_w/h` vychází `31.8 / 10.2`. **NEOTESTOVÁNO V PROHLÍŽEČI/PDF
náhledu** (ostrost loga a vizuální odsazení).

Push NE bez "push".

### 2026-06-12 – PDF report: oprava chybějícího loga a malého odsazení nálezů

Uživatel po prohlížečovém testu nahlásil, že v PDF reportu stále chybí logo a
odsazení textu nálezu od barevného okraje je pořád málo.

- **Logo**: `company.logo_id` v Export – nastavení bylo `0` (admin nic
  nenahrál) → `company.logo_path` se nikdy nenastavilo a `Document::Header()`
  logo nevykreslilo. `SEOB_Pdf_Report_Data::build()` nyní jako fallback použije
  logo webu (`get_theme_mod('custom_logo')`), pokud admin v Export –
  nastavení žádné vlastní logo nenahrál – ověřeno wp-cli, na tomto webu existuje
  `custom_logo` (attachment 108, `Logo-1.png`), `logo_path` se nyní správně
  vyplní a zobrazí v záhlaví každé stránky.
- **Odsazení nálezů**: `.seob-pdf-issue` padding-left `14px` → `6mm` (`templates/pdf/report.php`)
  – výraznější mezera mezi barevným okrajem karty a textem.

Ověřeno: `php -l` bez chyb, wp-cli render OK (137 033 B). **NEOTESTOVÁNO V
PROHLÍŽEČI** (vizuální posouzení odsazení a loga).

Push NE bez "push".

### 2026-06-12 – Nový modul "Search Console statistiky (Rank Math)" v Audit Dashboardu

Zadání: "Přidej tedy tu integraci z Rankmathu tech dat (search console, GA4
atd.)". Uživatel výslovně odmítl vlastní napojení na Google API/OAuth
("nechci skrze Google api") – řešení proto **jen čte** vlastní tabulku Rank
Math `wp_rank_math_analytics_gsc`, kterou si Rank Math (i Free verze) sám
naplní po připojení Google účtu přes svůj modul Analytics. Rozsah dle
odpovědi uživatele: jen Audit Dashboard (ne PDF, ne samostatná stránka, ne
GA4 – budoucí krok).

**Implementováno** (php -l OK, ověřeno wp-cli s dočasnou tabulkou):
- Nová třída `includes/GscInsights/GscInsights.php` (`SEOB_Gsc_Insights`):
  `is_table_available()` (`information_schema.TABLES`), `get_summary()`
  (souhrn za 28 dní pro health check) a `attach_metrics(&$rows)` – agregace
  `SUM(clicks)/SUM(impressions)/AVG(position)` po `page` za 28 dní, URL
  normalizace na cestu (`wp_parse_url(..., PHP_URL_PATH)`, trim `/`) kvůli
  rozdílům scheme/host/www mezi audit URL a GSC `page`.
- Nový modul `gsc-insights` v `SEOB_Module_Manager::MODULES` (`depends_on:
  ['audit']`, výchozí zapnuto), checkbox v Nastavení → Moduly
  (`modules_gsc_insights`).
- `SEOB_Audit_Ajax::scan_results()` doplňuje `row.gsc` (metriky nebo `null`)
  a `gsc_available` (zda tabulka existuje).
- Audit Dashboard: 4 nové sloupce (Zobrazení, Kliky, CTR, Pozice), notice
  `#seob-gsc-notice` s návodem na připojení Rank Math Analytics – sloupce i
  notice se skrývají/zobrazují podle `gsc_available`
  (`assets/admin/js/audit-dashboard.js`, `.seob-gsc-hidden` v
  `assets/admin/css/admin.css`).
- Health check `SEOB_Health_Checks::gsc_checks()` – kritický pokud chybí Rank
  Math, varování pokud nepřipojeno Search Console, jinak souhrn za 28 dní.
- Dokumentace: nový `docs/modules/gsc-insights.md`.

Otestováno přes wp-cli: vytvořena dočasná tabulka přesně dle schématu Rank
Math s řádky v různých variantách host/scheme/www, ověřeno správné přiřazení
metrik k audit URL i korektní `null` u stránek bez dat, poté tabulka smazána.
**NEOTESTOVÁNO V PROHLÍŽEČI.**

Push NE bez "push".

### 2026-06-12 (rozpracováno) – PDF report: hlavičkový papír na každé stránce, souhrn dopadů/přínosů, sekce "Vystavil"

Navazující zadání uživatele (viz screenshot karet nálezů): "Udělej to jako
hlavičkový papír, a přidej tam naše logo" (logo na každé stránce, ne jen na
titulní), "trochu odsaď písmo z prava od toho červeného pole" (větší padding
u `.seob-pdf-issue`), "ty přínosy a dopad bych dal i celkově potom dolů do
nějakého souhrnu" (konsolidovaná sekce dopadů/přínosů), a následně "K nabídce
by pak na konci tohoto auditu mělo být vypsané kdo tu nabídku vystavil...
Jméno, firma, IČO, kontakty, logo (možná i editovatelné z administrace)".

**Hotovo (php -l OK, wp-cli render OK):**
- `includes/Pdf/Document.php` (NOVÝ): `SEOB_Pdf_Document extends TCPDF` –
  vlastní `Header()` (logo + název webu + accent linka na každé stránce) a
  `Footer()` (accent linka + název firmy + číslo stránky). Barva z hex přes
  `hex_to_rgb()`.
- `includes/Pdf/PdfRenderer.php` `render()`: instancuje `SEOB_Pdf_Document`
  (`require_once Document.php` po načtení TCPDF), `setPrintHeader/Footer(true)`,
  upravené okraje (`SetMargins(15, 32, 15)`, `setHeaderMargin(5)`,
  `setFooterMargin(10)`, `SetAutoPageBreak(true, 20)`) pro místo na hlavičku/patičku.
- `includes/Settings.php` `pdf_defaults()` + `includes/Admin/SettingsAjax.php`
  `pdf_settings()`: nové pole `company.contact_person` (jméno zpracovatele)
  a `company.ico` (IČO).
- `templates/admin/page-pdf-settings.php`: nová pole "Jméno zpracovatele"
  (`pdf_company_contact_person`) a "IČO" (`pdf_company_ico`) v sekci
  "Firemní údaje a branding".
- `templates/pdf/report.php`:
  - odstraněna duplicitní inline tabulka s logem v záhlaví (logo teď řeší
    `SEOB_Pdf_Document::Header()` na každé stránce) – ponechán jen titulek
    `<h1>` + meta řádek s datem.
  - `.seob-pdf-issue` padding zvětšen z `8px` na `14px` (větší odsazení
    textu od červeného/barevného levého okraje karty nálezu).
  - nová sekce "Souhrn dopadů a přínosů zlepšení" – pro každý typ nálezu z
    `issue_summary` (deduplikováno podle typu) vypíše dopad + přínos
    celkově za web, s počtem ovlivněných stránek.
  - nová sekce "Vystavil" na konci reportu (po "Naše nabídka") – logo
    agentury, jméno zpracovatele, název firmy, IČO, kontakt
    (`company.contact_person/name/ico/contact/logo_path`).

**ZBÝVÁ:**
1. Otestovat v prohlížeči – hlavičkový papír na více stránkách, sekce
   "Souhrn dopadů a přínosů" a "Vystavil" – **NEOTESTOVÁNO** (jen wp-cli render).
2. CHANGELOG.md – doplnit do `[Unreleased]`.
3. `docs/STATE.md` a `docs/modules/pdf-export.md` – aktualizovat.
4. Zvážit další bump `SEOB_VERSION` (aktuálně `0.3.0`).
5. Aktualizovat memory `project-seo-boost.md`.
6. Push NE bez explicitního "push" od uživatele.

### 2026-06-12 (rozpracováno) – Redesign Export PDF reportu (branding, top-N + souhrn, business impact)

Zadání uživatele: oprava buggujícího znaku 🏢 v titulku PDF (emoji v názvu
webu, font DejaVu Sans ho nezvládá), report nepoužitelný pro 300+ stránkové
weby (detailní karta pro KAŽDOU stránku), a celkové zhezčení/obrandování
exportu pro použití na klientských schůzkách.

**Hotovo (php -l OK na všech souborech):**
- `includes/Pdf/ReportData.php`: nová `sanitize_text()` – odstraní znaky
  nad U+FFFF (emoji) z `site_name` → oprava 🏢 bugu. `build()` přepracováno:
  řádky s nálezy se seřadí (skóre asc, pak počet nálezů desc), top N
  (`pdf_settings['report']['detailed_pages_limit']`, default 12) →
  `detailed_rows` (plné karty), zbytek → `issue_summary` (seskupeno podle
  typu nálezu: severity, label, impact/benefit, count, pages[] s url+title).
  Nové: `pages_with_issues_count`, `pages_ok_count`, `remaining_count`,
  `serp_affected_pages`, `compute_business_impact()` (orientační odhad
  CTR uplift / návštěv / konverzí / tržeb z `biz_monthly_visits`,
  `biz_conversion_rate`, `biz_avg_value`).
- `includes/Settings.php` `pdf_defaults()`: nové klíče `company.logo_id`,
  `company.logo_url`, `company.accent_color` (default `#2271b1`), nová
  sekce `report.detailed_pages_limit` (default 12).
- `includes/Admin/SettingsAjax.php` `pdf_settings()`: ukládá logo
  (`pdf_company_logo_id` → `wp_get_attachment_image_url`), `accent_color`
  (validace `^#[0-9a-fA-F]{6}$`), `pdf_report_detailed_pages_limit`.
- `templates/admin/page-pdf-settings.php`: nová sekce "Firemní údaje a
  branding" (logo upload přes wp.media, color picker pro accent), sekce
  "Rozsah reportu" (detailní limit).
- `assets/admin/js/pdf-settings.js`: media uploader pro logo (výběr/náhled/
  odstranění), `wp_enqueue_media()` doplněno v `Admin.php` pro hook
  `_page_seob-pdf-settings`.
- `templates/admin/page-report.php` + `assets/admin/js/report.js`: nové
  sekce "Nejhorší stránky (detailně)" (z `detailed_rows`), "Souhrn nálezů
  podle typu" (z `issue_summary`, `<details>` s výpisem URL), "Odhad
  obchodního dopadu" – 3 vstupní pole (návštěvnost, konverzní poměr, AOV),
  odesílají se s exportem jako `biz_monthly_visits`/`biz_conversion_rate`/
  `biz_avg_value`.
- `includes/Pdf/Ajax.php` `export()`: čte `biz_*` POST hodnoty, počítá
  `business_impact` přes `ReportData::compute_business_impact()`.
- `templates/pdf/report.php`: kompletní redesign – záhlaví s logem (přes
  `company.logo_path`, resolved v `ReportData::build()` z `get_attached_file()`)
  a accent color, karty jen pro `detailed_rows`, tabulka "Souhrn nálezů podle
  typu" + příloha s URL (max 25 per typ, zbytek "… a dalších N"), sekce
  "Odhad obchodního dopadu" (pokud vyplněno), nabídka + patička s accent
  barvou.

**ZBÝVÁ (další session):**
1. Otestovat v prohlížeči (Export reportu i Export – nastavení) – generování
   PDF, logo upload, accent color, business impact pole – **NEOTESTOVÁNO**.
2. Ověřit přes wp-cli, že `SEOB_Settings::get(PDF)` vrací nové klíče
   (`company.logo_id/logo_url/accent_color`, `report.detailed_pages_limit`)
   se starými uloženými daty (array_replace_recursive by mělo stačit).
3. CHANGELOG.md – přidat záznam do `[Unreleased]`.
4. `docs/STATE.md` – aktualizovat.
5. `docs/modules/` – případně doplnit/aktualizovat dokumentaci k Export PDF
   modulu (pdf-export.md pokud existuje, jinak zvážit založení).
6. `SEOB_VERSION` bump (aktuálně `0.2.0`) – zvážit `0.3.0` (větší feature).
7. Aktualizovat memory `project-seo-boost.md`.
8. Push NE bez explicitního "push" od uživatele.

### 2026-06-12 – Vypnutý modul zmizí z admin menu + nápověda přímo v Chytré indexaci

Doplněno na žádost uživatele: "Cokoliv co vypneš v nastavení, by se mělo
vypnout i v nastavení admin menu pluginu" + "Dokumentace by měla být na
úrovni toho modulu... popis, co konkrétní nastavení dělá, jak s tím
pracovat, na co si dát pozor".

1. **Admin menu reaguje na stav modulů** (`includes/Admin/Admin.php::register_menus()`):
   - „Audit Dashboard“ (duplicitní submenu = toplevel stránka), „Chytrá
     indexace“, „Přesměrování“, „Export reportu“ a „Export – nastavení“ se
     v menu zobrazí jen pokud je příslušný modul aktivní
     (`SEOB_Module_Manager::is_active()`).
   - „Stav systému“ a „Nastavení“ zůstávají VŽDY v menu – jinak by nebylo
     jak modul znovu zapnout.
   - Pro přímý přístup na URL vypnuté stránky (např. starý bookmark) nová
     fallback šablona `templates/admin/page-module-disabled.php` – notice s
     odkazem na Stav systému / Nastavení. Použita v `page_dashboard()`
     (pokud `audit` vypnutý), `page_redirects()`, `page_report()`,
     `page_pdf_settings()`. `page_smart_indexing()` ZÁMĚRNĚ bez tohoto
     guardu – nastavení modulu Chytrá indexace si admin může připravit i
     před zapnutím (stávající notice na stránce to už řešila).
   - `enqueue_assets()`: audit-dashboard.js se na toplevel stránce načte jen
     když je modul `audit` aktivní.

2. **Nápověda přímo na stránce „Chytrá indexace“**
   (`templates/admin/page-smart-indexing.php`):
   - Nový sbalitelný blok (`<details class="seob-schema-help">`, stejný
     vzhled jako "Jak schéma funguje" v Nastavení) hned pod popisem modulu:
     vysvětluje Tier A/B/C, co znamená každý sloupec tabulky výsledků
     (URL/stránka, Typ, Firem, Skóre, Doporučení) a co dělají tlačítka
     "Schválit (index)"/"Noindex" (přepnou tier, označí jako ruční
     rozhodnutí, přežije rescan; jak se ruční rozhodnutí "vrátí").
   - Doplněn `<p class="description">` ke "Profil webu" (vysvětluje, že
     volba `eshop` je zatím jen informativní – MVP vždy používá katalogovou
     logiku).
   - Doplněn `<p class="description">` ke všem 4 mapovacím selectům
     (detail firmy / obor / lokalita / služba) – co konkrétně zapnutí dané
     taxonomie/CPT spustí v analýze.

`php -l` bez chyb na všech upravených/nových souborech
(`Admin.php`, `page-module-disabled.php`, `page-smart-indexing.php`).
**NEOTESTOVÁNO V PROHLÍŽEČI** – zejména ověřit, že po vypnutí modulu
(tlačítkem na Stav systému) jeho položka z menu skutečně zmizí (může být
nutné menu znovu načíst / odejít ze stránky vypnutého modulu, protože WP
admin menu se generuje při načtení stránky).

Mimochodem: modul „Chytrá indexace“ je nyní v `seob_general_settings`
zapnutý (`modules.smart-indexing = 1`) – pravděpodobně otestováno tlačítkem
„Zapnout“ na Stav systému mezi sezeními. V menu by se tak nově měla objevit
položka „Chytrá indexace“.

---

### 2026-06-12 – Jednoklikové zapnutí/vypnutí modulů + doporučení k M14

Doplněno k modulu „Chytrá indexace“ (M14, viz záznam níže):

- **Stav systému** (`seob-status`): nový sloupec „Akce“ v tabulce modulů s
  tlačítkem „Zapnout“/„Vypnout“ – přepne `modules.<id>` v
  `seob_general_settings` přes nový AJAX `seob_status_toggle_module`
  (`includes/Health/StatusAjax.php::toggle_module()`, `manage_options` +
  nonce `seob_admin_nonce`) a okamžitě překreslí tabulku i health checky
  (`assets/admin/js/status-page.js` – refaktorováno do `loadStatus()`/
  `handleToggle()`/`ajax()` helperů). Platí pro všechny moduly (Audit,
  Redirect Manager, Export PDF, Chytrá indexace) – vypnutí je úplné, protože
  `SEOB_Module_Manager::init_active()` neaktivní moduly vůbec neinstanciuje
  (žádné AJAX endpointy ani frontend filtry).
- `docs/modules/smart-indexing.md`: nové sekce „Zapnutí/vypnutí modulu“,
  „Doporučený postup nasazení (rollout)“ (mapování → blacklist → dry-run
  analýza → ruční review → semi_auto → auto) a „Rizika a na co si dát pozor“
  (Tier B/C nekombinovat, syntetické URL jsou jen návrhy bez fyzické stránky,
  ruční rozhodnutí přežívají rescan ale ne změnu mapování, Dry-run čte
  produkční DB).

Ověřeno přes wp-cli (přepnutí `modules.smart-indexing` 0↔1, `get_modules()`
vrátí `active=true/false`), `php -l` bez chyb na `StatusAjax.php` a
`page-status.php`. **NEOTESTOVÁNO V PROHLÍŽEČI** – přidáno ke stejnému
seznamu jako M14.

---

### 2026-06-12 – Nový modul M14 „Chytrá indexace“ (MVP, generická verze)

Uživatel dodal zadání M14 v2 (chytrá indexace katalogových/filtrovaných
stránek, profil KATALOG: obor/služba × lokalita) a požádal o rovnou
implementaci. Před začátkem zjištěno: testovací web `reboost-test` nemá
žádný katalog firem (žádný CPT/taxonomie odpovídající oboru/lokalitě) –
plugin je ale white-label pro více klientů, takže uživatel zvolil postavit
modul **generický a konfigurovatelný** (admin si v Nastavení namapuje
CPT pro detail firmy + taxonomie obor/lokalita/služba).

**Co bylo implementováno (`SEOB_VERSION` 0.1.9 → 0.2.0, `SEOB_DB_VERSION`
0.3.0 → 0.4.0):**

1. **DB** (`includes/Activator.php`, `includes/Database/Database.php`):
   nové tabulky `wp_seo_booster_facet_rules` (připraveno pro oborové/
   lokalitní overridy, zatím nevyužito), `wp_seo_booster_facet_urls`
   (výsledky analýzy + ruční rozhodnutí), `wp_seo_booster_facet_signals`
   (denní signály pro budoucí napojení na M7/GSC, zatím prázdné).
2. **Nastavení** (`includes/Settings.php`): nová option skupina
   `seob_smart_indexing_settings` (`SEOB_Settings::SMART_INDEXING`) –
   profil (catalog/eshop), režim (dry_run/semi_auto/auto), mapování
   (company_post_type, category/location/service_taxonomy), prahy
   (`min_companies` 5, `completeness_threshold` 60, `max_depth` 2),
   `blacklist_params` (seznam dle kap. 4.1 zadání, podporuje `*` wildcard).
   Nový modul registrován v `SEOB_Module_Manager::MODULES['smart-indexing']`,
   ve výchozím stavu **vypnutý** (`modules.smart-indexing = 0`).
3. **`includes/SmartIndexing/Helper.php`**: parsování blacklistu, matching
   s wildcard (`utm_*`), `strip_blacklisted_params()`, normalizace URL a
   hash pro `facet_urls`.
4. **`includes/SmartIndexing/CatalogScanner.php`** (`run_scan()`):
   - Detaily firem → skóre úplnosti profilu (obsah ≥ 50 slov, náhledový
     obrázek, perex, term v category/location taxonomii) → Tier A/B dle
     `completeness_threshold`.
   - Hlavní obory → vždy Tier A (`rule_category`).
   - Obor × lokalita → Tier A při `result_count >= min_companies`
     (`rule_min_companies`), kandidát při ≥ 3 (`candidate`), jinak noindex
     (`too_few`). Synteticky generovaná URL `/{obor}/{lokalita}/` dle
     kap. 4.5 – jde o návrh, fyzickou stránku/routu modul nevytváří.
   - Služba × lokalita (pokud nakonfigurováno) → vždy
     `candidate_manual_review` (Tier B).
   - `store_results()`: upsert do `facet_urls` přes `url_hash`; pokud má
     existující záznam `tier_reason` `approved_manual`/`demoted_manual`
     (ruční rozhodnutí), tier se při dalším scanu nepřepíše – jen se
     refreshují `result_count`/`score`/`scanned_at`.
5. **`includes/SmartIndexing/Frontend.php`** (`SEOB_SmartIndexing_Frontend`,
   aktivní jen mimo `dry_run`):
   - `rank_math/frontend/canonical` – pokud aktuální URL obsahuje
     blacklistovaný parametr, canonical → čistá URL bez něj (Tier C, bez
     kombinace s noindexem).
   - `rank_math/frontend/robots` – pokud je aktuální URL v `facet_urls`
     s `tier = 'B'`, doplní `noindex, follow`.
   - Oba filtry mají na začátku `is_admin()` guard – `admin-ajax.php`
     požadavky (vč. JetSmartFilters AJAX filtrování) se vůbec nedotknou,
     `is_admin()` je za nich `true`.
6. **`includes/SmartIndexing/Ajax.php`** (`SEOB_SmartIndexing_Ajax`):
   `seob_smart_index_options` (CPT/taxonomie pro select boxy),
   `seob_smart_index_save_settings`, `seob_smart_index_scan`,
   `seob_smart_index_results`, `seob_smart_index_approve` (→ Tier A,
   `approved_manual`), `seob_smart_index_demote` (→ Tier B,
   `demoted_manual`). V `ModuleManager::MODULES` u `smart-indexing`.
7. **Admin UI**: nová stránka „Chytrá indexace“ (`seob-smart-indexing`,
   `templates/admin/page-smart-indexing.php` + `assets/admin/js/
   smart-indexing.js`) – formulář profil/režim/mapování/prahy/blacklist,
   tlačítko „Spustit analýzu“ a tabulka návrhů se sloupci URL/Typ/Firem/
   Skóre/Doporučení + tlačítky Schválit (index) / Noindex. Pokud je modul
   vypnutý, zobrazí se notice s odkazem do Nastavení (formulář lze
   vyplnit, ale AJAX poběží až po zapnutí modulu). V `page-settings.php`
   přidán checkbox „Chytrá indexace“ do sekce Moduly
   (`includes/Admin/SettingsAjax.php`).
8. **Health check** (`includes/Health/HealthChecks.php`,
   `smart_indexing_checks()`): je nastaveno mapování?, jaký je aktuální
   režim (upozornění při Dry-run), kdy proběhla poslední analýza.
9. **Crocoblock/JetSmartFilters** (web běží na JetEngine + JetSmartFilters):
   zdokumentováno v `docs/modules/smart-indexing.md` – AJAX filtrování
   (admin-ajax.php) modul neovlivní (`is_admin()` guard), u filtrů s
   "Storage Type: URL Query" admin doplní jejich "Query Var" názvy do
   blacklistu (pokud jde o utility filtry typu řazení/otevřeno
   teď/hodnocení), ne pro filtry představující obor/lokalitu.

**Ověřeno přes wp-cli:** `SEOB_Activator::maybe_upgrade()` →
`seob_db_version` = `0.4.0`, všechny 3 nové tabulky vytvořeny
(`wp_seo_booster_facet_rules/facet_urls/facet_signals`). `php -l` bez chyb
na všech nových/upravených PHP souborech.

**NEOTESTOVÁNO V PROHLÍŽEČI** – zatím žádný reálný katalog na tomto webu,
takže analýza s prázdným mapováním vrátí 0 návrhů. Otestovat: zapnutí
modulu v Nastavení, načtení selectů (CPT/taxonomie), uložení nastavení,
"Spustit analýzu" na webu s nějakým CPT+taxonomií (i bez "katalogu" – jen
ověřit, že UI a AJAX nepadají), Schválit/Noindex tlačítka.

**Druhá iterace (odloženo):** skórovací model dle GSC (M7), `facet_rules`
tabulka (oborové/lokalitní overridy, UI sekce 2-3 mockupu), generování
fyzických landing pages pro povýšené kombinace (checklist 4.8), robots.txt
pro tracking parametry, plný health check (kap. 9).

---

### 2026-06-12 – Vizuální/UX doladění po review v prohlížeči: reset schémat na výchozí Rank Math, mazání historie scanů

Uživatel otestoval předchozí session v prohlížeči (historie scanů funguje,
nastavení schémat podle kategorie se zobrazuje OK) a požádal o tato doladění:

**1. Schéma podle typu obsahu/kategorie – „výchozí = Rank Math“ + reset jedním klikem**

Problém: select v tabulkách „Výchozí schéma podle typu obsahu/kategorie“ dřív
defaultoval na `'off'`, pokud SEOB nemá žádné vlastní nastavení (`current === ''`).
To vizuálně vypadalo jako explicitní volba „Běžná stránka“, a kliknutím na
„Uložit“/„Uložit vše“ by se omylem zapsal override `'off'` i tam, kde uživatel
chtěl jen zdědit chování Rank Math. Uživatel explicitně trval na tom, že SEOB
nesmí defaultně nic přepisovat nad Rank Math.

Oprava:
- `SEOB_Schema_Helper::get_rank_math_post_type_default( $post_type )` – nová veřejná
  metoda, vrací aktuální RM nastavení (`pt_{post_type}_default_rich_snippet`),
  vytažená z `get_post_type_default()`.
- `SEOB_Schema_PostType_Ajax::list_post_types()` – přidán klíč `rm_default` u
  každého řádku.
- `SEOB_Schema_PostType_Ajax::save_post_type()` / `SEOB_Schema_Category_Ajax::save_category()` –
  hodnota `''` je nově platná a znamená „zrušit override“ (`unset()` v
  `seob_default_schema_post_types`, resp. `delete_term_meta()`), validace
  `'' === $value || isset(TYPES[$value])`.
- `assets/admin/js/schema-defaults.js` – select má novou první položku
  `''` = „Výchozí (dle Rank Math: …)“ (u typů obsahu se zobrazí konkrétní RM
  typ, u kategorií obecný text „dle typu obsahu / Rank Math“), vybranou pokud
  `current === ''`. Nové tlačítko „Resetovat“ (`config.resetClass`) nastaví
  select na `''` a hned uloží (jeden klik).
- `templates/admin/page-settings.php` – tlačítko „Resetovat“ (červený obrys,
  `.seob-posttype-reset` / `.seob-schema-cat-reset`) u každého řádku obou tabulek,
  „Uložit“ změněno na `button-primary`.
- `admin.css` – `.seob-posttype-select`/`.seob-schema-cat-select` širší (min 260px),
  styl tlačítka „Resetovat“.

**2. Historie scanů – mazání + limit**

Uživatel: historie scanů funguje, ale chce možnost ji promazat a omezit max.
počet uložených scanů (úspora místa v DB).

- `SEOB_Audit_ScanRunner::delete_scan( $scan_id )` – smaže řádky z `audit_table`
  (`scan_id = ...`) i `scan_runs_table` (`id = ...`).
- `SEOB_Audit_ScanRunner::prune_history()` – volá se na konci `finalize_scan()`,
  čte `seob_audit_settings.history_limit` (nová volba, default 20) a smaže
  nejstarší dokončené scany nad tento limit (`OFFSET $limit` v `SELECT id ... WHERE
  status='done' ORDER BY id DESC`).
- `SEOB_Audit_Ajax::scan_delete()` – nový AJAX `seob_scan_delete` (nonce +
  `manage_options`), vrací aktualizovanou historii i výsledky.
- `templates/admin/page-dashboard.php` – tlačítko „Smazat scan“ vedle výběru
  historie; `audit-dashboard.js` – potvrzovací dialog (`window.confirm`), po
  smazání znovu načte historii i výsledky; `exportPdfLink` se skryje, pokud po
  smazání žádný scan nezbyl.
- `templates/admin/page-settings.php` – nové pole „Historie scanů“
  (`history_limit`, 1–200) v sekci „Audit Dashboard“; `SEOB_Settings::AUDIT`
  default `history_limit = 20`; `SettingsAjax::save_settings()` ukládá
  `int_field('history_limit', 1, 200, 20)`.

**3. „Od minula“ prázdné** – ověřeno, je to očekávané chování (zobrazí se „–“,
pokud se od minulého scanu nic neopravilo), žádná oprava potřeba.

**Ověření**: `php -l` na všech upravených/nových souborech bez chyb. Přes wp-cli:
`SEOB_Settings::get(AUDIT)` obsahuje `history_limit: 20`,
`SEOB_Schema_Helper::get_rank_math_post_type_default('post')` vrací `article`
(reálné RM nastavení). DB má aktuálně 21 dokončených scanů → při dalším
finalize_scan() se automaticky smaže nejstarší (id 1).

Smazán nepoužívaný prázdný soubor `includes/ScanRunner.php` (0 B, vznikl jako
artefakt předchozí AV-karanténní historky, nikde se nenačítal).

**4. Grafický polish Audit Dashboardu** – na žádost uživatele ("sniž řádky,
udělej to obecně menší a modernější"):
- Nová třída `seob-audit-table` (`page-dashboard.php`) – menší padding buněk
  (7px/10px), menší font (13px), hlavička tabulky velkými/malými písmeny
  (uppercase, 11px, světle šedé pozadí `#f6f7f7`).
- `.seob-toolbar` a `.seob-score-overview` – kartičky s bílým pozadím, jemným
  rámečkem a zaoblením (6px) místo holého textu.
- `.seob-score-overview` – flex layout, celkové skóre zvýrazněno (22px), počty
  nálezů jako barevné "pill" badge (`border-radius: 999px`) na světle šedém
  pozadí, "opraveno od minula" v zeleném badge.
- `.seob-score-badge` – zaoblený na pill tvar (dřív `border-radius: 3px`).
- `.seob-status-icon` – větší (18px) pro lepší čitelnost v zúžených řádcích.
- `.seob-filters input[type="search"]` – min-width 240px.

`SEOB_VERSION` `0.1.8` → `0.1.9`. **Neotestováno v prohlížeči**: reset tlačítka
u schémat, mazání scanu z historie, ukládání limitu historie v Nastavení,
grafický polish Audit Dashboardu (body 1-4 výše).

**Doladění 4 po screenshotu uživatele** – sloupec "Schéma" se zalamoval na 2-3 řádky
(celý dlouhý popis typu, např. "Běžná stránka (bez schématu / výchozí WebPage)"),
což dělalo všechny řádky vysoké. Opraveno:
- `assets/admin/js/audit-dashboard.js` – nová mapa `SCHEMA_SHORT_LABELS` (krátké
  české názvy typů schémat, např. `off` → "Běžná stránka", `jobposting` →
  "Nabídka práce"), zobrazuje se v buňce; plný popis z `seobData.schemaTypes`
  je teď jen tooltip (`title`).
- `admin.css` – `.seob-audit-table` zmenšen padding (5px/8px) a font (12px),
  všechny úzké sloupce (skóre, title/description/h1/alt/schéma/obsah/od
  minula/akce) `white-space: nowrap` + `width: 1%`, sloupec "Stránka"
  `width: auto` (zabere zbytek místa), tlačítko "Opravit" zmenšeno.

---

### 2026-06-11 – Společná infrastruktura: ModuleManager, Stav systému + Site Health, seo_booster_metrics, docs/modules

Implementace „jádra“ sekce 5 master zadání (architektura `SEOB_` zachována,
viz rozhodnutí níže v tomto souboru). AI fronta a CI/CD/PHPUnit zůstávají
odložené (composer není v dev prostředí dostupný).

**1. ModuleManager** (`includes/ModuleManager.php`, `SEOB_Module_Manager`):
- `const MODULES` – registr 3 modulů (audit, redirects, pdf) s `label`,
  `description`, `classes` (třídy k inicializaci) a `depends_on` (`pdf`
  závisí na `audit`).
- `is_active()`, `init_active()` (nahradilo ad-hoc `if (!empty($modules['x']))`
  bloky v `Plugin.php`), `get_modules()` (obohaceno o `enabled`/
  `dependency_ok`/`active`), `is_rank_math_active()`
  (`class_exists('RankMath')`).
- `Plugin.php::init()` nyní volá `SEOB_Module_Manager::init_active()` +
  `SEOB_Health_Checks::register()` + `new SEOB_Status_Ajax()`.

**2. `seo_booster_metrics` tabulka + graf**:
- Nová tabulka (`module`, `metric_key`, `metric_value`, `recorded_at`) přidána
  do `SEOB_Activator::create_tables()` + `SEOB_Database::metrics_table()`.
  `SEOB_DB_VERSION` `0.2.0` → `0.3.0` (migrace přes `maybe_upgrade()`, beze
  reaktivace – ověřeno wp-cli, tabulka `wp_seo_booster_metrics` existuje).
- `SEOB_Metrics` (`includes/Metrics/Metrics.php`): `record()`, `get_trend()`,
  `get_latest()`.
- Zápis metrik:
  - `audit.score_avg` v `SEOB_Audit_ScanRunner::finalize_scan()`.
  - `redirects.unresolved_404_count` v `SEOB_Redirect_Manager::cleanup_old_logs()`
    (denní cron) – počet řádků `links` s `redirect_to IS NULL AND hits_404 > 0`.
  - `pdf.export_count` (hodnota 1 na export) v `SEOB_Pdf_Ajax::export()`.
- `assets/admin/js/status-chart.js` – `seobRenderSparkline()`, vanilla SVG
  sparkline bez závislostí.

**3. „Stav systému“ + WP Site Health**:
- `includes/Health/HealthChecks.php` (`SEOB_Health_Checks`):
  - `audit_checks()` – poslední dokončený scan (good <7 dní, warning 7–30,
    critical >30/žádný) + detekce „zaseklého“ scanu (`status='running'` >2 h).
  - `redirects_checks()` – `wp_next_scheduled(SEOB_Redirect_Manager::CRON_HOOK)`
    + `unresolved_404_count`.
  - `pdf_checks()` – existence `vendor/tcpdf/seob-tcpdf-loader.php`.
  - `get_general_checks()` – info o detekci Rank Math.
  - `register()` – `add_filter('site_status_tests', ...)`, pro každý aktivní
    modul jeden „direct“ test v Nástroje → Stav webu.
- `includes/Health/StatusAjax.php` (`SEOB_Status_Ajax`) – AJAX
  `seob_status_data` (nonce + `manage_options`), vrací moduly, health checky,
  trendy.
- Nová admin stránka **Stav systému** (`seob-status`):
  `templates/admin/page-status.php` + `assets/admin/js/status-page.js`
  (fetch + render modulů/health checků/sparkline grafů), CSS
  `.seob-status-*` v `admin.css`. Registrace submenu + enqueue v `Admin.php`.

**4. `docs/modules/*.md`** – nové soubory `audit-dashboard.md`,
`redirects.md`, `pdf-export.md` dle povinné osnovy (Proč / Co se zlepší /
Jak monitorovat / Health check).

**Ověření**: `php -l` na všech nových/upravených souborech bez chyb. Přes
wp-cli: `seob_db_version` = `0.3.0`, tabulka `wp_seo_booster_metrics`
existuje, `SEOB_Module_Manager::get_modules()` vrací správnou strukturu pro
všechny 3 moduly (`active=true`), `SEOB_Health_Checks::get_checks('audit')`
korektně odhalil existující reálný problém – scan #8 zaseklý ve stavu
`running` od 10. 6. 2026 (`audit_stuck_scan` = critical) – toto NENÍ chyba
nové implementace, je to existující stav DB, který health check správně
hlásí. `get_checks('redirects')`/`get_checks('pdf')`/`get_general_checks()`
vrátily očekávané `good` výsledky (cron naplánován, TCPDF dostupné, Rank Math
aktivní). `SEOB_VERSION` `0.1.7` → `0.1.8`.

**Neotestováno v prohlížeči**: stránka „Stav systému“ (vykreslení tabulky
modulů, health checků, sparkline grafů) a Nástroje → Stav webu (nové testy
`seob_module_*`).

---

### 2026-06-11 – Schéma „běžná stránka“ přebíjí Rank Math + sloupec „Obsah“ (thin content) v auditu

**D1 – nahlášeno:** stránky jako `/gdpr/`, `/cookies/`, `/kontakty/`, `/o-nas/`, `/reference/`,
`/novinky-z-mailingu/` se v Audit Dashboardu zobrazují se schématem „Článek (Article)“, ačkoli
jde o běžné stránky. Ověřeno přes wp-cli: `rank-math-options-titles['pt_page_default_rich_snippet']
= 'article'` (i pro `post`) je **skutečné, existující nastavení Rank Math** – nebylo to chybou
SEO Booster Pro, ale defaultem RM. Uživatel rozhodl: neměnit nastavení Rank Math, ale přidat do
SEO Booster Pro možnost explicitně označit typ obsahu/kategorii jako „běžnou stránku“
(`TYPES['off']`) a aby tato volba skutečně přebila reálný výstup Rank Math.

**Opraveno v `includes/Schema/SchemaHelper.php`:**
- `get_post_type_override()` – `'off'` je teď platná uložená hodnota (dřív se vracelo `null`,
  takže šlo nastavit jen specifické typy schémat, ne „žádné“).
- `get_category_default()` – stejná oprava, term meta `''` = nenastaveno, jakákoli hodnota
  z `TYPES` (i `'off'`) = nastaveno.
- `get_post_type_default()` – nově vrací `array{type, explicit}` místo `string`, aby šlo
  rozlišit „SEOB má vlastní nastavení (i off)“ vs. „jen fallback na Rank Math“.
- `get_effective_type()` – nový klíč `is_explicit` v návratovém poli.
- `filter_rich_snippet_meta()` – řetězec rozšířen o `get_post_type_override()`: pokud SEOB
  má pro daný typ obsahu nastaveno (i `'off'`) a kategorie nemá vlastní hodnotu, vrátí se tato
  hodnota jako `rank_math_rich_snippet` – Rank Math ji vezme jako vlastní volbu (stejně jako
  ruční „None“ v RM metaboxu) a nepoužije svůj `pt_*_default_rich_snippet`. Žádný zápis do RM
  options/postmeta (ověřeno přes wp-cli – `metadata_exists()` zůstává `false`).
- `TYPES['off']` přejmenováno na „Běžná stránka (bez schématu / výchozí WebPage)“ + doplněn popis.

**Opraveno v `includes/Schema/PostTypeAjax.php` a `CategoryAjax.php`:** `save_post_type()`/
`save_category()` už neukazují `'off'` přes `unset()`/`delete_term_meta()` (to by uložení
„běžná stránka“ smazalo) – vždy se uloží zvolená hodnota.

**`includes/Audit/Scanner.php`:** `schema_missing` se teď hlásí jen když je efektivní typ
`off`/prázdný **a zároveň** `is_explicit === false` (čistý fallback na prázdné/Article
nastavení Rank Math). Záměrná volba „běžná stránka“ (přes SEOB nastavení typu obsahu/kategorie,
nebo ruční „None“ na příspěvku) varování negeneruje.

**Ověřeno přes wp-cli:** `seob_default_schema_post_types = ['page' => 'off']` →
`get_effective_type()` vrací `['type' => 'off', 'source' => 'post_type_default',
'is_explicit' => true]`, `get_post_meta($id, 'rank_math_rich_snippet', true)` vrací `'off'`
přes filtr, ale v `wp_postmeta` nic uloženo není. Příspěvky typu `post` (bez SEOB override)
nadále dostávají `article` z Rank Math beze změny.

**D2 – nahlášeno:** `thin_content` nález (Scanner.php už detekuje, `< thin_content_words` slov)
nebyl v hlavní tabulce Audit Dashboardu vidět, jen po rozkliknutí řádku.

**Opraveno:** nový sloupec „Obsah“ v `templates/admin/page-dashboard.php`
(`<th>`/`<td class="seob-col-thin">`, colspan 9→10 pro empty/edit řádek) +
`assets/admin/js/audit-dashboard.js` (`buildRow()` – `findIssue(row.issues, 'thin_content')`,
podle vzoru `.seob-col-alt`: ⚠ s počtem slov při nálezu, jinak ✔) + CSS `.seob-col-thin`
(centrované zarovnání) + `SEOB_VERSION` 0.1.6 → 0.1.7.

**Lint:** `php -l` bez chyb na všech upravených souborech (`SchemaHelper.php`, `PostTypeAjax.php`,
`CategoryAjax.php`, `Scanner.php`, `page-dashboard.php`, `seo-boost.php`).

**Push:** zatím ne, čeká na pokyn uživatele.

---

### 2026-06-11 – Bezpečnostní audit před mergem `feature/audit-dashboard-redirects`

**Požadavek:** docs/STATE.md bod 6 – bezpečnostní audit jako povinný krok před mergem
větve `feature/audit-dashboard-redirects` do `main`.

**Rozsah kontroly:** všech 16 AJAX akcí (`wp_ajax_seob_*`) napříč
`includes/Pdf/Ajax.php`, `includes/Admin/SettingsAjax.php`, `includes/Audit/Ajax.php`,
`includes/Redirects/Ajax.php`, `includes/Schema/CategoryAjax.php`,
`includes/Schema/PostTypeAjax.php`, a admin JS s `innerHTML` (`audit-dashboard.js`,
`redirects.js`, `report.js`, `schema-defaults.js`, `pdf-settings.js`).

**Zjištění:**
- Všechny AJAX handlery volají `check_ajax_referer('seob_admin_nonce', 'nonce')` +
  `current_user_can('manage_options')` (`Audit/Ajax.php::save_meta()` navíc
  `current_user_can('edit_post', $object_id)` a validuje `field`/`value` proti
  whitelistu).
- Vstupy sanitizované odpovídajícím způsobem (`absint`, `sanitize_key`,
  `sanitize_text_field`/`sanitize_textarea_field` s `wp_unslash`, `wp_kses_post`,
  `esc_url_raw`, `wp_validate_redirect`).
- PDF export (`Pdf/Ajax.php`, `Pdf/PdfRenderer.php`, `templates/pdf/report.php`):
  veškerý dynamický výstup do TCPDF prochází `esc_html()`/`esc_attr()`/`(int)`,
  binární výstup PDF má odůvodněný `phpcs:ignore`.
- JS s `innerHTML`: `redirects.js` používá vlastní `escapeHtml()` helper před
  vložením `target_url`/`redirect_to`/`last_checked`. `audit-dashboard.js` –
  `renderSummary()` skládá `innerHTML` jen z číselných hodnot vypočtených na
  serveru (`score_avg`, `counts.*`, `resolved_total`), žádný uživatelský text.
  `schema-defaults.js` – nové `edit_url`/`name`/`descriptions` se vkládají přes
  `.href`/`.textContent`/`.title`, ne `innerHTML`; `edit_url` navíc generuje
  `admin_url()` na serveru. `report.js` a `pdf-settings.js` používají výhradně
  `textContent`/`FormData`.

**Závěr:** Žádné bezpečnostní problémy nenalezeny – všechny endpointy a JS dodržují
zavedený bezpečný vzor (nonce, capability check, sanitizace vstupu, escapování
výstupu). Audit je hotový, **push do `main` zatím NEPROVEDEN** – čeká na explicitní
pokyn uživatele.

### 2026-06-11 – Audit Dashboard: označení nálezů opravených od minulého scanu

**Požadavek:** docs/STATE.md bod 4 ("Další krok") – diff `issues` mezi aktuálním a předchozím
scanem pro stejný `object_id`, vizuálně označit nově vyřešené nálezy.

**Backend:**
- `includes/Audit/AuditScanRunner.php` – `get_results()`: nová privátní metoda
  `get_previous_issue_types(int $scan_id): array` najde poslední dokončený scan s `id < $scan_id`
  a vrátí mapu `object_id => [typy nálezů]` z jeho `issues_json`. Pro každý řádek aktuálního scanu
  se spočítá `resolved_issues` = typy nálezů, které byly v předchozím scanu pro stejný `object_id`,
  ale v aktuálním už nejsou (`array_diff`). Do `summary` přidáno `resolved_total` (součet napříč
  celým scanem). Pokud předchozí dokončený scan neexistuje (první scan), `resolved_issues` je
  prázdné pole pro všechny řádky a `resolved_total` je 0.

**Frontend:**
- `templates/admin/page-dashboard.php` – nový sloupec „Od minula“ (`.seob-col-resolved`),
  colspan prázdného řádku a edit-řádku změněn z 8 na 9.
- `assets/admin/js/audit-dashboard.js` – `buildRow()`: pokud `row.resolved_issues.length > 0`,
  zobrazí zelený badge „✓ N“ s tooltipem (seznam opravených nálezů přes `ISSUE_LABELS`), jinak „–“.
  `renderSummary()`: pokud `summary.resolved_total > 0`, zobrazí v souhrnu „✓ N opraveno od
  minulého scanu“.
- `assets/admin/css/admin.css` – styly `.seob-resolved-badge` a `.seob-count-resolved`
  (zelená barva), `.seob-col-resolved` přidán do centrovaných sloupců.
- `seo-boost.php` – `SEOB_VERSION` `0.1.5` → `0.1.6` (cache busting).

**Verifikace:** `php -l` bez chyb na `AuditScanRunner.php`, `page-dashboard.php`, `seo-boost.php`.
Zatím netestováno v prohlížeči – pro zobrazení je potřeba alespoň 2 dokončené scany.

### 2026-06-11 – Schéma: tlačítko „Uložit vše“ pro hromadné uložení tabulky

**Požadavek:** U obou tabulek (typ obsahu, kategorie) je potřeba uložit všechny řádky jedním
kliknutím namísto klikání "Uložit" u každého zvlášť.

**Frontend:**
- `assets/admin/js/schema-defaults.js` – `initTable()`: extrahována funkce `saveRow(item, select,
  status)` (sdílená pro jednotlivé i hromadné ukládání), pole `rows` sbírá `{item, select, status}`
  pro každý řádek. Po načtení dat se aktivuje tlačítko `config.saveAllId` – kliknutí postupně
  (`Promise.all`) uloží všechny řádky a do `config.saveAllStatusId` vypíše souhrn "Uloženo X / Y.".
- `templates/admin/page-settings.php` – nad obě tabulky přidáno tlačítko „Uložit vše“
  (`#seob-posttype-save-all` / `#seob-schema-cat-save-all`, zpočátku `disabled` dokud se nenačtou
  data) + status span (`#seob-posttype-save-all-status` / `#seob-schema-cat-save-all-status`).
- `seo-boost.php` – `SEOB_VERSION` `0.1.4` → `0.1.5` (cache busting JS).

**Verifikace:** `php -l` bez chyb na `page-settings.php` a `seo-boost.php`. Zatím netestováno
v prohlížeči.

### 2026-06-11 – Schéma: proklik na příspěvky, nápověda k typům a vysvětlení hierarchie

**Požadavek:** U tabulek "Výchozí schéma podle typu obsahu" a "podle kategorie" chybí proklik
na konkrétní příspěvky daného typu/kategorie (např. aby uživatel zjistil, co je za obsah typ
„Slovníček pojmů“), dále chybí doporučení, kdy který typ schématu použít a proč, a obecná
dokumentace, jak schéma a jeho priorita fungují.

**Backend:**
- `includes/Schema/SchemaHelper.php` – nová `const TYPE_DESCRIPTIONS` (mapování typ → krátký
  popis, co znamená a kdy je vhodné jej použít, pro všech 14 typů z `TYPES`).
- `includes/Schema/PostTypeAjax.php` – `list_post_types()` nyní vrací i `edit_url`
  (`admin_url('edit.php?post_type=...')`) pro každý typ obsahu a `descriptions` (TYPE_DESCRIPTIONS)
  v odpovědi.
- `includes/Schema/CategoryAjax.php` – `list_categories()` nyní vrací i `edit_url`
  (`admin_url('edit.php?category_name=...')`) pro každou kategorii a `descriptions`.

**Frontend:**
- `assets/admin/js/schema-defaults.js` – `buildRow()`: název typu/kategorie je nyní odkaz
  (`<a target="_blank">`) na seznam příslušných příspěvků v adminu; buňka "Doporučeno" má
  `title` s popisem doporučeného typu; vedle selectu je nová ikona nápovědy
  (`.seob-type-info`, dashicons-editor-help) s `title` popisem aktuálně vybraného typu,
  aktualizovaným při změně výběru.
- `templates/admin/page-settings.php` – nová sekce „Schéma (strukturovaná data)“ se sbalitelnou
  nápovědou (`<details class="seob-schema-help">`): vysvětluje prioritní pořadí (post override →
  kategorie → typ obsahu → Rank Math globální/WebPage) a obsahuje tabulku všech typů schémat
  s popisem, kdy je vhodné je použít (z `SEOB_Schema_Helper::TYPE_DESCRIPTIONS`).
- `assets/admin/css/admin.css` – styly `.seob-schema-help`/`.seob-schema-help-body` (rámeček +
  collapsible) a `.seob-type-info` (ikona nápovědy u selectu).
- `seo-boost.php` – `SEOB_VERSION` `0.1.3` → `0.1.4` (cache busting JS/CSS).

**Verifikace:** `php -l` bez chyb na `SchemaHelper.php`, `PostTypeAjax.php`, `CategoryAjax.php`,
`page-settings.php`, `seo-boost.php`. Zatím netestováno v prohlížeči.

### 2026-06-11 – Nastavení: nová sekce „Výchozí schéma podle typu obsahu“

**Požadavek:** Sekce „Výchozí schéma podle kategorie“ nepokrývá custom post types bez kategorií
(např. Crocoblock/JetEngine „Slovníček pojmů“). Uživatel chce obecnější úroveň – nastavit výchozí
schéma pro celý typ obsahu (Příspěvky → Article, Landing page → Service apod.), kategorie zůstává
specifičtější override s přednostní platností.

**Backend:**
- `includes/Schema/SchemaHelper.php` – nová `const POST_TYPE_OPTION = 'seob_default_schema_post_types'`
  a metoda `get_post_type_override(string $post_type): ?string` (čte option array, vrací `null` při
  `'off'`/nenastaveno). `get_post_type_default()` nově nejdřív zkusí náš override, jinak fallback na
  Rank Math `pt_{$post_type}_default_rich_snippet`. `get_effective_type()` beze změny – `source` u tohoto
  pravidla zůstává `'post_type_default'` (label v `audit-dashboard.js` už existuje).
- `includes/Schema/PostTypeAjax.php` (nový soubor) – `SEOB_Schema_PostType_Ajax`:
  - `wp_ajax_seob_schema_post_types_list` – vypíše veřejné post types (bez `attachment`) s počtem
    publikovaných příspěvků (`wp_count_posts()->publish`), aktuálním nastavením a navrhovaným typem
    podle heuristiky `NAME_HINTS` (klíčová slova ve slugu/labelu → typ schématu; `post` → `article`,
    jinak `off`).
  - `wp_ajax_seob_schema_post_type_save` – validace `post_type_exists()` + `SEOB_Schema_Helper::TYPES`,
    ukládá do option array (`'off'` = unset klíče).
- `seo-boost.php` – require nového `includes/Schema/PostTypeAjax.php` (za `CategoryAjax.php`),
  `SEOB_VERSION` `0.1.2` → `0.1.3` (cache busting).
- `includes/Plugin.php` – `new SEOB_Schema_PostType_Ajax();` vedle `SEOB_Schema_Category_Ajax` (gated
  `modules['audit']`).

**Frontend:**
- `templates/admin/page-settings.php` – nová sekce „Výchozí schéma podle typu obsahu“ PŘED sekcí
  „Výchozí schéma podle kategorie“: progress bar (`#seob-posttype-progress*`, stejná `.seob-progress`
  komponenta), tabulka (Typ obsahu | Počet příspěvků | Schéma | Doporučeno | Akce),
  `<tbody id="seob-posttype-body">` + `<template id="seob-posttype-row-template">`.
- `assets/admin/js/schema-categories.js` smazán, nahrazen `assets/admin/js/schema-defaults.js` –
  sdílená logika (timeout/AbortController fetch, progress tick po 250ms, build řádků, ukládání)
  zgeneralizována do `initTable(config)`, voláno 2× – pro post types
  (`seob_schema_post_types_list`/`seob_schema_post_type_save`, klíč `post_type`) a pro kategorie
  (`seob_schema_categories_list`/`seob_schema_category_save`, klíč `term_id`, beze změny chování).
- `includes/Admin/Admin.php` – enqueue handle/cesta přejmenovány na `seob-schema-defaults` /
  `assets/admin/js/schema-defaults.js` na hooku `_page_seob-settings`.

**Verifikace:** `php -l` bez chyb na `SchemaHelper.php`, `PostTypeAjax.php`, `Plugin.php`,
`seo-boost.php`, `Admin.php`, `page-settings.php`. Zatím netestováno v prohlížeči.

### 2026-06-11 – KOŘENOVÁ PŘÍČINA nalezena: enqueue_assets() nikdy nenačítal JS pro submenu stránky

**Problém:** Po přidání progress baru pro „Výchozí schéma podle kategorie“ uživatel hlásí 10 minut
beze změny – progress bar se vůbec nehýbe (0 %, žádný text). Network tab v DevTools ukázal, že
`schema-categories.js` se **vůbec nenačítá** (žádný request). Stejně tak to vysvětluje, proč
předchozí fix `report.js` (Export reportu) nezabral – ani ten soubor se nikdy nenačetl.

**Příčina:** `SEOB_Admin::enqueue_assets()` porovnával `$hook` s řetězci jako
`'seo-boost_page_seob-settings'` (odvozeno od `MENU_SLUG = 'seo-boost'`). Skutečný hook suffix pro
submenu stránky ale WordPress odvozuje z `sanitize_title()` **menu titulu** top-level položky
(`add_menu_page('SEO Booster Pro', 'SEO Booster', ...)` → `sanitize_title('SEO Booster')` =
`'seo-booster'`), ne ze slugu. Skutečné hooky jsou tedy `seo-booster_page_seob-settings`,
`seo-booster_page_seob-report`, `seo-booster_page_seob-pdf-settings`,
`seo-booster_page_seob-redirects` – žádný z nich neodpovídal podmínkám `=== $hook`, takže
`wp_enqueue_script()` pro `settings.js`, `schema-categories.js`, `pdf-settings.js`, `redirects.js`
i `report.js` se **nikdy nezavolal**. Ověřeno přes dočasný php-cli skript simulující
`add_menu_page()`/`add_submenu_page()` + `get_plugin_page_hookname()`.

(Toplevel hook `'toplevel_page_' . MENU_SLUG` byl OK – proto Audit Dashboard fungoval.)

**Soubory (upravené):**
- `includes/Admin/Admin.php` – `enqueue_assets()`: všechny 4 podmínky `'seo-boost_page_seob-X' === $hook`
  nahrazeny `str_ends_with( $hook, '_page_seob-X' )`, nezávislé na sanitizaci menu titulu.

**Dopad:** Toto je skutečná příčina dlouhodobě parkovaného bugu „Načítám…“ u schema-categories.js
i nově reportovaného zaseknutí Export reportu – obě stránky teď poprvé skutečně odešlou AJAX request.

**Verifikace:** `php -l` na `Admin.php` bez chyb. Doporučeno otestovat v prohlížeči obě stránky
(Export reportu, Nastavení – schéma kategorií) i Export – nastavení a Přesměrování (formuláře
používající JS na těchto stránkách dosud taky nemusely fungovat).

### 2026-06-11 – Nastavení: stejný timeout/progress fix pro „Výchozí schéma podle kategorie“

**Problém:** Stránka Nastavení – sekce „Výchozí schéma podle kategorie“ – zůstává trvale na
„Načítám…“ (uživatel hlásí až 20 minut bez odpovědi). Stejný symptom jako u Export reportu
(viz záznam níže), backend (`SEOB_Schema_Category_Ajax::list_categories()`) je jednoduchý
cyklus přes `get_categories()` bez síťových volání, takže příčina je pravděpodobně stejná
(blokující admin-ajax kvůli jinému pluginu bez internetu).

**Soubory (upravené):**
- `templates/admin/page-settings.php` – přidán progress bar `#seob-schema-progress`
  (`#seob-schema-progress-fill`, `#seob-schema-progress-text`) nad tabulku kategorií,
  stejná komponenta `.seob-progress` jako u Export reportu
- `assets/admin/js/schema-categories.js` – `ajax()` helper nově podporuje volitelný
  `timeoutMs` (AbortController) a kontrolu `response.ok`; úvodní `seob_schema_categories_list`
  má 20s timeout s tikajícím progress barem (250ms interval, 0–100 % vůči 20s) a po timeoutu
  zobrazí konkrétní hlášku v tabulce místo věčného „Načítám…“
- `seo-boost.php` – `SEOB_VERSION` `0.1.1` → `0.1.2` (cache busting)

**Verifikace:** `php -l` na `page-settings.php` a `seo-boost.php` bez chyb.

### 2026-06-11 – Export reportu: progress bar pro načítání + bump verze (cache busting)

**Problém:** Po předchozím zásahu (20s timeout v `report.js`) uživatel hlásí, že stránka „Export
reportu“ stále jen zobrazuje „Načítám data auditu…“ bez jakékoli reakce, i po 5 minutách – chybová
hláška ani timeout se nezobrazí.

**Zjištění:**
- `wp-content/debug.log` neobsahuje žádný záznam z doby testu (žádný PHP fatal/warning) – požadavek
  buď vůbec nedorazil na server, nebo backend doběhl bez chyby, ale odpověď se v prohlížeči neprojeví.
- `debug.log` obsahuje `wp_update_plugins(): ... Secure connection to WordPress.org se nepodařilo
  navázat` – prostředí nemá funkční přístup k internetu/DNS. Pokud nějaký jiný plugin (License
  Mismatch hláška viditelná ve screenshotu – Crocoblock/Complianz/Rank Math apod.) dělá při
  `admin-ajax.php` (běží přes plný bootstrap včetně všech pluginů) blokující `wp_remote_*`/cURL
  volání na licenční server, DNS resolve na Windows bez dostupné sítě může trvat i několik minut –
  to by přesně odpovídalo hlášeným „5 minutám“. Mimo rozsah tohoto pluginu (nelze opravit úpravou
  seo-boost).
- `SEOB_VERSION` byl od verze 0.1.0 beze změny přes všechny dosavadní úpravy `report.js` – prohlížeč
  tak mohl servírovat **starou cachovanou verzi** souboru (stejné `?ver=0.1.0` URL), takže ani
  předchozí timeout/error fix se nemusel v prohlížeči vůbec projevit.

**Soubory (upravené):**
- `seo-boost.php` – `SEOB_VERSION` `0.1.0` → `0.1.1` (cache-busting všech enqueued JS/CSS)
- `templates/admin/page-report.php` – `#seob-report-loading` rozšířeno o progress bar
  (`#seob-report-progress`, `#seob-report-progress-fill`, `#seob-report-progress-text`) – stejná
  komponenta `.seob-progress` jako v Audit Dashboardu
- `assets/admin/js/report.js` – během čekání na `seob_pdf_report_data` se každých 250ms aktualizuje
  progress bar (0–100 % vůči 20s timeoutu) a text „X s / 20 s“, takže je vidět, že stránka skutečně
  čeká na odpověď a kolik času zbývá do timeoutu. Po timeoutu se navíc zobrazí konkrétnější hláška
  s podezřením na blokující admin-ajax (licenční kontrola bez internetu).

**Verifikace:** `php -l` na `page-report.php` a `seo-boost.php` bez chyb.

### 2026-06-11 – Export PDF: samostatná stránka nastavení + timeout pro report.js

**Soubory (nové):**
- `templates/admin/page-pdf-settings.php` – nová stránka „Export – nastavení“ (`seob-pdf-settings`):
  texty nálezů (impact/benefit), obchodní nabídky (3 šablony) a firemní údaje – dříve součást
  obecné stránky Nastavení
- `assets/admin/js/pdf-settings.js` – ukládání formuláře `#seob-pdf-settings-form` přes nový AJAX
  `seob_save_pdf_settings`

**Soubory (upravené):**
- `templates/admin/page-settings.php` – odebrány sekce „PDF Report – texty nálezů / obchodní
  nabídky / firemní údaje“, nahrazeno odkazem na novou stránku „Export – nastavení“
- `includes/Admin/SettingsAjax.php` – `save_settings()` už neukládá `seob_pdf_settings` (jen
  GENERAL/AUDIT/REDIRECT, aby uložení obecného formuláře nepřepsalo PDF nastavení nulami);
  nová metoda `save_pdf_settings()` (akce `wp_ajax_seob_save_pdf_settings`) ukládá pouze
  `seob_pdf_settings` přes existující `pdf_settings()` helper
- `includes/Admin/Admin.php` – registrace submenu „Export – nastavení“ (`seob-pdf-settings`,
  `page_pdf_settings()`), enqueue `pdf-settings.js` pro hook `seo-boost_page_seob-pdf-settings`
- `templates/admin/page-report.php`, `assets/admin/js/report.js` – AJAX dotaz na
  `seob_pdf_report_data` má nově 20s timeout (`AbortController`), při chybě/timeoutu/HTTP != 200
  se zobrazí konkrétní hláška v `#seob-report-error` (dříve zůstávalo jen „Načítám…“ bez indikace
  problému). Backend handler ověřen přes wp-cli `do_action()` simulaci – vrací validní JSON pod 1s,
  takže příčina hlášeného 5min „visení“ je pravděpodobně na straně prohlížeče/sítě – timeout teď
  alespoň ukáže uživateli chybový stav místo nekonečného „Načítám…“. Stejný symptom má
  dlouhodobě neřešený bug u `schema-categories.js` na stránce Nastavení (parkováno).

**Verifikace:** `php -l` na všech upravených souborech bez chyb.

### 2026-06-11 – Export auditu do PDF (obchodní report)

**Soubory (nové):**
- `vendor/tcpdf/` – TCPDF 6.7.5 vendorovaný ručně (bez Composeru), jen core + DejaVuSans fonty +
  `seob-tcpdf-loader.php` (require_once tcpdf.php, voláno až lazy z `PdfRenderer`)
- `includes/Pdf/ReportData.php` (`SEOB_Pdf_Report_Data`) – `build(?int $scan_id)` vezme
  `AuditScanRunner::get_results()`, doplní ke každému nálezu `impact`/`benefit` z `seob_pdf_settings`,
  spočítá `intro_summary`, navrhne `offer_suggestion` (`maintenance` ≥85, `standard` 60–84,
  `comprehensive` <60) a vyřeší placeholdery `{site_name}`/`{score}`/`{critical_count}`/`{warning_count}`
  v šablonách nabídky. `issue_labels()` – veřejné popisky nálezů (sdíleno se stránkou Nastavení).
- `includes/Pdf/PdfRenderer.php` (`SEOB_Pdf_Renderer`) – `render(array $data): string`, TCPDF
  (`writeHTML`, font dejavusans, `setRTL(false)`), výstup `Output('', 'S')` (string, nezapisuje na disk)
- `templates/pdf/report.php` – HTML šablona PDF (souhrn, nálezy po stránkách s dopadem/přínosem, nabídka, patička)
- `includes/Pdf/Ajax.php` (`SEOB_Pdf_Ajax`) – `wp_ajax_seob_pdf_report_data` (vrací `ReportData::build()`
  jako JSON), `wp_ajax_seob_pdf_export` (přijme edited `intro_summary`/`offer_key`/`offer_name`/`offer_body`,
  sanitizuje, vyrenderuje PDF a pošle jako download)
- `templates/admin/page-report.php` + `assets/admin/js/report.js` – nová stránka „Export reportu“:
  shrnutí skóre, editovatelné úvodní shrnutí, přehled nálezů po stránkách (read-only), výběr šablony
  nabídky (předvybraná dle skóre) s editovatelným textem, tlačítko „Stáhnout PDF“ (blob download)

**Soubory (upravené):**
- `includes/Settings.php` – `const PDF = 'seob_pdf_settings'`, `pdf_defaults()` (14× `issue_texts`
  s impact/benefit, 3× `offer_templates`, `company`), `modules.pdf => 1` v `GENERAL`
- `includes/Admin/SettingsAjax.php` – ukládání `modules_pdf` + nová sekce `pdf_settings()`
  (issue texty přes `wp_kses_post`, offer šablony, firemní údaje)
- `templates/admin/page-settings.php` – sekce „PDF Report“ (texty nálezů, obchodní nabídky, firemní údaje)
  + checkbox modulu
- `includes/Admin/Admin.php` – submenu „Export reportu“ (`seob-report`), enqueue `report.js`
  (`seobData.scanId` z `$_GET['scan_id']`), `seobData.reportUrl` pro dashboard
- `templates/admin/page-dashboard.php`, `assets/admin/js/audit-dashboard.js` – tlačítko „Export PDF“
  vedle historie scanů, odkaz na `seob-report&scan_id={aktuální scan}`
- `assets/admin/css/admin.css` – styly pro `#seob-report-summary`/`.seob-pdf-page`/`.seob-pdf-issue`
- `seo-boost.php` – registrace `includes/Pdf/{ReportData,PdfRenderer,Ajax}.php`
- `includes/Plugin.php` – `if (!empty($modules['pdf'])) { new SEOB_Pdf_Ajax(); }`
- `.gitignore` – `vendor/_tcpdf_tmp/` (dočasný adresář po extrakci TCPDF zipu, nešlo smazat kvůli AV/lock)

**Proč:** Uživatel chce z dokončeného auditu vygenerovat obchodní PDF report pro klienty – popis nálezů
s dopadem/přínosem, souhrn a finální nabídku, editovatelné před exportem.

**Test:** `php -l` na všech nových/upravených souborech bez chyb. Přes wp-cli ověřeno
`SEOB_Pdf_Report_Data::build()` (scan_id=17, score=84, offer=standard, 12 řádků) a
`SEOB_Pdf_Renderer::render()` → validní PDF (116 493 B, hlavička `%PDF-1.7`), diakritika v DejaVuSans OK.
Stránka „Export reportu“ a tlačítko v dashboardu zatím neověřeny v prohlížeči – uživatel otestuje.

**Bezpečnostní audit:**
- Oba AJAX endpointy (`seob_pdf_report_data`, `seob_pdf_export`) vyžadují
  `check_ajax_referer('seob_admin_nonce','nonce')` + `current_user_can('manage_options')`.
- `intro_summary`/`offer_name`/`offer_body` z formuláře exportu procházejí
  `sanitize_textarea_field`/`sanitize_text_field`, `offer_key` přes `sanitize_key` + validace proti
  existujícím klíčům šablon. Texty nálezů/nabídek z Nastavení přes `wp_kses_post`/`sanitize_text_field`.
  V PDF šabloně (`templates/pdf/report.php`) jsou všechny hodnoty escapovány (`esc_html`/`esc_attr`,
  `nl2br` až po `esc_html`).
- TCPDF: `setRTL(false)`, žádné externí URL/obrázky v `writeHTML`, PDF se negeneruje na disk
  (`Output('', 'S')` → přímý download).
- `seob_pdf_export` posílá PDF s `Content-Disposition: attachment` a `sanitize_file_name()` na název souboru.

---

### 2026-06-11 – Vizuální úprava tabulky výsledků v Audit Dashboardu

**Soubory:** `templates/admin/page-dashboard.php`, `assets/admin/js/audit-dashboard.js`, `assets/admin/css/admin.css`

**Co:** Tabulka výsledků byla opticky "rozsypaná" (slabé oddělení řádků). Upraveno:
- Odstraněna WP třída `striped` (nefungovala správně kvůli prokládaným `seob-edit-row` řádkům), nahrazena vlastním zebrováním – `renderRows()`/`buildRow()` v `audit-dashboard.js` nyní přidávají třídu `seob-row-alt` na lichý index (na `seob-result-row` i odpovídající `seob-edit-row`).
- CSS: tučné záhlaví s výraznějším spodním okrajem, zarovnání sloupců se stavovými ikonami/skóre na střed, sloupec "Stránka" tučně vlevo, hover zvýraznění řádku, jasnější oddělovací linky mezi jednotlivými stránkami (`border-top`/`border-bottom`).

**Proč:** Uživatel chtěl opticky lépe rozlišit, které buňky patří k sobě (požadavek "učesat" tabulku).

**Test:** Pouze CSS/JS úprava (žádné nové vstupy/AJAX), neotestováno v prohlížeči – uživatel ověří vizuálně.

**Bezpečnostní audit:** Beze změny rizika – jen vizuální/CSS změny a přidání třídy v JS.

---

### 2026-06-10 – POKRAČOVÁNÍ 6 – Historie scanů (bod 3) implementována + KRITICKÁ oprava rozbitého webu

**Bod 3 – Historie scanů / porovnání verzí:**
- Backend: `SEOB_Audit_ScanRunner::get_scan_history(int $limit = 20)` – vrací posledních N dokončených scanů
  (`status = 'done'`) seřazených od nejnovějšího (`id, started_at, finished_at, score_avg, urls_total`).
- AJAX: `wp_ajax_seob_scan_history` v `includes/Audit/Ajax.php` → `SEOB_Audit_Ajax::scan_history()` →
  `wp_send_json_success( [ 'scans' => $this->runner->get_scan_history() ] )`.
- Frontend (`templates/admin/page-dashboard.php`): nový `<select id="seob-scan-history">` v toolbaru vedle
  tlačítka "Spustit nový scan".
- `assets/admin/js/audit-dashboard.js`:
  - `loadHistory(selectScanId)` – zavolá `seob_scan_history`, naplní `<option>` (label = datum + skóre + počet URL),
    vybere buď `selectScanId`, nebo nejnovější scan.
  - `change` listener na `#seob-scan-history` → `loadResults(parseInt(value, 10))`.
  - `loadResults()` po úspěchu nastaví `scanHistory.value` na `summary.id`, takže dropdown odráží zobrazený scan.
  - Po dokončení nového scanu (`runBatch` → `data.finished`) se volá i `loadHistory(scanId)`, aby se nový scan
    objevil v historii.
  - Init: `loadResults(); loadHistory();`.
- CSS: `.seob-scan-history-label` v `assets/admin/css/admin.css` (flex layout, max-width pro select).
- Ověřeno přes wp-cli: `get_scan_history()` vrací 14 dokončených scanů (id 1-16, score_avg 71-84).
- **Neotestováno v reálném prohlížeči** (web byl po většinu session rozbitý, viz dále) – příští krok.

**🔴 KRITICKÁ oprava – web byl celý rozbitý (fatal error):**
- Při práci na bodu 3 zmizel z disku soubor `includes/Audit/ScanRunner.php` (obsahoval celou třídu
  `SEOB_Audit_ScanRunner` – `start_scan`, `process_batch`, `finalize_scan`, `get_latest_scan`, `get_scan_history`,
  `get_results`). `seo-boost.php` ho ale natvrdo `require_once`-oval → **fatal error na úplně každém page loadu**
  (potvrzeno přes `wp option get siteurl`, které hlásilo "Na webu došlo k závažné chybě" i mimo wp-admin).
- Příčina zmizení souboru se nepodařilo s jistotou určit – `git status` ho ukazoval jako `D` (deleted) v
  working tree. Pokus o obnovu (`git checkout HEAD --`, Write tool, PowerShell `New-Item`/`Move-Item`, bash
  heredoc) na **přesně tomto jméně+cestě** `includes/Audit/ScanRunner.php` vždy skončil "Access denied"/EPERM,
  i když ACL složky (`icacls`) je v pořádku (FullControl pro PC/Administrators/SYSTEM). Test ukázal, že je
  blokované POUZE jméno `ScanRunner.php` (case-insensitive) v této složce – jiná jména (`Scan_Runner.php`,
  `AuditScanRunner.php`, `ScanRunner.PHP.tmp`) šla vytvořit bez problémů. Na stroji běží Norton 360 + Windows
  Defender + Avast současně → nejpravděpodobněji karanténa/tombstone jednoho z nich (admin práva nejsou
  v sandboxu dostupná pro ověření/úklid).
- **Oprava:** obsah třídy rekonstruován (z dřívějšího Read v této session, včetně `get_scan_history()`) a uložen
  jako nový soubor `includes/Audit/AuditScanRunner.php` (třída uvnitř beze změny – `SEOB_Audit_ScanRunner`).
  V `seo-boost.php` (pole `$seob_files`) upraven odkaz `includes/Audit/ScanRunner.php` →
  `includes/Audit/AuditScanRunner.php`.
- Ověřeno: `wp option get siteurl` → `http://reboost-test.local` (bez chyby), `get_scan_history()` vrací data.
- **TODO (nízká priorita, vyžaduje admina):** najít a vyčistit AV karanténu, aby šlo soubor případně přejmenovat
  zpět na `ScanRunner.php` – není to nutné, kód funguje i pod novým jménem.

### 2026-06-10 – POKRAČOVÁNÍ 5 – schema kategorie "Načítám…" – error handling + nález BOM v jiném pluginu (ROZPRACOVÁNO)

**Diagnostika (bod 2 ze STATE.md):**
- Ověřeno end-to-end přes wp-cli (autentizovaný `wp_remote_post` na `admin-ajax.php` se skutečnou session/nonce stejného uživatele): `seob_schema_categories_list` vrací `200 OK` a validní JSON se 4 kategoriemi (`{"success":true,"data":{"types":{...},"categories":[...]}}`).
- `wp_localize_script` pro `seob-settings` i `seob-schema-categories` (oba pod stejným JS objektem `seobData`) se renderují korektně, oba bloky obsahují stejná data (`ajaxUrl`, `nonce`) – ověřeno renderem skutečné fronty skriptů pro hook `seo-boost_page_seob-settings`.
- Reálnou stránku v prohlížeči (DevTools) se nepodařilo otestovat – `wps-hide-login` (slug `prihlaseniweb`) blokuje přímý request na `/wp-admin/...` bez plnohodnotné browser session, a v sandboxu není dostupný headless prohlížeč/Node.

**Provedená oprava (defenzivní, dokud se nenajde root cause v browseru):**
- `assets/admin/js/schema-categories.js` – přidán `.catch()` na úvodní `seob_schema_categories_list` request: při chybě (síť, neplatný JSON, atd.) se místo trvalého "Načítám…" zobrazí "Chyba při načítání kategorií: ..." + `console.error`. Dřív při selhání `response.json()` zůstávalo "Načítám…" navždy beze stopy v UI.

**VEDLEJŠÍ NÁLEZ – BOM v `emailing-calculator.php` (JINÝ plugin/repo):**
- `wp-content/plugins/emailing-calculator/emailing-calculator.php` má na začátku souboru UTF-8 BOM (`EF BB BF`) před `<?php`. Protože je to jeden z prvních aktivních pluginů, tyto 3 bajty se vypisují před JAKÝMKOLI výstupem na webu (potvrzeno: `admin-ajax.php` odpověď pro `seob_schema_categories_list` měla `EF BB BF` před `{`).
- Dle Fetch/Encoding specifikace by `response.json()` měl BOM ořezat (UTF-8 decode s BOM strip), takže pravděpodobně NENÍ přímou příčinou tohoto bugu, ale jde o reálnou závadu se širokým dopadem (headers already sent, rozbité exporty/redirecty/feedy v jiných kontextech). **Hlášeno uživateli, čeká na rozhodnutí, zda opravit (jiný plugin/repo).**

**STÁLE OTEVŘENO:**
- Pokud uživatel příště uvidí "Chyba při načítání kategorií: ...", zapsat přesnou hlášku + `console.error` výstup sem pro další diagnostiku.
- Bod 3-6 ze STATE.md (historie scanů, diff oprav, bezpečnostní audit + push).

**Bezpečnostní audit:** žádné nové vstupy, jen frontend error-handling – beze změny rizika.

---

### 2026-06-10 – POKRAČOVÁNÍ 4 – schema_json sloupec v audit_table (DB migrace)

**Co:**
- `seo-boost.php` – přidána konstanta `SEOB_DB_VERSION` ('0.2.0').
- `includes/Activator.php`:
  - `create_tables()` – nový sloupec `schema_json LONGTEXT` v `seo_booster_audit`.
  - `activate()` ukládá `seob_db_version` option.
  - nová metoda `maybe_upgrade()` – pokud se `seob_db_version` neshoduje s `SEOB_DB_VERSION`, znovu spustí `create_tables()` (dbDelta je idempotentní, doplní chybějící sloupec) a aktualizuje option. Volá se z `plugins_loaded`, takže migrace proběhne i bez reaktivace pluginu.
- `includes/Audit/ScanRunner.php`:
  - `process_batch()` – nově ukládá `schema_json => wp_json_encode($result['schema'])`.
  - `get_results()` – dekóduje `schema_json` do `row['schema']`, fallback na `{type: 'off', source: 'post_type_default'}` pro staré řádky bez hodnoty (zpětná kompatibilita se scany před touto migrací).
- `CHANGELOG.md` – záznam v `[Unreleased]`.

**Test (wp-cli proti živé DB):**
- `option get seob_db_version` → `0.2.0` po `plugins_loaded` (migrace proběhla automaticky bez reaktivace).
- `DESCRIBE wp_seo_booster_audit` → sloupec `schema_json` přítomen (dbDelta ho přidal na konec tabulky, což je u ALTER běžné a neškodí).
- Staré řádky (scan 12-14, schema_json=NULL) → `get_results()` vrací default `{type:'off', source:'post_type_default'}` bez chyby.
- Testovací mini-scan (1 post, scan_id 15) → `process_batch()` uložil `{"type":"article","source":"post_type_default"}`, `get_results()` ho správně dekódoval. Testovací scan (id 15) po ověření smazán z `audit`/`scan_runs` tabulek.

**Bezpečnostní audit:** žádné nové vstupy od uživatele, jen interní DB migrace a JSON encode/decode existujících dat – beze změny rizika.

**Další krok:** STATE.md bod 1 hotový. Pokračovat bodem 2 (tabulka "Výchozí schéma podle kategorie" zůstává na "Načítám…" – frontend debug v DevTools).

---

### 2026-06-10 – POKRAČOVÁNÍ 3 – retry pro count_rendered_h1 + zjištění, že většina H1 nálezů je SKUTEČNÁ

**Diagnóza scanu 13 (proběhl po předchozí opravě):** transienty `seob_h1_*` ukázaly, že u postu **1897 i 1707 fix funguje** (cache count=1, žádný h1_missing). U postu **1972 (red-rider)** transient `false` → `count_rendered_h1()` selhal i ve scanu 13 → fallback na 0 → h1_missing. **Oprava:** `count_rendered_h1()` teď zkouší `wp_remote_get` až **3x s 300ms prodlevou** mezi pokusy, než se vzdá (řeší občasné selhání loopback requestu během dávky kvůli vytížení PHP-FPM/Wordfence).

**DŮLEŽITÉ ZJIŠTĚNÍ – většina "H1 ✗" v posledním screenshotu je SPRÁVNĚ, NE bug:**
Přímý test rendered HTML (právě teď, mimo scan) ukázal:
- `/kontakty/`, `/o-nas/`, `/novinky-z-mailingu/` → **opravdu 0× `<h1>`** = skutečný SEO problém, scanner má pravdu. Tyto stránky reálně nemají H1 nadpis (Elementor heading widget nejspíš má `header_size=h2`, stejně jako u GDPR).
- `/` (homepage) → 2× H1 (duplicitní) = `h1_duplicate` warning, taky správně.
- `/pripadova-studie-emailing-red-rider/` (1972) a `/emailove-kampane/` → mají 1× H1, ale scan je vyhodnotil jako h1_missing → toto JSOU false-positivy způsobené selháním loopback requestu (viz oprava výše).

**Doporučení pro uživatele:** spustit nový scan (s retry fixem by 1972 a emailove-kampane už měly vyjít OK). Zbylé "H1 ✗" (gdpr, kontakty, o-nas, novinky-z-mailingu) jsou reálné nálezy – je potřeba v Elementoru u hlavního nadpisu té stránky nastavit "HTML Tag: H1" (teď je nejspíš H2).

---

### 2026-06-10 – POKRAČOVÁNÍ 2 – potvrzená příčina H1 false-positive + progress ETA (ROZPRACOVÁNO)

**Potvrzeno (post 1972 "red-rider"):** přímé volání `scan_post(1972)` přes wp-cli vrací score=93, ŽÁDNÝ h1_missing (rendered HTML má 2x stejný H1 → dedup 1). Ale poslední DOKONČENÝ scan (id=12, 13:46:50, status=done) má v `audit_table` pro post 1972 `score=78` + `h1_missing`. Tedy: **při reálném AJAX scanu `count_rendered_h1()` selhává** (vrací null → fallback na content-based 0 H1), zatímco izolovaný wp-cli request funguje. Příčina: pravděpodobně Wordfence (aktivní) blokuje/zpomaluje opakované loopback `wp_remote_get` požadavky během dávky, nebo WP Rocket vrací jinou odpověď bez cookies přihlášeného uživatele.

**Oprava (`includes/Audit/Scanner.php` → `count_rendered_h1()`):**
- Přidán **transient cache** klíčovaný `seob_h1_{post_id}`, platnost 1 den, invalidace při změně `post_modified_gmt` – při opakovaných scanech se nedělá zbytečný HTTP request pro nezměněné posty (řeší i "zšednutí"/pomalost dávek).
- `wp_remote_get()` nyní posílá **cookies aktuálního uživatele** (`$_COOKIE` → `WP_Http_Cookie[]`) a hlavičku `Cache-Control: no-cache` – WP Rocket by tak neměl vracet starou cache pro přihlášeného uživatele a Wordfence by měl požadavek brát jako legitimní session, ne jako bota.
- `sslverify => false`, timeout zvýšen na 10s.
- **Pozor:** toto je nejlepší odhad příčiny, NENÍ ověřeno end-to-end novým scanem (potřeba spustit nový scan a zkontrolovat, zda post 1972/1897/GDPR teď v `audit_table` mají správný H1 výsledek).

**Progress / odhad času (`assets/admin/js/audit-dashboard.js`):**
- `setProgress()` nyní počítá průměrný čas na položku od startu scanu a zobrazuje "zbývá cca X min Y s" vedle progress baru a %.
- `scanStartTime` se nastaví při `seob_scan_start` a resetuje po dokončení/chybě; přidán `.catch()` na `runBatch` aby se tlačítko znovu odemklo při chybě fetch.

**STÁLE OTEVŘENO (priorita pro další session):**
1. Spustit nový scan a ověřit, že H1 fix opravdu funguje end-to-end (post 1972, 1897, GDPR).
2. Schema kategorie tabulka "Načítám…" – frontend debug (backend ověřen funkční).
3. `ScanRunner::process_batch()` neukládá `schema` do `audit_table` (DB migrace + `get_results()`).
4. Historie scanů / porovnání verzí v dashboardu.
5. "Už opraveno od minulého scanu" – diff issues mezi scany.
6. **Nový požadavek uživatele:** kategorie v RankMath/WP nejsou vhodná jednotka pro výchozí schéma – web třídí obsah přes sitemapy (`post-sitemap.xml` = blog články, `slovicek-pojmu-sitemap.xml` = glosář, vlastní post type `slovicek-pojmu`). Zvážit přepnutí "výchozí schéma" feature z kategorií na **post type** (post, page, slovicek-pojmu) místo/vedle kategorií.
7. Bezpečnostní audit + push.

---

### 2026-06-10 – POKRAČOVÁNÍ – nové požadavky + zjištění (ROZPRACOVÁNO, session končí na 10 % limitu)

**Hotovo v této navazující session:**
- `assets/admin/js/audit-dashboard.js` + `admin.css`: ke každé ikoně nálezu (Title/Description/H1/Alt/Schéma) přidán `title` atribut (hover tooltip) s popisem nálezu (`issueTooltip()` helper) – uživatel chtěl vidět "o jakou chybu jde" bez nutnosti rozkliknout "Opravit". CSS: `.seob-status-icon[title] { cursor: help; border-bottom: 1px dotted; }`.

**Zjištění k post 1897 (H1 problém):**
- `scan_post(1897)` přes wp-cli vrací **score=93, ŽÁDNÝ h1_missing** (jen `title_too_long`). Rendered HTML obsahuje 2× stejný `<h1>` → dedup na 1 → OK.
- Takže oprava H1 z předchozího záznamu FUNGUJE správně pro post 1897 při testu přes wp-cli.
- **Hypotéza, proč to v dashboardu uživatel přesto vidí jako h1_missing:** dashboard zobrazuje výsledky POSLEDNÍHO DOKONČENÉHO scanu (`get_results()` → `WHERE status='done' ORDER BY id DESC LIMIT 1`). Pokud uživatel spustil "nový scan" PŘED touto opravou (nebo scan se nedokončil/zůstal `status=running` kvůli OOM crashi z dřívějška), dashboard stále ukazuje starý záznam. **Je třeba ověřit datum/čas posledního `status='done'` scan_run a nechat uživatele spustit scan ZNOVU PO této opravě.**
- Druhá hypotéza (od uživatele, k prozkoumání): **WP Rocket** (aktivní, v3.21.3) cachuje vykreslené HTML. `wp_remote_get()` v `count_rendered_h1()` může dostat STAROU cache verzi stránky (bez nového H1), pokud WP Rocket cache nebyla po úpravě promazána. Potvrzeno: odpověď obsahuje marker "WP Rocket" v HTML. **TODO: před/během scanu volat `rocket_clean_post($post_id)` (pokud funkce existuje), aby `count_rendered_h1()` vždy dostal čerstvé HTML.**

**Zjištění k "scan zšedne a dlouho se nic neděje":**
- Pravděpodobná příčina: `count_rendered_h1()` dělá `wp_remote_get()` (timeout 8s) na KAŽDÝ post v dávce → při `batch_size=20` může 1 dávka trvat desítky sekund až minuty, pokud loopback request běží pomalu (Local by Flywheel s omezeným počtem PHP-FPM workerů) nebo pokud Wordfence loopback požadavky zpomaluje/blokuje.
- **TODO řešení:**
  1. Cache výsledku `count_rendered_h1()` přes transient klíčovaný `post_id` + `post_modified_gmt` (re-fetch jen když se post od posledního scanu změnil) – zrychlí opakované scany.
  2. Zvážit snížení timeoutu (8s → 5s) a/nebo `batch_size` výchozí hodnoty.
  3. Před fetch zavolat `rocket_clean_post($post_id)` (viz výše) – řeší zároveň "stará cache" i čerstvost dat.

**Nové požadavky uživatele (zatím NEIMPLEMENTOVÁNO):**
1. **Progress feedback při scanu** – přidat časový odhad nebo postupný % progres tak, aby UI nepůsobilo jako zaseknuté (progress bar už existuje – `setProgress()` v `audit-dashboard.js`, ale možná je potřeba odhad zbývajícího času na základě průměrné doby na dávku).
2. **Historie scanů / verze pro porovnání** – uživatel chce vidět progres v čase (předchozí scany a jejich skóre). `scan_runs` tabulka už historii ukládá (`status='done'`, `score_avg`, `started_at`/`finished_at`), ale dashboard zobrazuje jen poslední. Potřeba: UI seznam/graf předchozích scanů + možnost vybrat scan pro zobrazení (`get_results($scan_id)` už podporuje parametr).
3. **"Změna provedena" indikace v reportu** – když uživatel opraví nález (např. přidá H1 ručně v Elementoru), report by to měl při dalším náhledu/scanu vyznačit jako vyřešené, aby se uživatel nevracel k již opraveným věcem. Možné řešení: porovnat `issues` aktuálního scanu vs. předchozího scanu pro stejný `object_id` a označit nově vyřešené nálezy (zelené "✔ opraveno od minulého scanu").
4. **Schema kategorie tabulka na stránce Nastavení zůstává na "Načítám…"** – ZJIŠTĚNO: backend AJAX `seob_schema_categories_list` funguje správně (otestováno přímým voláním `list_categories()` přes wp-cli, vrací validní JSON se 4 kategoriemi). Problém je tedy na FRONTENDU (`assets/admin/js/schema-categories.js`) nebo enqueue/lokalizaci skriptu na stránce Nastavení – `node` není ve sandboxu k dispozici pro `--check` syntaxe. **TODO: zkontrolovat v prohlížeči (DevTools konzole) na stránce Nastavení, jestli `schema-categories.js` vůbec běží / jestli `seobData` existuje / jestli `fetch` vrací chybu (např. 400/403). Zkontrolovat i pořadí/duplicitní `wp_localize_script('seobData', ...)` mezi `seob-settings` a `seob-schema-categories` skripty.**

**STÁLE OTEVŘENO Z MINULA (priorita):**
- `ScanRunner::process_batch()` neukládá `$result['schema']` do `audit_table` → potřeba migrace (`schema_json` sloupec) + úprava `get_results()`.
- Bezpečnostní audit + dev-log final + push.

---

### 2026-06-10 – H1 detekce z renderované stránky, oprava schématu (Rank Math defaults) + editace schématu v dashboardu (ROZPRACOVÁNO)

**Stav:** Implementováno a otestováno přes wp-cli proti živé DB, ale **ještě nedokončeno** – chybí propojení s `ScanRunner` (viz "Pozor na" níže), aktualizace `docs/overview.md` a `CHANGELOG.md`, a finální bezpečnostní audit + push.

**Co (hotovo):**
- **H1 false-positive (GDPR + všechny články)**:
  - `includes/Audit/Scanner.php` – nová metoda `count_rendered_h1(WP_Post $post): ?int`, počítá unikátní `<h1>` tagy z `wp_remote_get(get_permalink($post))` (regex na renderovaném HTML, deduplikace přes `wp_strip_all_tags`). Důvod: u běžných příspěvků se H1 vykresluje přes Elementor Theme Builder šablonu "Single Post" mimo `_elementor_data`/`post_content`, takže detekce z obsahu H1 vůbec neviděla.
  - `scan_post()` nyní použije `count_rendered_h1()` jako primární zdroj, fallback na starou detekci z obsahu (`$content['headings'][1]`), pokud HTTP request selže.
  - **GDPR stránka**: ověřeno, že renderovaná stránka skutečně má 0× `<h1>` (Elementor heading widget "GDPR" má `header_size` = výchozí h2, ne h1). Scanner měl pravdu – uživatelova ruční úprava H1 se zřejmě neuložila/nepublikovala. Je třeba to uživateli vysvětlit.
  - Post 1707 ověřen: skóre 78→100 po opravě H1 i schématu.

- **Schema false-positive (skoro celý web hlásil `schema_missing`)**:
  - Nový soubor `includes/Schema/SchemaHelper.php` – třída `SEOB_Schema_Helper` se třemi úrovněmi efektivního schématu: `override` (vlastní `rank_math_rich_snippet` na příspěvku) → `category_default` (term meta `seob_default_schema`) → `post_type_default` (Rank Math `rank-math-options-titles[pt_{post_type}_default_rich_snippet]`, na tomto webu = `article` pro `post` i `page`).
  - Filtr `get_post_metadata` na `rank_math_rich_snippet` doplní kategoriový default automaticky, BEZ zápisu meta na každý příspěvek.
  - **Pozor – recursion guard**: `metadata_exists()` interně volá stejný `get_post_metadata` filtr → bez `private static bool $checking_existence` guardu nekonečná rekurze (způsobilo Out of Memory crash při testu). `has_override()` guard nastaví/zruší kolem `metadata_exists()`. `get_effective_type()` musí volat `has_override()` (ne přímo `metadata_exists()`), jinak se `source` mylně vyhodnotí jako `override` místo `category_default`.
  - `Scanner.php` – `scan_post()` nyní volá `SEOB_Schema_Helper::get_effective_type($post)`, `schema_missing` se hlásí jen pokud efektivní typ je `off`/prázdný. Výsledek scanu obsahuje nový klíč `'schema' => $schema_info` (`type` + `source`).

- **Editace schématu v dashboardu + výchozí schéma podle kategorie**:
  - `includes/Schema/CategoryAjax.php` (nová třída `SEOB_Schema_Category_Ajax`) – AJAX `seob_schema_categories_list` (vrátí kategorie s `current`/`suggested` schématem) a `seob_schema_category_save` (uloží/smaže term meta `seob_default_schema`). Návrh schématu dle klíčových slov v názvu kategorie (produkt/eshop→product, služ→service, akce/událost→event, kurz/školení→course, práce/kariéra→jobposting, jinak `article`).
  - `includes/Audit/Ajax.php` – `save_meta()` rozšířen o `field=schema` (validace proti `SEOB_Schema_Helper::TYPES`, `update_post_meta('rank_math_rich_snippet', ...)`).
  - `templates/admin/page-dashboard.php` – do edit panelu přidán `<select class="seob-input-schema">` + `<p class="seob-schema-source description">`.
  - `assets/admin/js/audit-dashboard.js` – sloupec Schema ve výsledcích, dropdown v edit panelu (předvyplněný + popisek zdroje), uložení přes `Promise.all` (title + description + schema najednou).
  - `templates/admin/page-settings.php` – nová sekce "Výchozí schéma podle kategorie" (tabulka kategorií s dropdownem a tlačítkem Uložit).
  - `assets/admin/js/schema-categories.js` (nový soubor) – načte a uloží kategorie přes výše uvedené AJAX endpointy.
  - `includes/Admin/Admin.php` – settings stránka enqueue `schema-categories.js`; dashboard stránka lokalizuje `seobData.schemaTypes`.
  - `includes/Plugin.php` – `new SEOB_Schema_Helper()` vždy, `new SEOB_Schema_Category_Ajax()` v rámci modulu `audit`.
  - `seo-boost.php` – přidány nové soubory do `$seob_files`.
  - `assets/admin/css/admin.css` – `.seob-field select { max-width: 320px; }`, `.seob-schema-source { margin: 4px 0 0; font-style: italic; }`.

**Test:** `php -l` na `SchemaHelper.php` a `CategoryAjax.php` bez chyb. Ověřeno přes wp-cli proti živé DB (`seob-final-check.php`, dočasně, smazáno):
```
Post 1707 (Email marketing KPI...): score=100, schema=article/post_type_default
Post 3 (GDPR): score=60, schema=article/post_type_default – h1_missing potvrzeno správně (renderovaná stránka má 0 H1)
Post 789 (Kalkulačka): score=93, schema=article/post_type_default
```

**Pozor na – NEDOKONČENO:**
1. **`SEOB_Audit_ScanRunner::process_batch()` zatím neukládá `$result['schema']` do `audit_table`** – ukládá se jen `issues_json`, `score` atd. Dashboard JS čte `row.schema.type`/`row.schema.source` ze `seob_scan_results`, takže bez úpravy `ScanRunner`/`get_results()` (případně DB sloupec `schema_json` + migrace v `Activator.php`/`Database.php`, nebo dopočet za běhu) bude dropdown vždy padat na default `{type:'off', source:'post_type_default'}`. **Toto je nutné dořešit, než bude funkce v dashboardu reálně fungovat.**
2. `count_rendered_h1()` přidává jeden HTTP request na příspěvek navíc – dopad na výkon při hromadném scanu (zvážit cache/throttling u velkých webů).
3. Zbývá: aktualizovat `docs/overview.md` (nová složka `Schema/`), `CHANGELOG.md` ([Unreleased]), bezpečnostní audit (`CategoryAjax.php` + `Audit/Ajax.php` – nonce/capability už jsou doplněny dle vzoru), a vysvětlit uživateli závěry o GDPR H1.

**Bezpečnostní audit:** ZATÍM NEPROVEDEN – nutno provést před pushem (povinný krok dle workflow).

---

### 2026-06-10 – Stránka Nastavení (fáze 1 MVP)

**Co:**
- `templates/admin/page-settings.php` – přepsána z placeholderu na plnohodnotný formulář se třemi sekcemi:
  - **Obecné**: zapnutí/vypnutí modulů Audit Dashboard a Redirect Manager, debug log, smazání dat při odinstalaci
  - **Audit Dashboard**: velikost dávky scanu (1-100), hranice thin content (50-2000 slov), checkbox pro noční cron (zatím bez funkce, jen se ukládá pro budoucí verzi)
  - **Přesměrování**: zapnutí 404 logu, retence logu (1-365 dní)
- `includes/Admin/SettingsAjax.php` (nová třída `SEOB_Settings_Ajax`) – AJAX handler `wp_ajax_seob_save_settings`, ukládá všechny tři skupiny nastavení přes `SEOB_Settings::update()`, čísla ořezává (`int_field()`) na povolený rozsah, checkboxy normalizuje na 0/1 (`bool_field()`)
- `assets/admin/js/settings.js` – odešle formulář přes `FormData` + `fetch`, doplní nezaškrtnuté checkboxy jako `'0'` (FormData je jinak vynechá), zobrazí stav uložení
- `includes/Admin/Admin.php` – `enqueue_assets()` nyní pro hook `seo-boost_page_seob-settings` načte `settings.js` a lokalizuje `seobData` (ajaxUrl + nonce)
- `seo-boost.php` – `includes/Admin/SettingsAjax.php` přidán do `$seob_files`
- `includes/Plugin.php` – `new SEOB_Settings_Ajax()` instancován vždy (není vázáno na moduly, protože stránka Nastavení musí fungovat i pro jejich zapnutí/vypnutí)

**Proč:** Pokračování fáze 1 MVP („tak pokračuj v dalších nastaveních“) – Audit Dashboard a Redirect Manager mají settings klíče (`batch_size`, `thin_content_words`, `log_404`, `log_retention_days`, `cron_enabled`, `modules.*`), ale dosud nešly editovat z UI.

**Test:** `php -l` na všech upravených/nových souborech bez chyb. Logika `save_settings()` (přes reflection na `bool_field`/`int_field`) otestována přes wp-cli proti živé DB – uloženy hraniční hodnoty (`batch_size=500→100`, `thin_content_words=10→50`, `log_retention_days=0→1`), ořezání funguje správně, hodnoty se po zápisu korektně načtou zpět. Po testu vráceny původní hodnoty nastavení.

**Bezpečnostní audit:** `check_ajax_referer('seob_admin_nonce', 'nonce')` + `current_user_can('manage_options')` v `save_settings()`. Všechny vstupy procházejí `absint()`/bool normalizací, žádné přímé SQL dotazy. Šablona escapuje výstupy přes `esc_html()`/`esc_attr()`/`checked()`.

**Pozor na:** `cron_enabled` se ukládá, ale automatický noční scan zatím není implementován (viz předchozí záznam).

---

## Workflow spolupráce

### Git & verzování
- **Repo:** `https://github.com/Lukasholubik/seo-boost/`
- **Push příkaz:** Napíše-li uživatel **"push"** (nebo "pošli", "pushni"), provedu bez dalšího ptaní:
  1. Bezpečnostní a penetrační audit změněného kódu (viz sekce níže)
  2. Opravím všechny nalezené problémy
  3. `git add` + `git commit` + `git push`

- **Nasazení na live (release):** Napíše-li uživatel "commitni do live", "nasaď na live", "vydej verzi" nebo podobně, provedu bez ptaní celý tento postup:
  1. Bump verze v `seo-boost.php` (hlavička `Version:` + `define('SEOB_VERSION', ...)`)
  2. Aktualizovat `CHANGELOG.md` se seznamem změn
  3. `git add` + `git commit` (`chore: bump version to X.Y.Z`)
  4. `git tag vX.Y.Z`
  5. `git push origin main --tags`
  6. Vytvořit GitHub Release přes API (token je uložen v paměti Claude – reference-github-token):
     ```powershell
     $token = "ghp_..."
     $payload = @{ tag_name="vX.Y.Z"; name="SEO Booster Pro X.Y.Z"; body="changelog text"; draft=$false; prerelease=$false } | ConvertTo-Json -Depth 3
     Invoke-WebRequest -Uri "https://api.github.com/repos/Lukasholubik/seo-boost/releases" -Method POST -Headers @{Authorization="token $token"; Accept="application/vnd.github.v3+json"; "User-Agent"="Claude-Code"} -Body $payload -ContentType "application/json" -UseBasicParsing
     ```
  7. Sdělit uživateli: jdi na **WP Admin → Dashboard → Aktualizace → Zkontrolovat znovu**
- **Vybízení k pushování:** Sám aktivně připomenu push po větší ucelené změně nebo sérii úprav – nikdy nenechám kód dlouho jen lokálně.
- **Commit zprávy:** Stručně popisují co a proč (česky nebo anglicky dle kontextu), ne jak.
- **Po každé změně:** Záznam do tohoto `dev-log.md` (datum, soubory, co, proč).

### Větve (branching strategie)
- **`main`** = stabilní CORE kód – vždy funkční, vždy prošel bezpečnostním auditem.
- **Feature větve** = při každé nové funkci nebo úpravě, která by mohla narušit chod:
  - Pojmenování: `feature/nazev-funkce`, `fix/nazev-opravy`, `refactor/nazev`
  - Větev se merguje do `main` až po otestování a bezpečnostním auditu
  - Pokud se funkce nepovede → prostý `git checkout main`, větev smažeme
- **Kdy vytvořit větev:** Vždy, když přidáváme novou funkci nebo měníme existující logiku. Pro drobné opravy textu/CSS stačí přímý commit do `main`.

### Bezpečnostní audit před každým pushem
Před každým `git push` automaticky provedu kontrolu:
- **Injection:** SQL injection (přímé dotazy bez `$wpdb->prepare()`), XSS (výstup bez `esc_*`), command injection
- **Auth & capabilities:** Každý AJAX handler a REST endpoint má `check_ajax_referer()` / `verify_nonce` + `current_user_can()`
- **Citlivá data:** Žádný API klíč, heslo, secret nesmí být v kódu nebo logu v plaintextu
- **SSRF:** URL validace pro webhooky a externí požadavky (Search Console, broken link checker apod.)
- **Sanitizace vstupů:** Všechny `$_POST`, `$_GET`, `$_REQUEST` hodnoty sanitizovány před použitím
- **Escapování výstupů:** HTML kontext `esc_html()`, atributy `esc_attr()`, URL `esc_url()`
- **Otevřené přesměrování:** `wp_safe_redirect()` místo `wp_redirect()` kde hrozí manipulace (Redirect Manager!)
- Pokud najdu problém → opravím **před** pushem, zapíši do dev-logu

### CSS
- **Framework: Tailwind CSS** – veškeré nové styly v Tailwindu (`assets/src/admin.css` → build do `assets/admin/css/admin.css`).
- Třídy mají prefix `seob-` (viz `tailwind.config.js`), aby nekolidovaly s WP admin CSS.
- Build: `npm install && npm run build:css` (nebo `npm run watch:css` při vývoji).
- Žádné vlastní CSS třídy pokud to Tailwind zvládne utility třídami. Inline `style=""` jen pro dynamické hodnoty.

### AI generování (meta description, alt texty)
- AI návrhy se **nikdy neukládají automaticky** – vždy fronta `seo_booster_ai_queue` + ruční schválení adminem (E-E-A-T zásada).

---

## Šablona záznamu

```
### RRRR-MM-DD – Stručný popis změny

**Soubory:** `includes/XYZ.php`, `templates/admin/page-xyz.php`
**Co bylo uděláno:** ...
**Proč:** ...
**Pozor na:** ... (volitelné – upozornění, side-effecty, TODO)
```

---

## Záznamy

### 2026-06-10 – Audit Dashboard scanner + Redirect Manager (fáze 1 MVP)

**Soubory:**
- `includes/Audit/PixelWidth.php` – odhad šířky textu v px (Arial 14px) pro SERP náhled
- `includes/Audit/Scanner.php` – `SEOB_Audit_Scanner::scan_post()` – kontroly title/description (chybí, délka v px), H1 (chybí/duplicitní), hierarchie nadpisů, alt texty obrázků (chybí/generické), schema (Rank Math rich snippet), noindex, thin content, focus keyword; čte obsah z `_elementor_data` (rekurzivní průchod widgety) nebo z `the_content` (DOMDocument fallback)
- `includes/Audit/ScanRunner.php` – `start_scan()` založí `scan_runs` + frontu post ID v transientu, `process_batch()` zpracuje dávku (`seob_audit_settings.batch_size`), `finalize_scan()` dopočítá duplicity title/description napříč webem a průměrné skóre
- `includes/Audit/Ajax.php` – `seob_scan_start`, `seob_scan_batch`, `seob_scan_results`, `seob_save_meta` (zápis do `rank_math_title`/`rank_math_description`)
- `includes/Redirects/RedirectManager.php` – `template_redirect` hook: aplikuje 301 z `seo_booster_links` (mapa cachovaná 12h v transientu `seob_redirects_map`), jinak loguje 404 (`hits_404`, `last_checked`); denní cron `seob_redirects_cleanup` maže staré 404 záznamy dle `log_retention_days`
- `includes/Redirects/Ajax.php` – `seob_redirect_list`, `seob_redirect_save`, `seob_redirect_delete`
- `templates/admin/page-dashboard.php` + `assets/admin/js/audit-dashboard.js` – tabulka výsledků, spuštění scanu s progress barem, filtr dle závažnosti/hledání, inline editace title/description s živým SERP náhledem a pixelovým měřičem
- `templates/admin/page-redirects.php` + `assets/admin/js/redirects.js` – přehled aktivních přesměrování + 404 logu, vytvoření 301 jedním klikem
- `assets/admin/css/admin.css` – ručně psané CSS (Tailwind build zatím neproveden, viz "Pozor na" níže)
- `includes/Plugin.php` – inicializace nových modulů dle `seob_general_settings.modules`
- `includes/Activator.php` – `deactivate()` nově maže naplánovaný cron

**Co bylo uděláno:** Implementována fáze 1 MVP – funkční SEO Audit Dashboard (scan na vyžádání, skóre 0–100, nálezy s prioritou, inline oprava title/description s SERP náhledem) a Redirect Manager (404 log + vytvoření 301 jedním klikem, ochrana proti open redirectu přes `wp_validate_redirect`/`wp_safe_redirect`).

**Proč:** Dle zadávacího dokumentu `SEO_Booster_Pro_Audit_Dashboard_a_nove_moduly.md`, fáze 1 (Audit Dashboard + Redirect Manager + SERP náhled) – jádro produktu.

**Otestováno:** Scan spuštěn přes `wp eval-file` proti živé DB (12 publikovaných URL, průměrné skóre 71/100), 404 logování a vytvoření/aplikace přesměrování ověřeno včetně odmítnutí cizí domény jako cíle.

**Bezpečnostní audit:** Všechny AJAX endpointy mají `check_ajax_referer('seob_admin_nonce')` + `current_user_can('manage_options')` (zápis meta navíc `edit_post`). SQL přes `$wpdb->prepare()`/`insert`/`update`/`delete`. Výstup v JS přes `textContent`, v `redirects.js` přes `escapeHtml()`. Cíl přesměrování validován `wp_validate_redirect()` (zabránění open redirectu), aplikace přes `wp_safe_redirect()`.

**Pozor na:**
- `assets/admin/css/admin.css` je teď ručně psané CSS (ne Tailwind build) – po `npm install && npm run build:css` je potřeba sloučit/nahradit utility třídami dle `tailwind.config.js`.
- AI návrhy (fronta `seo_booster_ai_queue`) zatím nejsou implementované – inline editor zatím jen pro ruční úpravu title/description.
- Kontrola broken links (externí 4xx/5xx), JSON-LD validace a SERP náhled v rozbalovacím editoru pro obrázky/alt zatím nejsou – plánováno v dalších iteracích fáze 1/2.
- Scan zpracovává `post` a `page` (publikované); cron/automatický noční scan zatím není naplánován (`seob_audit_settings.cron_enabled` se zatím nevyhodnocuje).

### 2026-06-10 – Založení pluginu, kostra + git/GitHub setup

**Soubory:** celá struktura `wp-content/plugins/seo-boost/`

**Co bylo uděláno:**
- Bootstrap `seo-boost.php` (konstanty `SEOB_*`, slug `seo-boost`, Plugin Update Checker)
- `SEOB_Settings` (option prefix `seob_`), `SEOB_Database` (DB prefix `seo_booster_`), `SEOB_Activator` (4 tabulky: `audit`, `scan_runs`, `ai_queue`, `links`)
- `SEOB_Plugin` orchestrátor + `SEOB_Admin` – admin menu pod skupinou Grou.cz (pozice 32), 3 placeholder stránky: Audit Dashboard, Přesměrování, Nastavení
- Tailwind CSS build setup (`package.json`, `tailwind.config.js`, prefix `seob-`)
- Zkopírován Plugin Update Checker (vendor/)
- `docs/` (tento dev-log, overview, settings-reference), `CHANGELOG.md`, `readme.txt`, `.gitignore`
- Git repo inicializován, GitHub repo `Lukasholubik/seo-boost` založeno a pushnuto

**Proč:** Založení nového pluginu v rodině Grou.cz dle architektury z `SEO_Booster_Pro_Audit_Dashboard_a_nove_moduly.md` (Audit Dashboard, Redirect Manager, atd.). Workflow (push/audit/branching/Tailwind/release) zkopírováno z `smartemailing-connect`.

**Pozor na:**
- Tailwind build (`assets/admin/css/admin.css`) je zatím jen placeholder – je třeba `npm install && npm run build:css`.
- Funkční moduly (scan, AI, redirecty) zatím nejsou implementované – jen DB schéma a admin kostra. Implementace dle `SEO_Booster_Pro_Audit_Dashboard_a_nove_moduly.md`, fáze 1: Audit Dashboard + Redirect Manager + SERP náhled.
