# Modul: Audit Dashboard

## Proč tento modul existuje

Audit Dashboard pravidelně skenuje publikovaný obsah webu (příspěvky a
stránky) a hledá běžné SEO problémy: chybějící/příliš dlouhé title a
description, problémy s nadpisy (H1/hierarchie), chybějící alt texty u
obrázků, chybějící strukturovaná data (schema), thin content a focus
keyword. Bez tohoto modulu by tyto problémy bylo nutné dohledávat ručně,
stránku po stránce.

## Co se zlepší a jak to poznám

- Každý scan vrací skóre 0–100 pro každou stránku a souhrnný průměr
  (`score_avg`) za celý web.
- Audit Dashboard zobrazuje tabulku všech stránek s nálezy, jejich
  závažností (kritické / varování / doporučení) a inline editor pro
  rychlou opravu (title, description, schema).
- Sloupec „Od minula“ ukazuje, kolik nálezů bylo opraveno od předchozího
  scanu – zlepšení je vidět přímo v dashboardu.

## Jak to monitorovat

- Tabulka `seo_booster_metrics` (`module = 'audit'`):
  - `score_avg` – průměrné skóre za poslední dokončený scan, zapisuje se v
    `SEOB_Audit_ScanRunner::finalize_scan()`. Trend je vidět na stránce
    **SEO Booster → Stav systému**.
- Historie jednotlivých scanů je v tabulce `seo_booster_scan_runs`
  (`status`, `started_at`, `finished_at`, `score_avg`).

## Health check

Implementováno v `SEOB_Health_Checks::audit_checks()`, zobrazeno na stránce
**Stav systému** a integrováno do WP Site Health (Nástroje → Stav webu):

- **Poslední scan** – `good` pokud byl poslední scan dokončen za posledních
  7 dní, `warning` 7–30 dní, `critical` pokud je starší než 30 dní nebo
  žádný scan ještě neproběhl. Krok k nápravě: spustit nový scan v Audit
  Dashboardu.
- **Zaseklý scan** – `critical`, pokud scan běží ve stavu `running` déle než
  2 hodiny (pravděpodobně se zasekl). Krok k nápravě: spustit nový scan.
