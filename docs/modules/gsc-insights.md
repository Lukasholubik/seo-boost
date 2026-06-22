# Modul: Search Console statistiky (Rank Math)

## Proč modul existuje

Audit Dashboard hodnotí technické SEO (title, description, schema...), ale
neříká, jak si stránky reálně vedou ve vyhledávání. Uživatel chtěl tato data
zapojit, ale **bez vlastního napojení na Google API/OAuth** – proto modul
pouze čte data, která si Rank Math (i ve free verzi) sám uloží do vlastní
tabulky po připojení svého modulu Analytics.

## Jak to zapnout

1. V Rank Math (Obecná nastavení → Search Console / modul Analytics)
   připojte Google účet a vyberte property webu – Rank Math si poté sám
   pravidelně stahuje data ze Search Console do tabulky
   `wp_rank_math_analytics_gsc`.
2. V SEO Booster Pro → Nastavení → Moduly je „Search Console statistiky
   (Rank Math)“ zapnutá výchozí (`modules.gsc-insights`). Vyžaduje aktivní
   modul Audit Dashboard.
3. Po prvním stažení dat Rank Mathem se v Audit Dashboardu zobrazí 4 nové
   sloupce: **Zobrazení, Kliky, CTR, Pozice** (souhrn za posledních 28 dní).
4. Po rozkliknutí tlačítka „Opravit“ u konkrétní stránky se zobrazí sekce
   **„Klíčová slova ve vyhledávání (28 dní)“** – tabulka až 10 dotazů, na
   které stránka cílila, s pozicí, kliky a zobrazeními (seřazeno podle
   kliků).

Dokud Rank Math nemá tabulku `wp_rank_math_analytics_gsc` s daty za
posledních 28 dní, zobrazí se v Audit Dashboardu informační notice s
odkazem na nastavení Rank Math a sloupce Search Console jsou skryté – nic
dalšího se nezmění.

## Jak to funguje technicky

`includes/GscInsights/GscInsights.php` (`SEOB_Gsc_Insights`):

- `is_table_available()` – ověří přes `information_schema.TABLES`, zda
  tabulka `{prefix}rank_math_analytics_gsc` existuje (Rank Math ji vytvoří
  jen po prvním připojení Analytics modulu).
- `get_summary()` – celkový souhrn (zobrazení, kliky, CTR, průměrná pozice)
  za posledních 28 dní, použito v health checku.
- `attach_metrics(&$rows)` – jedním dotazem agreguje
  `SUM(clicks)/SUM(impressions)/AVG(position) GROUP BY page` za 28 dní a
  doplní každému řádku auditu klíč `gsc` (metriky nebo `null`, pokud pro
  danou URL Search Console data nemá).
- `attach_queries(&$rows, $limit = 10)` – jedním dotazem agreguje
  `SUM(clicks)/SUM(impressions)/AVG(position) GROUP BY page, query` za
  28 dní, seřadí podle kliků a doplní každému řádku auditu klíč
  `gsc_queries` (max `$limit` dotazů, nebo `null`/prázdné pole, pokud pro
  danou URL data nejsou).

**Normalizace URL:** sloupec `page` v tabulce Rank Math obsahuje plnou URL
přesně tak, jak ji vrátí Search Console API (může mít jiné schéma/host než
`home_url()`, např. `https://www.…` vs. `http://…`). Proto se porovnává jen
cesta (`wp_parse_url($url, PHP_URL_PATH)`, bez koncového lomítka).

## Integrace do Audit Dashboardu

- `SEOB_Audit_Ajax::scan_results()` doplní do odpovědi `row.gsc` u každého
  řádku a `gsc_available` (bool).
- `assets/admin/js/audit-dashboard.js` vyplní nové buňky
  (`.seob-col-gsc-impressions/clicks/ctr/position`, „–“ pokud `gsc` je
  `null`) a podle `gsc_available` skryje/zobrazí sloupce (`.seob-gsc-hidden`
  na `.seob-audit-table`) a notice `#seob-gsc-notice`.
- V edit panelu (`templates/admin/page-dashboard.php`, `.seob-gsc-queries`)
  se z `row.gsc_queries` vyplní tabulka klíčových slov; pokud je `null`,
  sekce se skryje celá (modul/tabulka nedostupná), pokud je prázdné pole,
  zobrazí se text „nemáme data“.

## Co modul (zatím) NEdělá

- Žádné GA4 ani jiná data – jen Search Console přes Rank Math.
- Nezasahuje do PDF reportu ani nemá samostatnou stránku (mimo rozsah
  aktuálního zadání, možný budoucí krok).
- Nepíše do tabulky Rank Math, jen čte.

## Health check

`SEOB_Health_Checks::gsc_checks()`:

- **Kritická**, pokud Rank Math není aktivní.
- **Varování**, pokud Rank Math běží, ale tabulka `rank_math_analytics_gsc`
  neexistuje nebo nemá data za posledních 28 dní – akce „Připojit Search
  Console v Rank Math“.
- **OK**, se souhrnem (zobrazení/kliky/CTR/pozice za 28 dní), pokud data
  existují.

Modul závisí na **Audit Dashboardu** (`depends_on: ['audit']`).
