# SEO Booster Pro – Přehled pluginu

> **Rychlý průvodce pro navigaci v kódu.** Při každém novém úkolu nejprve nahlédni do `dev-log.md`, pak sem.

---

## Základní info

| Položka | Hodnota |
|---|---|
| Verze | 0.1.0 |
| Prefix option klíčů | `seob_` |
| Prefix tříd | `SEOB_` |
| Prefix DB tabulek | `{prefix}seo_booster_*` |
| GitHub repo | `https://github.com/Lukasholubik/seo-boost/` |
| Auto-updater | Plugin Update Checker v5.5 (vendor/) |
| Kompatibilita | Rank Math (Free) – nekoliduje, čte/zapisuje jeho meta klíče |

---

## Účel pluginu

Centrální SEO nástroj pro weby rodiny Grou.cz (grou.cz, reboost.cz a klientské weby agentury):

1. **SEO Audit Dashboard** – hromadný scan všech URL, skóre 0–100, seznam nálezů (meta tagy,
   nadpisy, obrázky, structured data, indexace, odkazy, obsah, E-E-A-T), inline oprava přímo
   z přehledu, hromadné AI návrhy se schvalováním.
2. **Redirect Manager + 404 log** – zachytávání 404, vytvoření 301 jedním klikem, import/export.
3. **SERP náhled** – pixelová šířka title/description (ne počet znaků).
4. *(plánováno)* Interní prolinkování, Content Decay Monitor, Search Console integrace,
   Local SEO (LocalBusiness + IČO/DIČ), Image SEO Optimizer, white-label PDF/CSV reporty,
   editory robots.txt/.htaccess, audit log změn.

Detailní architektura a UI mockupy: viz `SEO_Booster_Pro_Audit_Dashboard_a_nove_moduly.md`
(zadávací dokument, není součástí repozitáře pluginu).

---

## Struktura složek

```
seo-boost/
├── seo-boost.php               ← bootstrap, konstanty (SEOB_VERSION, SEOB_PLUGIN_DIR…)
├── uninstall.php                ← smaže tabulky a options pokud je nastaveno delete_on_uninstall
├── CHANGELOG.md                 ← oficiální changelog verzí pluginu
├── package.json / tailwind.config.js  ← Tailwind build (prefix seob-)
├── docs/                         ← TATO SLOŽKA – vývojová dokumentace
│   ├── overview.md               ← tento soubor
│   ├── settings-reference.md     ← všechny option klíče a DB schéma
│   └── dev-log.md                ← deník změn ze společných session
│
├── includes/
│   ├── Plugin.php                ← orchestrátor, inicializuje všechny moduly
│   ├── Settings.php               ← čte/zapisuje WordPress options (seob_*)
│   ├── Activator.php              ← aktivace/deaktivace, tvorba DB tabulek
│   ├── grou-admin-group.php       ← vizuální seskupení Grou.cz pluginů v menu
│   │
│   ├── Database/Database.php      ← helper pro názvy DB tabulek
│   ├── Admin/Admin.php            ← registrace admin stránek, enqueue assets
│   ├── Audit/                     ← (plánováno) scanner, scoring, issues
│   └── Redirects/                 ← (plánováno) redirect manager, 404 log
│
├── templates/admin/
│   ├── page-dashboard.php          ← Audit Dashboard (placeholder)
│   ├── page-redirects.php          ← Přesměrování (placeholder)
│   └── page-settings.php           ← Nastavení (placeholder)
│
└── assets/
    ├── src/admin.css                ← Tailwind zdroj (@tailwind direktivy)
    └── admin/css/admin.css          ← vygenerovaný build (npm run build:css)
```

---

## Třídy a jejich zodpovědnost

| Třída | Soubor | Co dělá |
|---|---|---|
| `SEOB_Plugin` | Plugin.php | Inicializuje a propojuje všechny moduly |
| `SEOB_Settings` | Settings.php | Jediný přístupový bod pro čtení/zápis options |
| `SEOB_Database` | Database/Database.php | Helper: `audit_table()`, `scan_runs_table()`, `ai_queue_table()`, `links_table()` |
| `SEOB_Activator` | Activator.php | `activate()` / `deactivate()` – tvorba DB tabulek, výchozí hodnoty |
| `SEOB_Admin` | Admin/Admin.php | Registrace admin stránek (Audit Dashboard, Přesměrování, Nastavení), enqueue Tailwind CSS |

---

## DB schéma

Viz `settings-reference.md` pro plný DDL. Tabulky:

- `{prefix}seo_booster_audit` – výsledky scanu (jeden řádek na URL a běh scanu)
- `{prefix}seo_booster_scan_runs` – historie běhů scanu (trend skóre)
- `{prefix}seo_booster_ai_queue` – fronta AI návrhů čekajících na schválení
- `{prefix}seo_booster_links` – broken links a 404 log (sdíleno s Redirect Managerem)

---

## Bezpečnost (přehled)

- Všechny AJAX handlery: `check_ajax_referer('seob_admin_nonce')` + `current_user_can('manage_options')`
- Sanitizace vstupů: `sanitize_text_field`, `esc_url_raw`, `absint` atd.
- Výstup vždy přes `esc_html()` / `esc_attr()` / `esc_url()`
- API klíče (Search Console OAuth apod.) v logu vždy redaktovány jako `[REDACTED]`
- Redirect Manager – `wp_safe_redirect()`, validace cílové URL (žádné otevřené přesměrování)
- AI návrhy nikdy neukládat automaticky – vždy fronta + ruční schválení

---

## Admin menu

Plugin se registruje pod skupinou **Grou.cz** v levém menu WP adminu (pozice 32, hned za
SmartEmailing Connect na pozici 31 a Emailing Calculator na pozici 30).

- **SEO Booster** (`seo-boost`) – Audit Dashboard
- **Přesměrování** (`seob-redirects`)
- **Nastavení** (`seob-settings`)

---

*Tento soubor popisuje stav pluginu ke dni **2026-06-10**, verze 0.1.0 (kostra). Aktuální změny viz `dev-log.md`.*
