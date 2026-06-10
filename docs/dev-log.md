# SEO Booster Pro – Vývojový deník

> **Tento soubor je první co číst.** Každá změna přibyde sem – co bylo uděláno, proč a kde v kódu.
> Nové záznamy přidávej **na začátek** (nejnovější nahoře).

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
