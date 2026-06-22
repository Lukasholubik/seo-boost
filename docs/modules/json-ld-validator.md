# M3 – JSON-LD Validátor + detektor duplicit

**Verze:** 0.8.0  
**Stav:** Hotovo

## Proč tento modul existuje

Rank Math generuje JSON-LD schémata, ale nevaliduje je. Nevalidní schéma (chybějící povinná vlastnost jako `headline` u Article nebo `itemListElement` u BreadcrumbList) způsobí, že Google daný rich snippet vůbec nezobrazí. Druhý běžný problém: téma + Rank Math + page builder vloží každý vlastní Article schéma – Google může při detekci konfliktu ignorovat všechny.

Zdroj: [Google – Understand how structured data works](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data), schema.org specifikace.

## Co se zlepší a jak to poznám

- **Počet stránek s nevalidním schématem → 0** – ověřit v záložce „Vylepšení" v Google Search Console (sekce Rich Results)
- **Počet stránek s duplicitním schématem → 0** – detekuje modul, opravit deaktivací jednoho zdroje
- **Počet rich results v GSC poroste** – měřit 4–8 týdnů po opravě

## Jak skenování funguje

Scan probíhá **na serveru na pozadí** (WP-Cron), nezávisle na prohlížeči:

1. Kliknete **Spustit scan** → server sestaví seznam URL (max 50)
2. WP-Cron každé 3 sekundy zpracuje jednu URL:
   - stáhne renderovaný HTML (`wp_remote_get`)
   - extrahuje všechny `<script type="application/ld+json">` bloky
   - validuje schémata a detekuje duplicity
3. Admin stránka každé 2 sekundy polluje stav a zobrazuje progress bar
4. Po dokončení se stránka automaticky přenačte a zobrazí výsledky
5. Výsledek se uloží do archivu (posledních 10 scanů)

**Proč 1 URL / 3 sekundy?** Scan stahuje stránky loopback HTTP požadavky (volá sám sebe). Lokální dev server (Local by Flywheel) má malý PHP-FPM worker pool – příliš mnoho současných požadavků server zahltí. Tempo 1 URL / 3 sekundy je dostatečně rychlé (50 URL ≈ 2,5 minuty) a přitom server nezatěžuje.

## Co dělat s nálezy

### Nevalidní schéma (červené ❌)

Klikněte na URL v tabulce výsledků – detail zobrazí konkrétní chybu, např.:

> `Article: chybí povinná vlastnost "headline"`

**Možnosti opravy:**
- Otevřete příslušný příspěvek/stránku v editoru (odkaz přímo z tabulky)
- Zkontrolujte nastavení Rank Math v metaboxu daného příspěvku (záložka Schema)
- Pokud schéma vkládá téma nebo page builder, deaktivujte jeho schema výstup

Nejčastější chyby:

| Chyba | Příčina | Oprava |
|-------|---------|--------|
| Article: chybí `headline` | Rank Math nepřevzal titulek | Nastavit titulek v Rank Math metaboxu |
| BreadcrumbList: prázdné pole | Navigace nevrátila položky | Zkontrolovat nastavení drobečkové navigace v Rank Math |
| FAQPage: prázdné `mainEntity` | FAQ blok bez položek | Přidat otázky do FAQ bloku |
| Chyba parsování JSON | Syntax chyba ve vlastním kódu schématu | Opravit JSON ve vlastním poli schema |

### Duplicitní schéma (oranžové ⚠)

Stejný typ schématu vložen vícekrát na jedné stránce:

**Přesná duplicita (❌):** Stejný obsah 2×. Jeden zdroj vypnout zcela – zjistíte který:
1. Ve výsledcích vidíte URL s duplicitou
2. Otevřete stránku → Ctrl+U (zdrojový kód) → hledejte `application/ld+json`
3. Zjistěte, kdo co generuje (Rank Math, téma, plugin)
4. V Rank Math: **Nastavení → Titulky a meta → Globální nastavení → Schema** – vypněte typy kolidující s tématem

**Stejný typ, různý obsah (⚠):** Může být záměrné (více autorů Article), ale Google může preferovat jen jedno. Zvažte konsolidaci.

### Stránky bez schématu

Pokud žádnou chybu nenajdete ale stránka nemá rich snippety, zkontrolujte:
- Stránka vůbec nemá JSON-LD → v Rank Math nastavit schema pro daný typ příspěvku
- Schema je validní ale Google ho ještě neindexoval → počkat 1–4 týdny po opravě

## Co lze konfigurovat

### Rozsah scanu

Ve výchozím nastavení scan prochází max 50 URL (homepage + naposledy upravené příspěvky všech veřejných typů). Limit lze upravit v `SEOB_JsonLd_ScanRunner::MAX_URLS`.

### Automatické spuštění scanu

