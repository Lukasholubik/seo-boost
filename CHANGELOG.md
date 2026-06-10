# Changelog – SEO Booster Pro

Všechny výrazné změny jsou dokumentovány v tomto souboru.
Formát dle [Keep a Changelog](https://keepachangelog.com/cs/1.0.0/).

## [Unreleased]

### Přidáno
- Audit Dashboard: scan na vyžádání (dávkové zpracování s progress barem), skóre 0–100,
  kontroly title/description (chybí, délka v px, duplicity), H1/hierarchie nadpisů,
  alt texty obrázků, schema, noindex, thin content, focus keyword
- Inline editace SERP title/description s živým pixelovým náhledem a uložením do Rank Math meta
- Redirect Manager: log 404 požadavků, vytvoření 301 přesměrování jedním klikem,
  cache mapy přesměrování, denní úklid starých 404 záznamů

## [0.1.0] – 2026-06-10

### Přidáno
- Základní kostra pluginu (bootstrap, Settings, Activator, Plugin orchestrátor)
- DB schéma: `seo_booster_audit`, `seo_booster_scan_runs`, `seo_booster_ai_queue`, `seo_booster_links`
- Admin menu pod skupinou Grou.cz: Audit Dashboard, Přesměrování, Nastavení (placeholdery)
- Tailwind CSS build setup
- Plugin Update Checker (GitHub Releases)
