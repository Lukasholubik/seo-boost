# Modul: PageSpeed Insights (Lighthouse)

## Proč modul existuje

Doplňuje Audit Dashboard o reálná data z Google Lighthouse (Performance,
Accessibility, Best Practices, SEO) bez nutnosti provozovat headless Chrome
na serveru – využívá se veřejné Google PageSpeed Insights (PSI) API v5.

Protože testování celého webu stránku po stránce by bylo extrémně pomalé
(PSI dotaz trvá ~15–40 s), modul místo toho:

1. Pro každý veřejný typ obsahu (post, page, produkt...) s alespoň jednou
   publikovanou položkou vybere **5 náhodných publikovaných stránek**.
2. Pro každou z nich zavolá PSI **pro mobil i desktop**.
3. Výsledky shrne **podle typu obsahu** – průměrná skóre + nejčastější SEO
   nálezy s popisem.

Modul `pagespeed` je **výchozí vypnutý**, dokud admin nevyplní API klíč
v Nastavení.

## Jak získat bezplatný API klíč (podrobný návod)

1. Otevřete [Google Cloud Console](https://console.cloud.google.com/) a
   přihlaste se Google účtem.
2. **Projekt**: nahoře v liště vedle loga je výběr projektu (např. „My
   First Project“). Buď použijte existující projekt, nebo vytvořte nový
   („New Project“) – na bezplatném tieru PSI API nehraje roli, ke kterému
   projektu klíč patří.
3. V levém menu otevřete **„APIs & Services“ → „Library“**.
4. Do vyhledávacího pole zadejte **„PageSpeed Insights API“** (přesný
   název), klikněte na výsledek a poté na tlačítko **„Enable“** (Povolit).
   Pokud je API už povolené, tlačítko bude zobrazovat „Manage“ – v tom
   případě je to v pořádku, jen pokračujte dál.
5. V levém menu přejděte na **„APIs & Services“ → „Credentials“**.
6. Klikněte **„+ Create credentials“ → „API key“**. Otevře se panel pro
   vytvoření klíče:
   - **Name**: libovolný popisný název, např. „Lighthouse“ nebo „PageSpeed
     Insights – reboost.cz“ (pomáhá rozlišit klíče, pokud jich máte víc).
   - **„Select API restrictions“**: klikněte na dropdown a zaškrtněte
     **pouze „PageSpeed Insights API“**. Tím klíč nepůjde zneužít pro jiné
     Google API (Maps, Gemini apod.), i kdyby unikl.
   - **„Application restrictions“**: nechte na **„None“** – klíč se volá
     ze serveru (PHP `wp_remote_get`), ne z prohlížeče, takže omezení na
     weby/IP adresy není potřeba (a u serverového volání by často ani
     nefungovalo).
   - Checkbox **„Authenticate API calls through a service account“**
     nezaškrtávejte – týká se jiných (Vertex/Gemini) API, PageSpeed
     Insights ho nepotřebuje.
   - Klikněte **„Create“**.
7. Zobrazí se vygenerovaný klíč (řetězec ve tvaru `AIzaSy...`). Zkopírujte
   ho – uvidíte ho i později v seznamu „API Keys“ na stránce Credentials.
8. V administraci webu otevřete **SEO Booster Pro → Nastavení → sekce
   „PageSpeed Insights (Lighthouse)“**:
   - Zaškrtněte **„Povolit modul“**.
   - Do pole **„API klíč“** vložte zkopírovaný klíč.
   - Klikněte **„Uložit“** (tlačítko v horní liště Nastavení). Klíč se
     uloží šifrovaně (AES-256-CBC) a pole se po přenačtení zobrazí jako
     `••••••••` – to je očekávané, klíč je uložený.
9. Přejděte na **SEO Booster Pro → PageSpeed Insights** a klikněte
   **„Spustit analýzu“**.

**Limity bezplatného tieru**: 25 000 požadavků/den, 240 požadavků/minutu.
Proto se dávka při analýze zpracovává po jedné položce (`process_batch(...,
1)`), aby se předešlo zahlcení a PHP timeoutu.

**Bezpečnost**: API klíč omezený jen na „PageSpeed Insights API“ má velmi
nízké riziko zneužití – nejhorší možný dopad při úniku je vyčerpání denního
limitu (25 000 dotazů). Pokud by se to stalo, v Google Cloud Console →
Credentials klíč jednoduše smažte a vytvořte nový (kroky 5–8 výše).

## Jak to zapnout

1. SEO Booster Pro → Nastavení → Moduly: zaškrtnout „PageSpeed Insights
   (Lighthouse skóre a SEO doporučení pro vzorek stránek)“.
2. Sekce „PageSpeed Insights (Lighthouse)“ ve stejné stránce: vyplnit API
   klíč (uloží se šifrovaně, stejně jako u AI asistenta).
3. Uložit (tlačítko v horní liště Nastavení).

## Jak analýza funguje

- Stránka **SEO Booster → PageSpeed Insights** (`seob-pagespeed`) má
  tlačítko „Spustit analýzu“.
- Po spuštění (`seob_psi_start`) se založí běh (`seo_booster_psi_runs`) a
  fronta položek `{post, strategie}` (5 postů × 2 strategie za každý typ
  obsahu).
- Fronta se zpracovává po jedné položce (`seob_psi_batch`) s progress barem,
  dokud není prázdná.
- Po dokončení (`finalize_scan`) se pro každou kombinaci (typ obsahu,
  strategie) spočítají průměrná skóre, nejčastější SEO nálezy (top 10) a
  vzorek testovaných stránek – vše se uloží do `seo_booster_psi_summary`.
- Průměrné SEO skóre (přes všechny typy obsahu) se zaznamenává do
  `seo_booster_metrics` jako `pagespeed.seo_avg_mobile` /
  `pagespeed.seo_avg_desktop` pro trendy v „Stav systému“.
- Historie běhů se udržuje na posledních 10 dokončených (`prune_history()`).

## Architektura (pro vývojáře)

`includes/PageSpeed/`:

- `Client.php` (`SEOB_PageSpeed_Client`) – `analyze(url, strategy, api_key)`
  zavolá `https://www.googleapis.com/pagespeedonline/v5/runPagespeed`
  (kategorie performance/accessibility/best-practices/seo, timeout 60 s) a
  vrátí `{performance_score, accessibility_score, best_practices_score,
  seo_score, issues}` (0–100, `issues` = SEO audity se skóre < 1 z
  `categories.seo.auditRefs`). `parse_response()` je čistá funkce – kryto
  PHPUnit testy.
- `ScanRunner.php` (`SEOB_PageSpeed_ScanRunner`) – `start_scan()`,
  `process_batch()`, `get_latest_results()`, `delete_run()`. Agregace
  (`aggregate_group()`) je čistá funkce – kryto PHPUnit testy.
- `Ajax.php` (`SEOB_PageSpeed_Ajax`) – AJAX endpointy (`seob_psi_start`,
  `seob_psi_batch`, `seob_psi_results`, `seob_psi_delete`), stejný vzor jako
  ostatní moduly (`check_ajax_referer` + `current_user_can`).

Tabulky:

- `seo_booster_psi_runs` – běhy analýzy (`items_total`, `items_done`,
  `status`).
- `seo_booster_psi_results` – výsledek pro každou položku (post × strategie):
  4 skóre, `issues_json`, případná `error`.
- `seo_booster_psi_summary` – souhrn za (typ obsahu, strategie): průměrná
  skóre, `common_issues_json` (top nálezy s počtem výskytů), `sample_size`,
  `sample_object_ids_json`.

API klíč se šifruje stejnou třídou jako u AI asistenta
(`SEOB_AiQueue_Crypt::encrypt()/decrypt()` – AES-256-CBC, klíč odvozený z
`wp_salt('auth')`).

## Health check

`SEOB_Health_Checks::pagespeed_checks()`:

- **Kritická**, pokud je modul zapnutý, ale chybí API klíč – odkaz do
  Nastavení.
- **OK**, pokud zatím neproběhla žádná analýza – odkaz na spuštění.
- **Varování**, pokud poslední analýza má průměrné SEO skóre < 80, jinak
  **OK** – odkaz na stránku modulu.

## Co modul (zatím) NEdělá

- Velikost vzorku (5 stránek) a strategie (mobil + desktop) nejsou
  v Nastavení konfigurovatelné – jsou natvrdo v `SEOB_PageSpeed_ScanRunner`.
- Žádné automatické/plánované spouštění – analýza se spouští ručně.
- Žádné PDF reporty s PageSpeed daty.
