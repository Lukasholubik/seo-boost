# Testování (PHPUnit)

## Proč jen unit testy bez WordPressu

Plugin běží v `SEOB_` třídách bez namespace (rozhodnutí v `docs/STATE.md`).
Plnohodnotné WP-integrační testy (`wp-phpunit` + testovací DB) jsou těžké na
nastavení a composer v tomto dev prostředí nemá přístup k internetu. Místo
toho testujeme **čistou logiku** (statické metody bez vedlejších efektů) –
WP funkce, které je potřeba, se mockují přes [Brain Monkey](https://brain-wp.github.io/BrainMonkey/).

## Nastavení (na stroji s internetem/composerem)

```bash
cd wp-content/plugins/seo-boost
composer install
composer test
# nebo přímo:
vendor-dev/bin/phpunit
```

Composer dev závislosti (PHPUnit, Brain Monkey) se instalují do **`vendor-dev/`**
(vlastní `vendor-dir` v `composer.json`), aby se nemíchaly s ručně vendorovanými
knihovnami v `vendor/` (TCPDF, plugin-update-checker), které se commitují do gitu.
`vendor-dev/` je v `.gitignore`.

## Struktura

- `tests/bootstrap.php` – načte composer autoload, definuje `ABSPATH` a
  `SEOB_PLUGIN_DIR` (soubory pluginu mají na začátku
  `if ( ! defined( 'ABSPATH' ) ) exit;`).
- `tests/TestCase.php` – základní třída, kolem každého testu
  zapne/uklidí Brain Monkey (`Monkey\setUp()`/`Monkey\tearDown()`).
- `tests/Unit/` – testy, struktura kopíruje `includes/`.
- `tests/fixtures/` – malé testovací soubory (např. PNG fixtures pro
  testy práce s logem v PDF).

## Co je (zatím) otestováno

- `SEOB_Pdf_Report_Data::issue_labels()` a `logo_dimensions_mm()` (rozměry
  loga v PDF – nikdy neroztáhne nad přirozenou velikost).
- `SEOB_Pdf_Document::hex_to_rgb()` (převod accent barvy, fallback na výchozí
  barvu při neplatném vstupu).
- `SEOB_Module_Manager::MODULES` – integrita registru modulů (`depends_on`
  odkazuje jen na existující moduly, povinné klíče) a `is_rank_math_active()`.
- `SEOB_Gsc_Insights::normalize_path()` – normalizace URL z Rank Math GSC
  tabulky pro porovnání s URL z auditu (různé scheme/host/www).

## Jak přidávat další testy

Vybírej metody, které jsou **čistá logika** (statické, bez `global $wpdb`,
bez zápisu do DB/options) – ty se testují bez Brain Monkey. Pro metody
volající WP funkce (`get_option`, `wp_parse_url`, …) použij
`Brain\Monkey\Functions\when()` / `expect()` v testu, který extenduje
`SeoBoost\Tests\TestCase`. Metody s `global $wpdb` (např.
`SEOB_Gsc_Insights::attach_metrics()`) zatím nejsou testované – vyžadovaly by
mock `$wpdb`, což je další krok (např. přes `Brain\Monkey\Functions` + vlastní
stub třídu `wpdb`).
