# M10 – Content Decay Monitor

**Verze:** 0.9.0  
**Stav:** Implementováno (neotestováno v prohlížeči)

## Proč tento modul existuje

Content Decay je jev, kdy stránka postupně ztrácí organickou návštěvnost bez zjevné příčiny. Google preferuje čerstvý, relevantní obsah – stránky, které nebyly aktualizovány měsíce nebo roky, postupně nahrazuje novější konkurencí. Rank Math ani žádný jiný plugin tento fenomén aktivně nesleduje.

## Co se sleduje (decay signály)

| Signál | Body | Zdroj |
|---|---|---|
| Obsah nezměněn > 24 měsíců | +35 | `wp_posts.post_modified` |
| Obsah nezměněn 12–24 měsíců | +20 | `wp_posts.post_modified` |
| Obsah nezměněn 6–12 měsíců | +8 | `wp_posts.post_modified` |
| Pokles GSC kliků > 50% (30d vs. 31–60d) | +40 | Rank Math Analytics GSC |
| Pokles GSC kliků 25–50% | +25 | Rank Math Analytics GSC |
| Pokles GSC kliků 10–25% | +10 | Rank Math Analytics GSC |
| Pozice klesla > 10 míst | +20 | Rank Math Analytics GSC |
| Pozice klesla 5–10 míst | +8 | Rank Math Analytics GSC |
| Stará letní zmínka v textu (rok ≤ aktuální – 2) | +7 | Obsah příspěvku |
| Tenký obsah (< 150 slov) | +15 | Obsah příspěvku |
| Nízký obsah (150–300 slov) | +4 | Obsah příspěvku |

Maximální skóre: 100.

## Kategorie dle skóre

| Skóre | Kategorie | Akce |
|---|---|---|
| 0–20 | Čerstvé (fresh) | Bez akce |
| 21–40 | Stárnoucí (aging) | Sledovat |
| 41–60 | Stagnující (stale) | Plánovat refresh |
| 61–100 | Chřadnoucí (decaying) | Aktualizovat co nejdříve |

## Jak skenování funguje

Scan je **synchronní** (žádný WP-Cron) – provede se jako jeden AJAX request a výsledek je okamžitý. Důvod: jde jen o lokální DB queries (žádné HTTP požadavky na cizí servery).

1. Kliknete **Spustit scan** → server sestaví seznam publishovaných příspěvků (max 200)
2. Načte GSC data v dávce (2 SQL queries pro 30d vs. 31–60d)
3. Analyzuje každý příspěvek (`SEOB_ContentDecay_Analyzer::analyze()`)
4. Výsledky seřadí dle decay skóre sestupně
5. Uloží do `wp_options` + archiv posledních 10 skenů

**GSC data:** Pokud Rank Math nemá připojen Google Search Console (tabulka `wp_rank_math_analytics_gsc` neexistuje), GSC signály se přeskočí a scan funguje jen s lokálními daty (věk, obsah, letní zmínky).

## Bez GSC dat – co se stále detekuje

- Věk obsahu (content age) dle `post_modified`
- Tenký obsah (word count)
- Stará letní zmínka (rok ≤ aktuální – 2 v titulku nebo obsahu)

## Health check

| Check | Stav | Popis |
|---|---|---|
| `content_decay_last_scan` | warning | Žádný scan dosud neproběhl |
| `content_decay_last_scan` | warning | Scan starší než 30 dní |
| `content_decay_critical` | critical | Stránky s decay skóre ≥ 61 (chřadnoucí) |
| `content_decay_warning` | warning | Stránky s decay skóre 41–60 (stagnující) |

## Soubory

| Soubor | Popis |
|---|---|
| `includes/ContentDecay/Analyzer.php` | Analýza jednoho příspěvku – signály, decay skóre |
| `includes/ContentDecay/Scanner.php` | Scan všech příspěvků, bulk GSC queries, archiv výsledků |
| `includes/ContentDecay/Ajax.php` | AJAX endpointy (run_scan, get_results, get_history) |
| `templates/admin/page-content-decay.php` | Admin dashboard: summary karty, filtr, tabulka |
| `assets/admin/js/content-decay.js` | Frontend: spuštění scanu, render tabulky, filtrování |
| `docs/modules/content-decay.md` | Tato dokumentace |

## AJAX akce

| Akce | Popis |
|---|---|
| `seob_decay_run_scan` | Spustí synchronní scan a vrátí výsledky |
| `seob_decay_get_results` | Vrátí výsledky posledního scanu |
| `seob_decay_get_history` | Vrátí archiv posledních 10 skenů |

## Aktivace

Modul je ve výchozím stavu vypnut. Aktivace: **Nastavení → Moduly → Content Decay Monitor**.

Po aktivaci se zobrazí nová položka menu **Content Decay** v admin menu SEO Booster.
