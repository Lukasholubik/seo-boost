# M12: JS SEO – Render Gap Detektor

## Proč tento modul existuje

Page buildery (Elementor, Divi, WPBakery) a JS widgety injektují obsah do DOM až po vykonání JavaScriptu. Googlebot sice JavaScript renderuje, ale při výpadku renderování (přetížení crawl budgetu, timeout JS, chyba pluginu) vidí pouze surové HTML – a tam klíčový obsah chybí. Rozdíl mezi surovým HTML a finálním vyrenderovaným DOM nazýváme „render gap". Pokud je gap velký, hrozí výpadek indexace headingů, JSON-LD, meta description nebo textu, který Google vidí jinak než skutečný uživatel.

Zdroj: [Google Search Central – JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)

## Co se zlepší a jak to poznám

- **% obsahu dostupného v surovém HTML ↑** – cíl: gap score každé stránky < 20 bodů
- **H1, title, meta description, JSON-LD spolehlivě v raw HTML** – Google je vidí i bez JS
- **Žádné „ghost" stránky** – stránky, kde Google indexuje prázdné HTML, zmizí ze seznamu

## Jak to monitorovat

- **Záložka JS Render Gap v adminu**: tabulka všech analyzovaných URL s gap skóre (0–100), filtrovatelná dle závažnosti
- **Metriky**: `pages_with_gap` (count > 20 bodů), `avg_gap_score` – ukládány do `seo_booster_metrics` po každém scanu
- **Frekvence analýzy**: beacon sbírá rendered snapshoty průběžně od reálných návštěvníků; porovnání s raw HTML probíhá 1× týdně cronem (pondělí 03:30)
- **Alert při novém problému**: pokud nová stránka dostane gap score > 50, zapíše se do logů jako warning

## Health check

| Test | Pass | Warning | Fail |
|------|------|---------|------|
| **Beacon funkční** | Za posledních 48 h přišel aspoň 1 snapshot | – | 0 snapshotů → beacon pravděpodobně blokuje cache/consent |
| **Raw fetch** | `wp_remote_get` testovací URL vrátí 200 | – | Loopback nefunguje (SSL, firewall) |
| **Počet stránek s gap > 50** | 0 | 1–3 | > 3 |
| **DB tabulky** | Obě tabulky existují | – | Chybí → `maybe_upgrade()` neproběhl |
