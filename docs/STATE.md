# SEO Booster Pro – STATE

> Tento soubor udržuje přehled: hotové moduly, rozpracované, známé problémy, další krok.
> Na začátku KAŽDÉ session přečti tento soubor + `Zadani_pro_Claude_SEO_Booster_Pro.md` (master zadání, dodal uživatel přes `C:\Users\PC\Downloads\`).

---

## 🟢 ZAČNI ZDE – session 2026-06-17 – v0.9.0 – M12: JS Render Gap (NEPUSHNUTO)

**Nově: M12 – JS Render Gap detektor.** Nový modul `js-render-gap` (výchozí: vypnuto).
Frontend beacon (< 1.5 kB, localStorage rate limit 7 dní) sbírá rendered DOM; REST endpoint
`POST /wp-json/seo-booster/v1/js-gap` ukládá snapshoty; `Comparator` stáhne raw HTML
a porovná title/H1/meta/JSON-LD/text ratio; gap score 0–100; admin dashboard s filtry/re-analýzou;
WP-Cron `seob_js_gap_scan` každé pondělí 03:30 UTC.
`php -l` OK na všech souborech. `SEOB_VERSION` 0.8.0 → 0.9.0, `SEOB_DB_VERSION` 0.9.0.
Nové DB tabulky: `seo_booster_js_gap_snapshots` + `seo_booster_js_gap_results`.
`weekly` WP-Cron schedule registrován přes `cron_schedules` filter v `Plugin.php`.

**DALŠÍ KROK pro M12:** Aktivovat modul „JS Render Gap" v Nastavení → Moduly,
ověřit menu položku „JS Render Gap", navštívit pár stránek webu (beacon odešle snapshot),
v dashboardu kliknout „Spustit analýzu" a ověřit výsledkovou tabulku.

**Zbývá implementovat (ze zadání):**
- M7: Search Console konektor (full OAuth)
- M9: HTTP hlavičky a bezpečnost
- M10: Content Decay Monitor

---

## 🟡 Předchozí ZAČNI ZDE – session 2026-06-16 update 10 (NEPUSHNUTO)

**Nově: M3 – JSON-LD Validator + detektor duplicit.** Novy modul `json-ld` (vychozi: vypnuto).
Extrahuje `application/ld+json` bloky z renderovaneho HTML, validuje vuci schema.org,
detekuje duplicitni schemata. Tridy: `SEOB_JsonLd_Validator`, `SEOB_JsonLd_PageScanner`,
`SEOB_JsonLd_Ajax`. Admin stranka `seob-json-ld`, JS `json-ld.js`, health checks.
21 novych PHPUnit testu. `composer test` – **96/96 OK** (bylo 75). `php -l` OK.
`SEOB_VERSION` 0.7.0 -> 0.8.0.

---

## 🟡 Předchozí ZAČNI ZDE – session 2026-06-16 update 5 (NEPUSHNUTO)

**Nově: M8 – Hreflang Manager.** Nový modul `hreflang` (výchozí: vypnuto).
DB tabulky `seo_booster_hreflang_groups` + `seo_booster_hreflang_members`
(`SEOB_DB_VERSION` → `0.7.0`, `SEOB_VERSION` → `0.6.0`).
Skupinový model: 1 skupina = 1 dokument ve více jazycích.
Hreflang tagy se vkládají do `<head>` přes `wp_head` (priorita 2).
Výstup je automaticky blokován, pokud je detekován RM Pro nebo Yoast Premium.
Validátor hlásí stránky ve více skupinách + nepublikované stránky.
Admin UI: card listing skupin + modal s autocomplete vyhledáváním stránek.

**Soubory:** `includes/Hreflang/Manager.php`, `includes/Hreflang/Ajax.php`,
`templates/admin/page-hreflang.php`, `assets/admin/js/hreflang.js`.
`php -l` **neověřeno**, UI **neotestováno** – nutno aktivovat modul v Nastavení → Moduly.

**DALŠÍ KROK pro M8:** Aktivovat modul Hreflang v Nastavení → Moduly, ověřit
menu položku „Hreflang Manager", vytvořit testovací skupinu, spustit validaci.

---

## 🟡 Předchozí ZAČNI ZDE – session 2026-06-16 (NEPUSHNUTO)

**Nově: tlačítko „Vložit linky" v metaboxu editoru (M6 rozšíření).**
`includes/InternalLinks/LinkInserter.php` (nový), `Ajax.php` (+`seob_links_insert`),
`MetaBox.php` (+tlačítko+enqueue), `assets/admin/js/metabox-internal-links.js` (nový).

Flow: 1 klik na „Vložit linky" → AJAX najde orphan stránky, jejichž titulek je v obsahu článku
→ vloží max 3 `<a>` odkazů (první výskyt, chrání existující `<a>` a nadpisy) → `wp_update_post`.
Elementor stránky: 1. klik = varování, 2. klik = potvrzení (`force=1`).
`php -l` OK, **neotestováno v prohlížeči.**

**DALŠÍ KROK:**
1. Ověřit v prohlížeči menu položku „Interní prolinkování" (předchozí DALŠÍ KROK z 2026-06-15)
2. Otevřít editor článku → zkontrolovat metabox „Interní prolinkování" → kliknout „Vložit linky"
   → ověřit, že se zobrazí výsledek a obsah článku se uložil s novými `<a>` tagy
3. Ověřit Elementor stránku: varování + dvojitý klik

---

## 🟡 Předchozí ZAČNI ZDE – konec session 2026-06-15 (NEPUSHNUTO)

**Nově: M6 – Interní prolinkování (Internal Link Assistant + orphan pages),
nový modul `internal-links`.** Indexuje interní link graf, hledá osamocené
(orphan) stránky a navrhuje top 3 prolinkování přes lokální TF-IDF +
kosinovou podobnost (bez externí AI). 3 nové DB tabulky
(`internal_links`/`link_suggestions`/`link_scans`, `SEOB_DB_VERSION` →
0.6.0, `SEOB_VERSION` → 0.5.0), nová stránka „Interní prolinkování“
(`seob-internal-links`), metabox v editoru, health check. Detaily v
`docs/dev-log.md` (záznam "M6: Interní prolinkování...") a
`docs/modules/internal-links.md`.

`composer test` **56/56 OK** (bylo 38, +18 nových testů –
`tests/Unit/InternalLinks/ExtractorTest.php` a `SimilarityTest.php`),
`php -l` OK na všech nových/upravených souborech.

**Ověřeno (2026-06-15, přes wp-cli, bez prohlížeče), reálná DB (87
položek)**: modul aktivován, plný reindex `87/87` proběhl, `orphans_count=82`,
`avg_inlinks=0.11`. Návrhy dávají smysl (např. orphan stránka „CTA“ ze
Slovíčku pojmů navrhuje odkázat z „CTA tlačítko“/„CTA na webu“, score
0.31/0.30). Health check `critical` → po reindexu `good`+`warning` (82
osamocených stránek). **Modul je na tomto test webu nyní aktivní**
(`modules.internal-links = 1`).

**Cestou se objevil a opravil bug**: sloupec `rank` v
`seo_booster_link_suggestions` je od MySQL 8.0.2 rezervované slovo –
`dbDelta()` tuto jednu tabulku tiše nevytvořil (ostatní 2 nové tabulky OK).
Přejmenováno na `rank_order`.

**NEOTESTOVÁNO V PROHLÍŽEČI**: dashboard stránka „Interní prolinkování“
(tlačítko reindex, progress bar, tabulky, trendy) a editor metabox „Interní
prolinkování“ – jen ověřeno přes wp-cli/PHP, ne přes UI.

**OPRAVENO PO SESSION (aktivace modulu)**: smoke-test skript původně zapsal
`modules.internal-links=1` do **špatného option klíče**
(`seob_settings_general` – neexistuje), takže `is_active('internal-links')`
vracel `false` a v menu SEO Booster položka „Interní prolinkování“ chyběla.
Opraveno přes jednorázový skript – zapsáno do správného klíče
`SEOB_Settings::GENERAL` = `seob_general_settings`. Po refreshi adminu by se
položka menu měla zobrazit mezi „PageSpeed Insights“ a „Stav systému“ –
**čeká na potvrzení uživatele, že se menu položka objevila**.

## ⏭️ DALŠÍ KROK (pokračovat zde zítra)

1. Otevřít admin (`reboost-test.local/wp-admin`) a ověřit, že v menu SEO
   Booster je položka **„Interní prolinkování“**.
2. Vizuálně otestovat stránku `seob-internal-links`: souhrn (87 stránek /
   82 orphans / avg 0.11), tabulky „Osamocené stránky“ a „Všechny stránky“,
   tlačítko „Spustit reindex“ + progress bar.
3. Otevřít editor nějaké stránky (např. „CTA“ ID 1301 nebo „GDPR“ ID 3) a
   ověřit postranní metabox „Interní prolinkování“ (počty odkazů + 3 návrhy
   s funkčními edit-linky).
4. Pokud vše OK → zapsat výsledek do `dev-log.md`, případně řešit drobné
   UI nedostatky. Beze "push" do `feature/audit-dashboard-redirects`, dokud
   to uživatel neřekne explicitně.

---

**Předchozí: souhrnný přehled celého webu na stránce PageSpeed Insights.**
`get_results()` vrací nový klíč `overall` => `['mobile' => [...,'deltas'],
'desktop' => [...]]` – vážený průměr (`compute_overall_scores()`, váha =
`sample_size`) přes `performance_avg/accessibility_avg/best_practices_avg/seo_avg`
ze všech `psi_summary` řádků daného běhu, + delta vs. předchozí běh. V UI
nová karta "Celkový přehled webu (průměr ze všech typů obsahu)" nad
jednotlivými skupinami (`#seob-psi-overall`, šablona
`#seob-psi-overall-template`, `renderOverall()` v `pagespeed.js`).
`composer test` **38/38 OK**, `php -l` OK. **V prohlížeči neotestováno** –
na `.local` webu nejsou žádná validní skóre (viz limit níže), takže karta
ukáže jen "–" dokud neproběhne run s reálnými daty (produkce/tunel).
Detaily v `docs/dev-log.md` (záznam "PageSpeed Insights: souhrnný přehled
celého webu...").

**ZNÁMÝ LIMIT – PageSpeed Insights nelze testovat na .local webu.** Běh
`#4` (50 položek) doběhl, ale VŠECH 51 `psi_results` má
`error: "Lighthouse returned error: FAILED_DOCUMENT_REQUEST ... net::ERR_CONNECTION_FAILED"`,
takže `psi_summary` má jen prázdné skóre (UI ukazuje "–" a "Žádné výrazné
SEO nálezy"). **Příčina**: Google PSI API se musí připojit k URL z
internetu – `reboost-test.local` (Local by Flywheel) není veřejně dostupný.
**Není to bug v kódu** – modul je hotový a funkční, jen ho nelze ověřit na
tomto lokálním webu. Otestovat až na veřejné doméně (reboost.cz), případně
přes tunel (ngrok apod.).

**Git stav**: `wp-content/plugins/seo-boost/` je samostatný git repo
(`c:\Users\PC\Local Sites\reboost-test\app\public\wp-content\plugins\seo-boost`),
branch `main`. Velké množství necommitnutých změn (PageSpeed Insights modul
celý + dnešní Audit Dashboard úpravy) – `git status` ukazuje M na ~17
souborech + nové `assets/admin/js/pagespeed.js`, `docs/modules/pagespeed.md`.
**Nic z toho není commitnuto ani pushnuto.** Až uživatel řekne "push",
udělat `git add` + `commit` + `push` v tomto adresáři (ne v rootu webu –
root repo trackuje jen `local-xdebuginfo.php`/`wp-rocket-config` jako
untracked, nesouvisí).

**Ověřeno (2026-06-15, přes wp-cli, bez prohlížeče)** – scan `#26`, 87
položek:
1. ✅ 3 skupiny (post avg=97, page avg=68, slovicek-pojmu avg=93).
2. ✅ Skóre = normální čísla 0-100, žádný vědecký zápis (`score_avg=91`).
3. ✅ `group_score_deltas` (page -2, post/slovicek 0) + `group_issue_changes`
   (page.new.description_missing=1, slovicek-pojmu.resolved.thin_content=1) –
   `ISSUE_LABELS` mapuje obě hodnoty na české popisky pro tooltip.
4. ✅ Slovíček pojmů – post 1306 skóre 100, bez `thin_content` (reálný počet
   slov, 74/75 stále pod limitem 300 = legitimní, ne falešná 0).

Vizuální ověření v prohlížeči (rozbalení skupin, pozice tooltipů) zatím
neproběhlo – live link/tunel není k dispozici na tomto PC, odloženo na
produkci.

**Další krok**: čeká na zadání uživatele (push změn? další modul?).

---



**Nově: trend skóre vs. minulý audit – celkově, po kategoriích i po
stránkách, s tooltipem "v čem konkrétně".** `get_results()` vrací
`score_delta`/`new_issues`/`resolved_issues` (per stránka), celkový
`score_delta`, `group_score_deltas` a `group_issue_changes` (per kategorie).
V UI `▲/▼` vedle skóre badge + tooltip "Zlepšeno/Zhoršeno: konkrétní nález".
Skupina má i popisek "SEO skóre". Detaily v `docs/dev-log.md` (záznamy
"Audit Dashboard: trend skóre..." a "...oprava skóre skupiny...").

**Oprava bugu**: skóre skupiny se po zapnutí trendů zobrazovalo jako
absurdní čísla (`row.score` z DB je string → `+` dělal konkatenaci).
Opraveno převodem na `Number` v `loadResults()`. wp-cli ověřeno na scanu
`#26`: `group_score_deltas` i `group_issue_changes` vrací správné hodnoty
(`page.new.description_missing=1`, `slovicek-pojmu.resolved.thin_content=1`).
`composer test` **38/38 OK**. **V prohlížeči ještě neotestováno – další
krok** (zkontrolovat, že se skóre skupin zobrazuje jako normální číslo
0-100, ne vědecký zápis).

**Oprava: kontrola obsahu (počet slov) u "Slovíček pojmů" dřív ukazovala
0 u všech 75 položek** (JetEngine CPT ukládá obsah do meta polí, ne
`post_content`). Nyní `Scanner::collect_meta_content()` poskládá obsah
z textových meta polí (obecně, ne natvrdo pro tento CPT). Detaily v
`docs/dev-log.md` (záznam "Audit scan: oprava kontroly obsahu...").

wp-cli: scan `#26` (87 položek) doběhl po opravě – `score_avg=91`,
`slovicek-pojmu` avg=93 (74/75 nyní legitimně `thin_content`, reálný počet
slov ~248 < limit 300, ne falešná 0). `composer test` **38/38 OK**.

**Audit scan nově skenuje VŠECHNY relevantní post typy webu, ne jen
post/page.** `SEOB_Audit_ScanRunner::get_audit_post_types()` dynamicky
zjistí veřejné post typy s publikovaným obsahem a vyloučí Elementor/JetEngine
builder typy (`jet-popup`, `e-floating-buttons`, `elementor_library`,
`jet-theme-core`, `attachment`). Na tomto webu to nově vrací
`['post', 'page', 'slovicek-pojmu']` (4 + 8 + 75 = 87 položek). Detaily
v `docs/dev-log.md` (záznam "Audit scan: dynamické post typy podle webu...").

**Audit Dashboard nově seskupuje výsledky podle typu obsahu** – sbalitelné
skupiny, nejhorší skóre nahoře, počty kritických/varování/doporučení
v hlavičce. Díky dynamickým `postTypeLabels` se nyní automaticky objeví i
skupina "Slovíček pojmů". Detaily v `docs/dev-log.md` (záznam "Audit
Dashboard: výsledky seskupené podle typu obsahu..."). `composer test`
**38/38 OK**.

wp-cli: scan `#25` (87 položek) doběhl – `status=done`, `score_avg=91`,
výsledky: 4 `post` + 8 `page` + 75 `slovicek-pojmu` (přesně podle očekávání).
**Zbývá otevřít Audit Dashboard v prohlížeči a ověřit, že se zobrazí 3
skupiny (Příspěvky/Stránky/Slovíček pojmů) se správnými labely a skóre –
další krok.**

**Modul "PageSpeed Insights (Lighthouse)" hotový, nakonfigurovaný a doplněný
o běh na pozadí (WP-Cron) + historii/porovnání skóre.** Detaily v
`docs/dev-log.md` (záznamy "PageSpeed Insights: běh na pozadí (WP-Cron)..."
a starší).

Uživatel vytvořil free PSI API klíč (omezený jen na „PageSpeed Insights
API“), uložen šifrovaně do `SEOB_Settings::PAGESPEED`, modul **zapnut**
(`enabled=1`). Health check vrací `pagespeed_last_run: good`.

Uživatel reálně spustil scan (běh `#4`, 50 položek) a požádal o:
1. **Běh na pozadí** – implementováno přes WP-Cron (`SEOB_PageSpeed_ScanRunner::CRON_HOOK`),
   ověřeno wp-cli smoke testem, že `get_active_run()` vrací reálně postupující
   běh (33/50 → 34/50 mezi dvěma voláními bez zásahu uživatele).
2. **Historie + porovnání skóre** – `get_run_history()`, `get_results($run_id)`
   s `compute_deltas()` vůči předchozímu běhu, dropdown "Historie běhů" +
   `.seob-psi-delta-*` indikátory v UI.

**Zatím nepushnuto.**

**Co zbývá (další krok):**
1. Otevřít **SEO Booster Pro → Audit Dashboard** v prohlížeči – ověřit
   skupiny (Příspěvky/Stránky), rozbalení/sbalení, filtry (severity/search)
   uvnitř skupin, GSC sloupce, AI návrhy v rozbaleném řádku.
2. Otevřít **SEO Booster Pro → PageSpeed Insights** – běh `#4` by měl
   mezitím doběhnout na pozadí (WP-Cron se spouští při návštěvě webu).
   Ověřit progress bar po reloadu (resume přes `seob_psi_active`), historii
   běhů v dropdownu, a po druhém scanu deltu (+/-/0) oproti běhu `#4`.

**Dále stále čeká (z předchozí session, beze změny):**

**Git stav:** Celý dlouho narostlý balík (Stav systému/ModuleManager,
Chytrá indexace, GSC insights, PDF redesign, schema overrides, scan
history, AI schvalovací fronta, Composer/PHPUnit) byl po krátkém
bezpečnostním review (zaměřeno na nové AJAX endpointy – AiQueue, SmartIndexing,
Status, Pdf, SettingsAjax: nonce + `manage_options`/`edit_post` capability
checks, sanitizace, `textContent` v JS, AES-256-CBC pro API klíč – beze
zjištění) commitnut a pushnut na `feature/audit-dashboard-redirects`
(commit `d471fd6`). **`main` nedotčen, branch nemergována.**

**Co zbývá (další krok = bod D níže):** Otestovat AI schvalovací frontu
v prohlížeči – vyžaduje od uživatele free API klíč (Gemini/Groq, návod v
`docs/modules/ai-queue.md`): nastavení AI asistenta, tlačítka v Audit
Dashboardu, stránka AI fronta, health check.

**Jak pokračovat příští session:** Přečíst tento soubor (hotovo automaticky),
zeptat se uživatele, jestli už má API klíč na test PageSpeed Insights nebo AI
fronty (bod D), nebo zda chce probrat jiný bod z "Další krok" (1-3).

---

## ✅ VYŘEŠENO – web byl rozbitý (fatal error), teď znovu funguje

**Co se stalo:** Soubor `wp-content/plugins/seo-boost/includes/Audit/ScanRunner.php` během session zmizel
z disku (pravděpodobně karanténa Norton 360 / Windows Defender / Avast – na stroji běží všechny tři současně),
zatímco `seo-boost.php` ho přes `require_once` natvrdo načítal → **fatal error na úplně KAŽDÉM page loadu**
(frontend, wp-admin, i wp-cli – `wp option get siteurl` hlásilo "Na webu došlo k závažné chybě.").

**Jak je to teď opravené:**
- Obsah souboru (včetně rozpracovaného `get_scan_history()` pro bod 3) byl rekonstruován z toho, co bylo přečteno
  dřív v session, a uložen pod **novým jménem `includes/Audit/AuditScanRunner.php`** (přesný název
  `ScanRunner.php` na této cestě nešlo vytvořit – AV blokuje zápis/vytvoření souboru s tímto přesným jménem
  case-insensitive, i když `icacls` na složku ukazuje FullControl pro PC/Administrators/SYSTEM; jiná jména typu
  `Scan_Runner.php`/`AuditScanRunner.php` jdou bez problémů).
- `seo-boost.php` (seznam `$seob_files`, řádek ~55) upraven: `'includes/Audit/ScanRunner.php'` →
  `'includes/Audit/AuditScanRunner.php'`. Třída uvnitř zůstává `SEOB_Audit_ScanRunner` (beze změny), takže
  `Audit/Ajax.php` a vše ostatní funguje beze změny.
- Ověřeno přes wp-cli: `wp option get siteurl` vrací `http://reboost-test.local` (žádný fatal error) a
  `(new SEOB_Audit_ScanRunner())->get_scan_history()` vrací očekávaných 14 dokončených scanů (id 1-16).

**Pokud se v budoucnu objeví podobný problém** (soubor zmizí / nejde vytvořit přesně pod tímto jménem na této
cestě), je to s velkou pravděpodobností stejná AV karanténa – řešení: vytvořit soubor pod jiným jménem a upravit
`require_once` v `seo-boost.php`. Ideálně časem najít a vyčistit AV karanténu (potřeba admin práva, GUI Norton/
Avast/Defender), aby se dal soubor vrátit na "správné" jméno `ScanRunner.php`.

---

## ✅ Architektonické rozhodnutí (vyřešeno 2026-06-11)

Master zadání (`Zadani_pro_Claude_SEO_Booster_Pro.md`) předepisuje namespace `SEOBoosterPro\`,
REST API, `ModuleManager`, `seo_booster_metrics` tabulku, Site Health integraci, PHPUnit,
`docs/modules/*.md` pro každý modul, AI schvalovací frontu.

**Uživatel rozhodl:** ponechat stávající `SEOB_` strukturu (žádný velký refaktor na namespace/REST/
ModuleManager) a jen postupně DOPLNIT chybějící DoD prvky (docs/modules, `seo_booster_metrics`,
health check/"Stav systému", PHPUnit – odloženo, composer není v dev prostředí dostupný).

**Priorita uživatele:** nejdřív sekce 5 zadání ("Společná infrastruktura" – ModuleManager, Stav
systému, metrics tabulka), ALE toto bylo dočasně odsunuto kvůli dvěma akutním bugům (D1, D2 níže) –
zůstává jako **další krok** po jejich dokončení.

---

## ✅ Společná infrastruktura – jádro hotovo (2026-06-11)

Implementováno: `SEOB_Module_Manager` (`includes/ModuleManager.php`),
`seo_booster_metrics` tabulka + `SEOB_Metrics` (`includes/Metrics/Metrics.php`,
`SEOB_DB_VERSION` 0.2.0 → 0.3.0), stránka **Stav systému** (`seob-status`)
s health checky + sparkline grafy + integrace do WP Site Health
(`includes/Health/HealthChecks.php`, `StatusAjax.php`), `docs/modules/*.md`
pro audit/redirects/pdf. `SEOB_VERSION` 0.1.7 → 0.1.8. Detaily viz
`docs/dev-log.md` (záznam "Společná infrastruktura...").

**Aktualizace 2026-06-12:** Composer/PHPUnit scaffolding hotovo (viz "Další
krok" C výše) – jen nespuštěno (composer bez internetu v dev prostředí).

**Odloženo (dle rozhodnutí výše):** AI schvalovací fronta + AI provider
abstrakce, CI/CD + PHPUnit (composer nedostupný).

**Zjištěno při ověřování a OPRAVENO:** `SEOB_Health_Checks::get_checks('audit')`
hlásil `audit_stuck_scan` = critical – scan #8 v `seo_booster_scan_runs` byl
ve stavu `running` od 10. 6. 2026 (0/12 zpracováno, fronta v transientu už
expirovala), nikdy se nedokončil. Šlo o starou mrtvou data z dřívější
session, ne chybu nové implementace. Ručně přes wp-cli opraveno: scan #8
nastaven na `status='error'`, `finished_at` doplněn. `get_checks('audit')`
nyní vrací jen `audit_last_scan` = good (poslední dokončený scan #22,
11. 6. 2026 14:44). `'error'` je nová hodnota `status` – nikde jinde v kódu
se nefiltruje (jen `'done'`/`'running'`), takže nic dalšího neovlivňuje.

**Neotestováno v prohlížeči:** stránka Stav systému, Nástroje → Stav webu
(nové testy `seob_module_*`).

---

## ✅ D1 + D2 – opraveno 2026-06-11

**D1 – schéma „běžná stránka“:** stránky (`/gdpr/`, `/kontakty/`, `/o-nas/` apod.) se zobrazovaly
jako „Článek (Article)“ – potvrzeno přes wp-cli, že jde o reálné defaultní nastavení Rank Math
(`pt_page_default_rich_snippet = 'article'`), NE bug SEOB. Bez zásahu do nastavení Rank Math
přidána do SEO Booster Pro možnost označit typ obsahu/kategorii jako „Běžná stránka (bez schématu
/ výchozí WebPage)“ (`TYPES['off']`), která přes vlastní `get_post_metadata` filtr
(`SEOB_Schema_Helper::filter_rich_snippet_meta()`) skutečně přebije reálný výstup Rank Math
(`rank_math_rich_snippet`), aniž by se cokoli zapisovalo do RM options/postmeta. Audit Dashboard
u takto označených stránek už nehlásí „Chybí strukturovaná data“ (`schema_missing` se hlásí jen
pro neúmyslné `'off'`/prázdné fallbacky, ne pro záměrnou volbu – nový klíč `is_explicit`).

**D2 – thin content v auditu:** nový sloupec „Obsah“ v Audit Dashboardu zobrazuje, zda stránka
splňuje minimální počet slov (`thin_content` nález ze Scanner.php), dřív vidět jen po rozkliknutí
řádku.

Detaily a ověření (wp-cli, php -l) viz `docs/dev-log.md` (záznam "Schéma „běžná stránka“ přebíjí
Rank Math + sloupec „Obsah“..."). `SEOB_VERSION` 0.1.6 → 0.1.7. Pushnuto
2026-06-15 (commit `d471fd6`, viz sekce "ZAČNI ZDE" výše).

---

## Rozpracováno (z předchozí session, 2026-06-10)

Detaily viz `docs/dev-log.md` (záznamy "POKRAČOVÁNÍ 1-4"). Stručně, v pořadí priority:

1. ~~**Schema info uložit do `audit_table`**~~ – HOTOVO (POKRAČOVÁNÍ 4): sloupec `schema_json` + `SEOB_DB_VERSION`/`maybe_upgrade()` migrace + `ScanRunner::process_batch()`/`get_results()` ukládají a dekódují efektivní schéma. Ověřeno end-to-end přes wp-cli.
2. **"Načítám…" u tabulky "Výchozí schéma podle kategorie"** (Nastavení) – backend AJAX i lokalizace skriptů ověřeně fungují (POKRAČOVÁNÍ 5). Přidán `.catch()` do `schema-categories.js`, takže při dalším výskytu se zobrazí konkrétní chybová hláška místo trvalého "Načítám…" – ČEKÁ na uživatele, ať nahlásí, co se teď zobrazí / co je v konzoli (web byl mezitím rozbitý kvůli chybějícímu `ScanRunner.php`, teď opraveno – viz sekce výše, takže lze konečně otestovat v DevTools).
3. **Historie scanů / porovnání verzí** – HOTOVO, ale NEOTESTOVÁNO V PROHLÍŽEČI (POKRAČOVÁNÍ 6): backend (`ScanRunner::get_scan_history()`, AJAX `seob_scan_history` v `Audit/Ajax.php`) i frontend (`<select id="seob-scan-history">` v `page-dashboard.php`, `loadHistory()` + `change` listener + CSS v `audit-dashboard.js`/`admin.css`) jsou hotové, uložené a `get_scan_history()` ověřeno přes wp-cli (vrací 14 scanů). Zbývá ověřit v reálném prohlížeči: dropdown `#seob-scan-history` se naplní při načtení dashboardu a `change` přepne `loadResults(scanId)` na zvolený scan.
4. ~~**"Už opraveno od minulého scanu"**~~ – HOTOVO (2026-06-11): `AuditScanRunner::get_results()` počítá `resolved_issues`/`resolved_total` diffem proti předchozímu dokončenému scanu, dashboard zobrazuje sloupec „Od minula“ + souhrn. Zatím netestováno v prohlížeči (potřeba 2+ dokončené scany).
5. ~~**Sitemapa vs. kategorie pro výchozí schéma**~~ – HOTOVO (D1, 2026-06-11): "Výchozí schéma podle typu obsahu" (post type) teď plně funguje vedle kategorií, včetně volby "Běžná stránka" (`'off'`), a má reálný efekt na výstup Rank Math – viz sekce "D1 + D2" výše.
6. ~~**Bezpečnostní audit + push**~~ – HOTOVO (2026-06-11 a 2026-06-15): zkontrolováno všech
   AJAX endpointů (nonce + capability + sanitizace), admin JS s `innerHTML`
   (escapování/textContent) a nově i AiQueue/SmartIndexing/Status/Pdf/SettingsAjax.
   Žádné problémy nenalezeny, viz `docs/dev-log.md`. Pushnuto na
   `feature/audit-dashboard-redirects` (commit `d471fd6`). **Merge do `main`
   ještě neproběhl** – čeká na pokyn uživatele.

## Hotovo (2026-06-10)

- H1 detekce z renderované stránky (`count_rendered_h1`, retry 3x + cache transient + cookies pro WP Rocket/Wordfence) – ověřeno end-to-end, funguje.
- Schema effective-type (override → kategorie → post-type default dle Rank Math) – `SEOB_Schema_Helper`.
- Inline editor schématu v dashboardu + AJAX uložení.
- Hover tooltipy na ikonách nálezů v dashboardu.
- Progress bar s odhadem zbývajícího času.
- Vizuální oddělení řádků v tabulce výsledků (CSS).

## ✅ Vizuální/UX doladění – 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Po review uživatele (historie scanů + nastavení schémat OK) implementováno:
- Schéma podle typu obsahu/kategorie: nová volba "Výchozí (dle Rank Math…)" +
  tlačítko "Resetovat" (jeden klik vrátí na výchozí chování Rank Math, žádný
  override SEOB). Detaily v `docs/dev-log.md` (záznam "Vizuální/UX doladění...").
- Audit Dashboard: tlačítko "Smazat scan" u historie + Nastavení → "Historie scanů"
  (`history_limit`, default 20) – automatický úklid starých scanů po každém novém.
- Smazán nepoužívaný `includes/ScanRunner.php`. `SEOB_VERSION` 0.1.8 → 0.1.9.

## ✅ Nový modul M14 „Chytrá indexace" – MVP implementováno 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Uživatel dodal zadání M14 v2 (katalogové/filtrované stránky, obor × lokalita,
3-tier model). Tento testovací web nemá žádný katalog firem, takže uživatel
zvolil **generický konfigurovatelný modul** (admin si v Nastavení namapuje
CPT pro detail firmy + taxonomie obor/lokalita/služba – funguje na libovolném
klientském webu).

Implementováno: `SEOB_Settings::SMART_INDEXING`, nové tabulky
`wp_seo_booster_facet_rules/facet_urls/facet_signals` (`SEOB_DB_VERSION` →
`0.4.0`), `SEOB_SmartIndexing_Catalog_Scanner` (analýza detailů firem,
hlavních oborů, obor × lokalita, služba × lokalita → Tier A/B), frontend
integrace s Rank Math (`rank_math/frontend/canonical` + `...robots`, jen mimo
Dry-run), nová admin stránka „Chytrá indexace" s analýzou a tlačítky
Schválit/Noindex, health check, modul `smart-indexing` (vypnutý výchozí).
`SEOB_VERSION` 0.1.9 → 0.2.0. Ověřeno přes wp-cli (migrace, tabulky), `php -l`
bez chyb. Detaily v `docs/dev-log.md` (záznam "Nový modul M14...") a
`docs/modules/smart-indexing.md` (vč. sekce o Crocoblock/JetSmartFilters AJAX
filtrech – tento modul je neovlivní).

**Druhá iterace (odloženo):** GSC skórování (M7), `facet_rules` overridy,
generování landing pages, robots.txt pro tracking parametry.

## ✅ Export PDF reportu – redesign implementováno 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Uživatel: oprava bugu 🏢 v titulku PDF, report nevhodný pro 300+ stránkové
weby (karta pro každou stránku), zhezčení/branding pro klientské schůzky.

Implementováno (`SEOB_VERSION` 0.2.0 → 0.3.0, `SEOB_DB_VERSION` beze změny,
`php -l` bez chyb, ověřeno přes wp-cli – `ReportData::build()` i
`Renderer::render()` proběhnou bez chyby, `site_name` = "RE:Boost" bez
emoji):

- `ReportData::sanitize_text()` – odstraní znaky nad U+FFFF ze `site_name`
  (fix emoji bugu).
- `build()`: top-N nejhorších stránek (`detailed_rows`, limit nastavitelný
  v Export – nastavení → Rozsah reportu, default 12) + `issue_summary`
  (zbytek seskupený podle typu nálezu, s URL pro přílohu). Nové:
  `pages_with_issues_count`, `pages_ok_count`, `remaining_count`,
  `serp_affected_pages`.
- `compute_business_impact()` – orientační odhad CTR uplift / dalších
  návštěv / konverzí / tržeb z volitelných polí na stránce Export reportu
  (návštěvnost, konverzní poměr, hodnota objednávky/leadu).
- Branding: `company.logo_id/logo_url/accent_color` (Export – nastavení →
  Firemní údaje a branding, media uploader pro logo, color picker pro
  accent). `templates/pdf/report.php` kompletně redesignováno (logo v
  záhlaví, accent barva, top-N karty, souhrnná tabulka + příloha URL, sekce
  odhadu dopadu).

Detaily v `docs/dev-log.md` (záznam "Redesign Export PDF reportu") a
`docs/modules/pdf-export.md`.

## ✅ Export PDF reportu – hlavičkový papír + souhrn dopadů + "Vystavil" implementováno 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Navazující zadání (screenshot karet nálezů): logo a název webu na KAŽDÉ
stránce PDF (ne jen titulní), větší odsazení textu nálezu od barevného
levého okraje, konsolidovaná sekce dopadů/přínosů na konci, a sekce
"Vystavil" (kdo nabídku vystavil – jméno, firma, IČO, kontakt, logo).

Implementováno (`SEOB_VERSION` zůstává `0.3.0`, `php -l` bez chyb, ověřeno
přes wp-cli – `Renderer::render()` proběhne bez chyby, PDF ~129 kB):

- `includes/Pdf/Document.php` (nový) – `SEOB_Pdf_Document extends TCPDF`
  s `Header()`/`Footer()` pro opakující se logo, accent linku, název firmy
  a číslo stránky na každé stránce. `PdfRenderer::render()` upraven, aby ho
  použil (vyšší okraje `SetMargins(15, 32, 15)`).
- `includes/Settings.php` + `SettingsAjax::pdf_settings()`: nová pole
  `company.contact_person` a `company.ico`.
- `templates/admin/page-pdf-settings.php`: nová pole "Jméno zpracovatele"
  a "IČO" v sekci "Firemní údaje a branding".
- `templates/pdf/report.php`: odstraněna duplicitní inline tabulka s logem
  (řeší letterhead), `.seob-pdf-issue` padding `8px → 14px`, nová sekce
  "Souhrn dopadů a přínosů zlepšení" (z `issue_summary`), nová sekce
  "Vystavil" na konci (logo, jméno zpracovatele, firma, IČO, kontakt).

Detaily v `docs/dev-log.md` (záznam "PDF report: hlavičkový papír...") a
`docs/modules/pdf-export.md`.

## ✅ Nový modul „Search Console statistiky (Rank Math)" implementováno 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Zadání: "Přidej tedy tu integraci z Rankmathu tech dat (search console, GA4
atd.)", s podmínkou uživatele "nechci skrze Google api" – řešení čte jen
vlastní tabulku Rank Math `wp_rank_math_analytics_gsc` (žádné OAuth na naší
straně). Rozsah: jen Audit Dashboard.

Implementováno (`php -l` bez chyb, ověřeno wp-cli s dočasnou testovací
tabulkou dle schématu Rank Math – normalizace URL i `null` fallback fungují):

- `includes/GscInsights/GscInsights.php` (`SEOB_Gsc_Insights`) –
  `is_table_available()`, `get_summary()`, `attach_metrics()`.
- Nový modul `gsc-insights` (Nastavení → Moduly, výchozí zapnuto, depends on
  `audit`).
- Audit Dashboard: 4 nové sloupce (Zobrazení/Kliky/CTR/Pozice) + notice s
  návodem na připojení Rank Math Analytics, skryté dokud nejsou data.
- Health check `gsc_checks()` ve „Stav systému“.

Detaily v `docs/dev-log.md` (záznam "Nový modul Search Console statistiky...")
a `docs/modules/gsc-insights.md`.

## ✅ Nový modul „AI schvalovací fronta" implementováno 2026-06-12 (NEOTESTOVÁNO V PROHLÍŽEČI)

Dle master zadání (sekce 2/5): AI nikdy neukládá automaticky, návrhy
(title/description/alt texty) jdou do `seo_booster_ai_queue` (status
`pending`), zapsání do skutečných polí až po schválení adminem. API klíč
šifrovaný v DB (AES-256-CBC). Obecný OpenAI-compatible adaptér (doporučení:
free Gemini/Groq endpointy).

Implementováno: `includes/AiQueue/*` (Crypt, ProviderInterface,
OpenAiCompatibleProvider, Repository, PromptBuilder, Ajax), nový modul
`ai-queue` (výchozí vypnuto, `depends_on: ['audit']`), sekce „AI asistent“ v
Nastavení, nová stránka „AI fronta“ (`seob-ai-queue`), tlačítka „Navrhnout
pomocí AI“ / „Navrhnout alt texty obrázků“ v Audit Dashboardu, health check
`ai_queue_checks()`. Nové PHPUnit testy `CryptTest` + `PromptBuilderTest` –
**28 testů, 84 assertions, OK**. `php -l` bez chyb. wp-cli smoke test
(settings round-trip, queue insert/approve/reject na reálném postu) ověřen,
testovací data po sobě uklizena. Detaily v `docs/dev-log.md` (záznam "Nový
modul AI schvalovací fronta...") a `docs/modules/ai-queue.md`.

Pushnuto 2026-06-15 (commit `d471fd6`, viz sekce "ZAČNI ZDE" výše).

## Další krok

**D.** AI schvalovací fronta – **vyžaduje test v prohlížeči** (uživatel má
zvolit free Gemini/Groq API klíč dle `docs/modules/ai-queue.md`):
1. Nastavení → Moduly: zapnout „AI schvalovací fronta“, sekce „AI asistent“:
   vyplnit endpoint/model/API klíč, uložit (ověřit, že se objeví placeholder
   „•••• (vyplňte jen pro změnu)“ po přenačtení).
2. Audit Dashboard → „Opravit“ u nějaké stránky: tlačítka „Navrhnout pomocí
   AI“ u Title/Description a (pokud má stránka nález „Obrázky bez alt
   textu“) „Navrhnout alt texty obrázků“ – ověřit, že se zobrazí stavová
   zpráva s odkazem do AI fronty a **nezmění se** uložená hodnota.
3. SEO Booster → AI fronta: ověřit zobrazení návrhů (Pending), tlačítka
   Schválit (zapíše hodnotu, přepne do Schváleno) a Zamítnout (jen přepne).
4. Stav systému: ověřit health check „AI schvalovací fronta“ (critical bez
   klíče, warning s pending návrhy, good jinak).

**C.** ✅ Composer/PHPUnit scaffolding HOTOVO a **OTESTOVÁNO** – uživatel
nainstaloval Composer (Composer-Setup.exe), narazili jsme na 2 problémy s
lokálním PHP (oprava níže v "Pomůcky"), po opravě `composer install &&
composer test` proběhlo: **20 testů, 55 assertions, OK**. Detaily v
`docs/testing.md` a `docs/dev-log.md` (záznam "Composer + PHPUnit
scaffolding...").

**B.** ✅ **OTESTOVÁNO** (2026-06-12) – sloupce Zobrazení/Kliky/CTR/Pozice v
Audit Dashboardu zobrazeny a vypadají dobře (po opravě `table-layout`, viz
dev-log). Doplněna i nová sekce "Klíčová slova ve vyhledávání (28 dní)" v
edit panelu (`attach_queries()`), uživatel potvrdil OK. Health check
`gsc-insights` ve „Stav systému" zatím nebyl explicitně otestován v
prohlížeči (nízká priorita, stejná logika jako ostatní health checky).

**A.** ✅ Redesign Exportu PDF **OTESTOVÁNO** (2026-06-12) – vygenerován
testovací PDF (logo, accent `#2bd1a2`, IČO, kontaktní osoba) a uživatel
potvrdil, že vizuálně vyhovuje (logo ostré, odsazení karet, sekce "Souhrn
nálezů podle typu", "Souhrn dopadů a přínosů zlepšení" a "Vystavil" v
pořádku).

**0.** ✅ **OTESTOVÁNO** (2026-06-12) – uživatel potvrdil, že tlačítko
„Zapnout"/„Vypnout" modulů ve „Stav systému" i zobrazení/skrytí položek
v admin menu (vč. „Chytrá indexace") funguje OK.

**1.** Otevřít wp-admin → SEO Booster → Nastavení → sekce "Výchozí schéma podle typu obsahu/kategorie":
ověřit, že řádky bez vlastního nastavení ukazují "Výchozí (dle Rank Math: …)", tlačítko "Resetovat"
funguje (zruší override a uloží), a "Uložit vše" nepřepíše nic, co má zůstat na výchozím chování.

**2.** Otevřít wp-admin → SEO Booster → Audit Dashboard: vyzkoušet tlačítko "Smazat scan" u historie
(smaže vybraný scan, dropdown i výsledky se přenačtou). V Nastavení → Audit Dashboard zkontrolovat
nové pole "Historie scanů" (uložení hodnoty, popis).

**3.** "Společná infrastruktura" – jádro HOTOVO (viz sekce výše). Otevřít wp-admin → SEO Booster →
**Stav systému**, ověřit vykreslení tabulky modulů, health checků a sparkline grafů, a Nástroje →
Stav webu (nové testy SEO Booster Pro). Zvážit opravu zaseklého scanu #8 (viz sekce výše).

✅ Mimo tento plugin: UTF-8 BOM na začátku `wp-content/plugins/emailing-calculator/emailing-calculator.php`
opraven a commitnut (commit `8f1e166` v repu emailing-calculator) – ověřeno, admin-ajax odpovědi už nemají
přebytečné bajty na začátku. **Tento commit zatím není pushnutý** – uživatel byl dotázán dřív, zatím bez odpovědi.

## Pomůcky pro wp-cli (zjištěno tuto session)

- `wp-cli.phar` je v `C:\Users\PC\Downloads\wp-cli.phar` (NE v rootu projektu).
- PHP binárka: `C:\Users\PC\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe`
- php.ini s mysqli: `C:\Users\PC\AppData\Roaming\Local\run\tlTd6oucj\conf\php\php.ini` (POZOR: konkrétní `run\<ID>`
  složka se může mezi restarty Local měnit – pokud cesta nesedí, najít aktuální přes
  `find /c/Users/PC/AppData/Roaming/Local/run -maxdepth 3 -path "*conf/php/php.ini"`).
- Spouštět z `C:\Users\PC\Local Sites\reboost-test\app\public` s `--path="."`, např.:
  ```
  "<php.exe>" -c "<php.ini>" /c/Users/PC/Downloads/wp-cli.phar option get siteurl --path="."
  ```

## Pomůcky pro composer (zjištěno tuto session)

- Composer 2.10.1 nainstalován přes Composer-Setup.exe, používá stejnou PHP binárku jako wp-cli
  (`C:\Users\PC\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe`), ale s
  jejím VLASTNÍM `php.ini` v `bin\win64\php.ini` (jiný soubor než ten pro wp-cli výše).
- Po instalaci nemusí `composer` jít hned spustit v už otevřeném PowerShell okně (PATH se
  aktualizuje jen v registru, ne v běžící session). Oprava bez restartu terminálu:
  ```
  $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("PATH","User")
  ```
- V `bin\win64\php.ini` bylo potřeba opravit dvě věci, aby `composer install` prošel:
  1. **SSL "certificate verify failed"** – doplnit/odkomentovat v sekcích `[curl]` a `[openssl]`:
     ```
     curl.cainfo = "C:\Users\PC\Local Sites\reboost-test\app\public\wp-includes\certificates\ca-bundle.crt"
     openssl.cafile = "C:\Users\PC\Local Sites\reboost-test\app\public\wp-includes\certificates\ca-bundle.crt"
     ```
     (reuse WordPress vlastního CA bundle).
  2. **"zip extension and unzip/7z commands are both missing"** – odkomentovat `extension=zip`
     (řádek ~963), `php_zip.dll` už v instalaci existoval, jen byl vypnutý.
- Po obou opravách: `composer install --no-interaction` doinstaloval 32 dev balíčků do
  `vendor-dev/` (gitignored, vlastní `vendor-dir` aby se nemíchalo s ručně vendorovaným
  `vendor/tcpdf` + `vendor/plugin-update-checker`), a `composer test` proběhl
  **20 testů, 55 assertions, OK**.