Scan lze naplánovat jako pravidelnou úlohu – přidat do `wp-config.php` nebo functions.php:

```php
// Spustit scan každý pondělí
add_action('init', function() {
    if (!wp_next_scheduled('seob_weekly_jsonld_scan')) {
        wp_schedule_event(strtotime('next monday'), 'weekly', 'seob_weekly_jsonld_scan');
    }
});
add_action('seob_weekly_jsonld_scan', function() {
    SEOB_JsonLd_ScanRunner::start(50);
});
```

### Validátor jedné URL

Na spodku admin stránky je sekce **Rychlý test URL** – zadejte libovolnou URL a okamžitě uvidíte výsledek validace (bez spuštění celého scanu). Užitečné pro ověření konkrétní stránky po opravě.

## Archiv scanů

Posledních 10 dokončených scanů je uloženo v databázi (`wp_options`, klíč `seob_jsonld_scan_archive`). Přepínáte mezi nimi tlačítkem **Zobrazit** v tabulce archivů. URL-level detaily jsou uloženy jako transienty po dobu 24 hodin – po té době se smažou (archiv summary zůstane, ale detail URL nebude dostupný).

## Jak to monitorovat

- **SEO Booster → JSON-LD Validátor**: přehledová tabulka s barevným stavem každé URL
- **Metriky** (`seo_booster_metrics`): `invalid_schemas`, `duplicate_schemas`, `total_schemas`
- **Frekvence**: spouštět alespoň 1× týdně nebo po větší změně téma/pluginů

## Health check

| Check | Stav | Popis |
|-------|------|-------|
| `json_ld_self_test` | critical | Interní validátor vrátil chybu – PHP problém |
| `json_ld_last_scan` | warning | Poslední scan je starší než 7 dní |
| `json_ld_last_scan` | critical | Nikdy neproběhl scan |
| `json_ld_invalid` | critical | Nalezeny stránky s nevalidním schématem |
| `json_ld_duplicates` | warning | Nalezeny stránky s duplicitním schématem |

## Soubory

| Soubor | Popis |
|--------|-------|
| `includes/JsonLd/Validator.php` | Extrakce, validace, detekce duplicit, self-test |
| `includes/JsonLd/PageScanner.php` | Scan jedné URL přes HTTP, sestavení seznamu URL |
| `includes/JsonLd/ScanRunner.php` | WP-Cron dávkové zpracování, archiv výsledků |
| `includes/JsonLd/Ajax.php` | AJAX endpointy pro admin UI |
| `templates/admin/page-json-ld.php` | Admin stránka: progress, archiv, výsledky, validátor |
| `assets/admin/js/json-ld.js` | Polling, progress bar, validátor jedné URL |
| `tests/Unit/JsonLd/ValidatorTest.php` | 21 unit testů |

## AJAX akce

| Akce | Popis |
|------|-------|
| `seob_json_ld_start_scan` | Spustí nový scan na pozadí |
| `seob_json_ld_cancel_scan` | Zruší probíhající scan |
| `seob_json_ld_scan_status` | Vrátí aktuální stav scanu (pro polling) |
| `seob_json_ld_get_history` | Vrátí archiv posledních 10 scanů |
| `seob_json_ld_get_results` | Vrátí URL-level detail pro konkrétní scan_id |
| `seob_json_ld_scan_url` | Skenuje a validuje jednu konkrétní URL |

## Logika validace

### Extrakce

Načte HTML stránky přes `wp_remote_get` (renderovaný výstup – vidí i schémata vložená JavaScriptem při server-side renderu). Zvládá: samostatné objekty, `@graph` pole (rozkládá na položky), chybný JSON (označí jako parse_error).

### Povinné vlastnosti

| Typ | Povinné vlastnosti |
|-----|-------------------|
| Article, BlogPosting | `headline` |
| NewsArticle | `headline`, `datePublished` |
| Product | `name` |
| Organization, LocalBusiness | `name` |
| BreadcrumbList | `itemListElement` (neprázdné pole) |
| FAQPage | `mainEntity` (neprázdné pole) |
| HowTo | `name`, `step` |
| Event | `name`, `startDate` |
| Recipe | `name`, `recipeIngredient`, `recipeInstructions` |
| VideoObject | `name`, `description`, `thumbnailUrl`, `uploadDate` |

### Detekce duplicit

1. Schémata se seskupí podle `@type`
2. Pokud stejný typ existuje 2×+, porovnají se MD5 otisky
3. Stejný otisk = přesná duplicita (❌ blokuje rich snippety), různý otisk = stejný typ jiný obsah (⚠)

## Unit testy

Soubor: `tests/Unit/JsonLd/ValidatorTest.php` (21 testů) – pokrývají extrakci, validaci, detekci duplicit, self-test a zkrácení typů.
