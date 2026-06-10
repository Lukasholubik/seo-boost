# SEO Booster Pro – Vývojový deník

> **Tento soubor je první co číst.** Každá změna přibyde sem – co bylo uděláno, proč a kde v kódu.
> Nové záznamy přidávej **na začátek** (nejnovější nahoře).

---

## Záznamy

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
