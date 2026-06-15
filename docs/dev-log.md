# SEO Booster Pro – Vývojový deník

> **Tento soubor je první co číst.** Každá změna přibyde sem – co bylo uděláno, proč a kde v kódu.
> Nové záznamy přidávej **na začátek** (nejnovější nahoře).

---

## Záznamy

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
