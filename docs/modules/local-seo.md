# M11 – Local SEO (CZ)

**Verze:** 0.7.0  
**Stav:** Hotovo

## Přehled

Modul Local SEO vkládá strukturovaná data `LocalBusiness` (JSON-LD) do `<head>` stránek. Je navržen specificky pro české prostředí – obsahuje pole pro IČO, DIČ a plně podporuje otevírací dobu, GPS souřadnice a NAP scanner.

## Soubory

| Soubor | Popis |
|--------|-------|
| `includes/LocalSeo/Frontend.php` | Výstup JSON-LD, detekce konfliktu, seznam typů podnikání |
| `includes/LocalSeo/Ajax.php` | AJAX: uložení nastavení, náhled JSON-LD, NAP scan |
| `templates/admin/page-local-seo.php` | Admin stránka s formulářem, náhledem a dokumentací |
| `assets/admin/js/local-seo.js` | JavaScript pro formulář, mediální výběr, NAP scan |
| `tests/Unit/LocalSeo/FrontendTest.php` | 10 unit testů |

## Nastavení

Uloženo v option klíči `seob_local_seo_settings`.

### Dostupná pole

| Pole | Popis |
|------|-------|
| `business_name` | Název firmy (povinné pro výstup) |
| `business_type` | Podtyp LocalBusiness ze schema.org |
| `description` | Popis firmy |
| `phone` | Telefon v preferovaném formátu (+420 123 456 789) |
| `email` | E-mail |
| `address_street` | Ulice a číslo |
| `address_city` | Město |
| `address_zip` | PSČ |
| `address_country` | Stát (ISO 3166-1, výchozí CZ) |
| `lat` / `lng` | GPS souřadnice |
| `ico` | IČO (vkládá se jako PropertyValue) |
| `dic` | DIČ (vkládá se jako PropertyValue) |
| `price_range` | Cenová kategorie ($, $$, $$$, $$$$) |
| `image_url` / `image_id` | Logo nebo obrázek firmy |
| `output_on` | Kde vkládat: `homepage`, `all`, `contact` |
| `contact_page_id` | ID kontaktní stránky (pro `output_on: contact`) |
| `opening_hours` | Otevírací doba (Mo-Su, open/close/closed) |

## Výstup JSON-LD

```json
{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "name": "ACME s.r.o.",
  "url": "https://acme.cz/",
  "telephone": "+420 123 456 789",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Náměstí Republiky 1",
    "addressLocality": "Praha",
    "postalCode": "110 00",
    "addressCountry": "CZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 50.075200,
    "longitude": 14.437800
  },
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": "https://schema.org/Monday",
      "opens": "09:00",
      "closes": "17:00"
    }
  ],
  "identifier": [
    { "@type": "PropertyValue", "name": "IČO", "value": "12345678" },
    { "@type": "PropertyValue", "name": "DIČ", "value": "CZ12345678" }
  ]
}
```

## NAP scanner

NAP = Name, Address, Phone. Scanner prohledá obsah publikovaných příspěvků a stránek a hledá výskyty telefonního čísla, města a názvu firmy.

### Logika detekce telefonu

1. Referenční telefon (z nastavení) se normalizuje stripem všech znaků kromě číslic a `+`
2. V textu se hledají sekvence číslic a oddělovačů (`\s`, `-`, `.`) délky 8–20 znaků
3. Každá nalezená sekvence se normalizuje stejným způsobem
4. Pokud výsledek odpovídá referenčnímu číslu, ale raw formát se liší → označeno jako neshodný formát

### Příklady neshodných formátů

| Referenční | Nalezeno v textu | Stav |
|------------|-----------------|------|
| `+420 123 456 789` | `+420 123 456 789` | ✓ OK |
| `+420 123 456 789` | `123456789` | ⚠ Neshodný formát |
| `+420 123 456 789` | `+420-123-456-789` | ⚠ Neshodný formát |
| `+420 123 456 789` | `123 456 789` | ⚠ Neshodný formát |

## Detekce konfliktu

```php
SEOB_LocalSeo_Frontend::has_rank_math_local_seo(): bool
```

Kontroluje:
1. Existenci třídy `RankMath\Modules\LocalSeo\LocalSeo`
2. Option `rank_math_modules` – přítomnost hodnoty `local-seo`

Pokud je konflikt detekován, `output_schema()` okamžitě skončí a nevloží nic.

## Kompatibilita

| Plugin | Stav |
|--------|------|
| Rank Math Free | ✓ Bez konfliktu (RM Free Local SEO neobsahuje) |
| Rank Math Pro (Local SEO modul) | ⚠ Konflikt – modul se deaktivuje |
| Yoast SEO Free | ✓ Bez konfliktu |
| Yoast Local SEO | ⚠ Potenciální konflikt – duplicitní JSON-LD |
| WPML, Polylang | ✓ Bez konfliktu |

## Health checky

| Check | Stav | Zpráva |
|-------|------|--------|
| `local_seo_rm_conflict` | critical | RM Local SEO detekován, modul deaktivován |
| `local_seo_name_missing` | critical | Chybí název firmy, JSON-LD se nevkládá |
| `local_seo_name` | good | Název firmy nastaven |
| `local_seo_address` | warning/good | Adresa vyplněna / chybí |
| `local_seo_gps` | warning/good | GPS nastaveny / chybí |

## AJAX akce

| Akce | Popis |
|------|-------|
| `seob_local_seo_save` | Uloží nastavení formuláře |
| `seob_local_seo_preview` | Vrátí JSON-LD z aktuálně uloženého nastavení |
| `seob_local_seo_nap_scan` | Spustí NAP scan přes publikované příspěvky a stránky |

## Unit testy

Soubor: `tests/Unit/LocalSeo/FrontendTest.php`

| Test | Popis |
|------|-------|
| `test_no_rank_math_local_seo_in_clean_env` | Čisté testovací prostředí bez konfliktu |
| `test_rank_math_local_seo_detected_via_option` | Detekce přes option `rank_math_modules` |
| `test_build_schema_minimal` | Minimální data – jen název a typ |
| `test_build_schema_with_address` | Plná adresa s PostalAddress |
| `test_build_schema_with_gps` | GPS souřadnice → GeoCoordinates |
| `test_build_schema_with_ico_dic` | IČO / DIČ → PropertyValue identifikátory |
| `test_build_schema_opening_hours_excludes_closed_days` | Zavřené dny se nepromítnou do JSON-LD |
| `test_build_schema_skips_hours_row_with_empty_times` | Řádek bez časů (ale ne closed) se přeskočí |
| `test_build_schema_defaults_type_to_local_business` | Prázdný business_type → LocalBusiness |
| `test_build_schema_partial_address_sets_default_country` | Chybí country → výchozí CZ |
| `test_business_types_returns_non_empty_array` | business_types() vrátí neprázdný array |
