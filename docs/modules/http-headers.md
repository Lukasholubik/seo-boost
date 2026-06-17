# M9 – HTTP Hlavičky & Bezpečnost

**Verze:** 0.9.0  
**Stav:** Hotovo (neotestováno v prohlížeči)

## Proč tento modul existuje

Špatně nastavené HTTP hlavičky mohou zablokovat indexaci Googlem (`x-robots-tag: noindex`), oslabovat bezpečnost nebo zpomalovat stránky (chybějící cache). Rank Math ani žádný jiný SEO plugin tyto hlavičky neaudituje – jsou viditelné jen v HTTP odpovědi serveru, ne v HTML.

## Co se kontroluje

### Kritické (blokují SEO nebo dostupnost)
| Problém | Příčina | Dopad |
|---|---|---|
| `x-robots-tag: noindex` v HTTP | `.htaccess`, server konfigurace | Google stránku NEindexuje |
| Stránka neslouží přes HTTPS | Chybí SSL nebo redirect | Google downranking, Chrome varování |
| HTTP status ≥ 400 | Stránka neexistuje nebo chyba | Není dostupná pro Google |

### Varování (bezpečnostní best practice)
| Hlavička | Chybí | Doporučená hodnota |
|---|---|---|
| `Strict-Transport-Security` | HSTS nezajišťuje HTTPS | `max-age=31536000; includeSubDomains` |
| `X-Content-Type-Options` | MIME sniffing riziko | `nosniff` |
| `X-Frame-Options` | Clickjacking riziko | `SAMEORIGIN` |

### Info (optimalizace)
| Hlavička | Chybí | Doporučená hodnota |
|---|---|---|
| `Referrer-Policy` | Nekontrolovaný referrer | `strict-origin-when-cross-origin` |
| `Cache-Control` / `Expires` | Chybí cache | `public, max-age=3600` |
| `Content-Type` bez charset | Možná špatná interpretace | `text/html; charset=UTF-8` |

## Jak skenování funguje

Scan probíhá na serveru na pozadí (WP-Cron), vzor identický s JSON-LD Validátorem:

1. Kliknete **Spustit scan** → server sestaví seznam URL (max 50)
2. WP-Cron každé 3 sekundy zpracuje 1 URL přes `wp_remote_head()` (HEAD request)
3. Admin stránka každé 2 sekundy polluje stav a zobrazuje progress bar
4. Po dokončení se zobrazí výsledky: přehledová tabulka + summary karty

Výsledky jsou uloženy jako WordPress transienty (24h) + archiv posledních 10 skenů v `wp_options`.

## Rychlá kontrola URL

Na spodku admin stránky je panel „Rychlá kontrola URL" – zadejte libovolnou URL a okamžitě uvidíte:
- HTTP status code
- Skóre 0–100
- Seznam problémů s popisem
- Kompletní přehled HTTP hlaviček (rozkliknutelný)

## Skóre

Každá URL dostane skóre 0–100. Odečty:
- Kritický problém: -30 bodů
- Varování: -10 bodů  
- Info: -3 body

## Health check

| Check | Stav | Popis |
|---|---|---|
| `http_headers_last_scan` | warning | Žádný scan dosud neproběhl |
| `http_headers_last_scan` | warning | Scan je starší než 2 týdny |
| `http_headers_critical` | critical | Nalezeny URL s kritickými problémy (noindex, no HTTPS) |
| `http_headers_warnings` | warning | URL chybí bezpečnostní hlavičky |

## Soubory

| Soubor | Popis |
|---|---|
| `includes/HttpHeaders/Checker.php` | Kontrola jedné URL (HEAD request + analýza hlaviček) |
| `includes/HttpHeaders/ScanRunner.php` | WP-Cron dávkové zpracování, archiv výsledků |
| `includes/HttpHeaders/Ajax.php` | AJAX endpointy pro admin UI |
| `templates/admin/page-http-headers.php` | Admin stránka: progress, filtr, tabulka, rychlá kontrola |
| `assets/admin/js/http-headers.js` | Polling, progress bar, tabulka, rychlá kontrola |
| `docs/modules/http-headers.md` | Tato dokumentace |

## AJAX akce

| Akce | Popis |
|---|---|
| `seob_http_headers_start_scan` | Spustí nový scan na pozadí |
| `seob_http_headers_cancel_scan` | Zruší probíhající scan |
| `seob_http_headers_scan_status` | Vrátí aktuální stav scanu (pro polling) |
| `seob_http_headers_get_history` | Vrátí archiv posledních 10 skenů |
| `seob_http_headers_get_results` | Vrátí URL-level detail pro konkrétní scan_id |
| `seob_http_headers_check_url` | Zkontroluje a vrátí výsledek pro jednu URL |

## Aktivace

Modul je ve výchozím stavu vypnut. Aktivace: **Nastavení → Moduly → HTTP Hlavičky & Bezpečnost**.

Po aktivaci se zobrazí nová položka menu **HTTP Hlavičky** v admin menu SEO Booster.
