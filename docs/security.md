# SEO Booster Pro – Bezpečnostní architektura

> Dokumentace bezpečnostních opatření pluginu. Aktualizováno: 2026-06-17.

---

## Autentizace & Autorizace

Všechny admin AJAX endpointy splňují:

| Kontrola | Implementace |
|----------|--------------|
| Nonce ověření | `check_ajax_referer('seob_admin_nonce', 'nonce')` nebo `check_ajax_referer(..., false)` + JSON error |
| Oprávnění | `current_user_can('manage_options')` – všechny operace vyžadují roli Administrator |
| Granulární oprávnění | `current_user_can('edit_post', $object_id)` pro operace nad konkrétním příspěvkem |

Veřejné REST endpointy (bez autentizace):

| Endpoint | Účel | Ochrana |
|----------|------|---------|
| `POST /wp-json/seo-booster/v1/cwv` | CWV beacon z frontendu | Rate limit 30 req/min per IP hash, striktní validace všech polí (whitelist), max délky |
| `POST /wp-json/seo-booster/v1/js-gap` | DOM snapshot z JS beacon | Rate limit 1 req/URL/IP/24h, sanitize_text_field() na všechna string pole, max délky na všechna pole |

---

## Vstupní sanitizace

| Typ dat | Funkce |
|---------|--------|
| ID, čísla | `absint()`, `(int)`, `min()/max()` rozsah |
| Krátký text | `sanitize_text_field()` + `wp_unslash()` |
| Klíče (enum) | `sanitize_key()` + whitelist kontrola |
| URL | `esc_url_raw()` + validace schématu http/https |
| HTML | `wp_kses_post()` (jen PDF/editor kontexty) |
| Boolean | `isset($_POST[...]) && '1' === $_POST[...]` |
| E-mail | `sanitize_email()` |

---

## SQL bezpečnost

- Všechny dotazy s user-inputem používají `$wpdb->prepare()`.
- Jména tabulek jsou generována statickými metodami `SEOB_Database::*_table()` – nikdy nepocházejí z user inputu.
- Inserty a updaty používají typed format arrays (`['%s', '%d', ...]`).
- Selekty s `IN()` pro dynamický počet itemů používají `implode(',', array_fill(..., '%d'))` pattern.

---

## SSRF ochrana

Endpointy, které dělají server-side HTTP requesty, jsou omezeny:

| Endpoint | Omezení |
|----------|---------|
| `seob_http_headers_check_url` | Pouze URL na stejné doméně jako `home_url()` |
| `seob_json_ld_scan_url` | Pouze URL na stejné doméně jako `home_url()` |
| `HttpHeaders\ScanRunner` | Skenuje pouze URL z `wp_posts` (generované pomocí `get_permalink()`) |
| `JsonLd\ScanRunner` | Skenuje pouze URL z `wp_posts` (generované pomocí `get_permalink()`) |

WP-Cron scannery vždy generují URL seznam z WordPress funkcí – nikdy z user inputu.

---

## XSS ochrana

### PHP šablony

- Všechny výstupy v PHP šablonách používají `esc_html()`, `esc_attr()`, `esc_url()`.
- JSON data předaná do JS přes `wp_localize_script()` jsou automaticky `json_encode()`-ována.

### JavaScript (admin)

- Veškerý dynamický obsah vkládaný do DOM přes jQuery používá `.text()` pro text a `esc()` helper pro URL atributy.
- Žádné přímé `innerHTML` ani `.html()` volání s user-controlled daty.

### REST / Beacon endpointy

- `JsRenderGap\BeaconReceiver`: každý heading item sanitizován přes `sanitize_text_field()` + omezení délky na 200 znaků.
- `CWV\BeaconEndpoint`: WhiteList pro `metric`, `rating`, `device`; `value` je float s rozsahem 0–60000; `path` je stripped od query params + 500 char limit.

---

## API klíče

| Klíč | Uložení |
|------|---------|
| OpenAI API klíč | Šifrovaně v `wp_options` přes `SEOB_AiQueue_Crypt` (AES-256-CBC, klíč z `wp_salt()`) |
| Google PSI API klíč | Šifrovaně v `wp_options` přes `SEOB_AiQueue_Crypt` |
| Cloudflare Turnstile klíče | V `wp_options` (site key je veřejný, secret key je nešifrovaný – akceptovatelné pro server-side use) |

**Nikdy:** API klíče nejsou v kódu, šablonách, ani docs souborech.

---

## Audit výsledky (penetrační test 2026-06-17)

Provedeny kontroly:

- [x] Nonce ověření – všechny AJAX handlery ✅
- [x] Capability checks – `manage_options` + granulární `edit_post` ✅
- [x] SQL injection – `$wpdb->prepare()` všude kde je user input ✅
- [x] XSS v PHP šablonách – esc_html/esc_attr použity ✅
- [x] XSS v JS – jQuery .text() / esc() helper ✅
- [x] SSRF – opraveno: `check_single_url` omezeno na same-domain ✅
- [x] Beacon flooding – rate limit + délkové limity ✅
- [x] Beacon sanitizace – headings_json sanitizováno ✅
- [x] Path traversal – sanitize_text_field() odstraňuje traversal chars ✅

**Nalezeny a opraveny problémy:**

1. **SSRF** – `HttpHeaders\Ajax::check_single_url()` a `JsonLd\Ajax::scan_single_url()` přijímaly libovolné URL. **Opraveno:** přidána validace, že URL musí mít stejnou hostname jako `home_url()`.
2. **XSS via beacon** – `JsRenderGap\BeaconReceiver` ukládal headings array bez per-item sanitizace. **Opraveno:** `sanitize_text_field()` + 200 char limit na každý heading.
3. **Data bounds** – `JsRenderGap\BeaconReceiver` chyběly bounds pro `json_ld_count`, `text_len`, `links_count`. **Opraveno:** přidány min/max limity.
