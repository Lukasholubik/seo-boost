# M8 – Hreflang Manager

## Přehled

Modul spravuje hreflang skupiny a automaticky vkládá
`<link rel="alternate" hreflang="...">` tagy do `<head>` pro singulární stránky zahrnuté ve skupinách.

## Skupinový model

**1 skupina = 1 dokument v N jazykových verzích.**

Každý člen skupiny má:
- `page_id` – WordPress post/page ID
- `locale` – BCP 47 kód jazyka (cs, en, en-US, de…)
- `is_x_default` – označí tuto verzi jako `x-default` (max. 1 na skupinu)

Reciprocita je garantována automaticky – všechny stránky skupiny se vzájemně křížově odkazují.

## DB tabulky

| Tabulka | Účel |
|---|---|
| `seo_booster_hreflang_groups` | Seznam pojmenovaných skupin |
| `seo_booster_hreflang_members` | Členové skupin (page_id, locale, is_x_default) |

## Detekce konfliktu

Výstup hreflang tagů je automaticky potlačen, pokud je detekován plugin, který hreflang spravuje sám:
- **Rank Math Pro** (`RANK_MATH_PRO_FILE` konstanta nebo `RankMathPro` třída)
- **Yoast SEO Premium** (`WPSEO_Premium` třída)

V takovém případě admin stránka zobrazí varování. Data skupin zůstávají uložena.

## Detekce vícejazyčnosti

| Plugin | Indikátor |
|---|---|
| WPML | `defined('ICL_LANGUAGE_CODE')` |
| Polylang | `function_exists('pll_languages_list')` |
| TranslatePress | `class_exists('TRP_Translate_Press')` |

Pokud není detekován žádný vícejazyčný plugin, admin stránka zobrazí informační banner.

## AJAX akce

| Akce | Handler | Popis |
|---|---|---|
| `seob_hreflang_load` | `load_groups()` | Načte všechny skupiny s members |
| `seob_hreflang_save` | `save_group()` | Uloží skupinu (create/update) |
| `seob_hreflang_delete` | `delete_group()` | Smaže skupinu + její members |
| `seob_hreflang_search` | `search_posts()` | Autocomplete vyhledávání stránek |
| `seob_hreflang_validate` | `validate()` | Validátor (duplicitní page, nepublikované stránky) |

## Aktivace

Modul je ve výchozím stavu vypnut. Aktivace: **Nastavení → Moduly → Hreflang Manager**.
Po aktivaci se vytvoří DB tabulky (přes `maybe_upgrade()` / `dbDelta`).

## Automatické mapování skupin (naplánováno, neimplementováno)

Aktuálně se skupiny vytvářejí ručně. Plánovaná funkce „Automaticky detekovat skupiny"
projde stránky webu, navrhne mapování a počká na schválení – nic se neuloží bez potvrzení.

### Strategie detekce (pořadí spolehlivosti)

| Strategie | Podmínka | Spolehlivost |
|---|---|---|
| **Polylang integrace** | `pll_get_post_translations()` | 100 % – data přímo z překladového pluginu |
| **WPML integrace** | `icl_object_id()` | 100 % – stejný princip |
| **Shoda URL slugu** | bez jazykového pluginu | střední – závisí na konzistenci názvů |

### Implementační poznámky

- Polylang a WPML vrátí `[ 'cs' => post_id, 'en' => post_id, ... ]` → přímý převod na skupinu.
- URL slug strategie: projít všechny publikované stránky, seskupit podle slug bez jazykového prefixu adresáře (`/en/about` → slug `about`), navrhnout skupiny kde shoda ≥ 2 stránek s různými prefixem.
- UI flow: výsledek detekce zobrazit jako seznam návrhů (zaškrtávací seznam) → „Importovat vybrané skupiny" → uložení.
- Ručně vytvořené skupiny zůstanou nedotčeny.

## Soubory

```
includes/Hreflang/Manager.php     – frontend output + detekce konfliktu/jazyka
includes/Hreflang/Ajax.php        – AJAX handlery
templates/admin/page-hreflang.php – admin šablona (vč. inline dokumentace)
assets/admin/js/hreflang.js       – group management UI
docs/modules/hreflang.md          – tato dokumentace
```
