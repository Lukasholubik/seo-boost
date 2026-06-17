# SEO Booster Pro – Průvodce moduly

> **Kdy co zapnout?** Každý modul má jiné nároky na server. Tato stránka vám pomůže rozhodnout,
> které moduly mít permanentně aktivní a které zapínat jen při skenování.

---

## Kategorie

### Vždy zapnuto (Always-On)

Tyto moduly jsou pasivní nebo mají minimální overhead. Spouštějte je trvale.

| Modul | Proč vždy zapnutý | Overhead |
|-------|-------------------|----------|
| **Audit Dashboard** | Základní monitoring SEO problémů. WP-Cron skenuje na pozadí (ne při každém page load). | WP-Cron, žádný frontend |
| **Redirect Manager** | Loguje 404 požadavky, umožňuje záchytné přesměrování. Klíčový pro zdraví webu. | 1 DB query per 404 hit |
| **Chytrá indexace** | Pasivní hook v `wp_head` – aplikuje noindex na nevhodné katalogové stránky. | < 1 ms per page load |
| **GSC Insights (Rank Math)** | Čte Rank Math Analytics DB jen při otevření Audit stránky. Žádný aktivní overhead. | Pouze při admin view |
| **Core Web Vitals (RUM)** | 2 KB JS beacon měří LCP/INP/CLS od reálných uživatelů. Denní agregace. | 2 KB JS + 1 REST POST per page |
| **Hreflang Manager** | Vkládá `<link rel="alternate">` v `wp_head`. Nulový overhead. Zapnout u vícejazyčných webů. | < 0.5 ms per page load |
| **Local SEO (CZ)** | Vkládá LocalBusiness JSON-LD schema v `wp_head`. Zapnout u lokálních firem. | < 0.5 ms per page load |
| **Interní prolinkování** | MetaBox v editoru + indexer při uložení příspěvku. Žádný frontend overhead. | Per post-save only |

---

### Jen příležitostně (Occasional)

Tyto moduly pouštěj jen při skenování. Po provedení oprav **vypni** – zbytečně nezatěžujte server ani API kvótu.

| Modul | Kdy zapnout | Frekvence | Co spotřebovává |
|-------|-------------|-----------|-----------------|
| **JSON-LD Validátor** | Při podezření na duplicitní schema nebo po nasazení nových schema | Čtvrtletně nebo po změnách | WP-Cron + HTTP GET každé URL |
| **JS Render Gap** | Když podezíráme, že Google nevidí JS-renderovaný obsah | Vždy na 2–4 týdny, pak vypnout | JS beacon na frontend KAŽDÉ stránce + WP-Cron HTTP fetch |
| **HTTP Hlavičky & Bezpečnost** | Po nasazení změn na serveru, při podezření na noindex | Měsíčně | WP-Cron + HEAD request každé URL |
| **Content Decay Monitor** | Při plánování refresh obsahu, min. měsíčně | Měsíčně | Synchronní DB scan (< 3 s, žádné HTTP) |
| **PageSpeed Insights** | Před/po optimalizaci výkonu | Čtvrtletně | Google PSI API kvóta, WP-Cron |
| **AI schvalovací fronta** | Při dávkové tvorbě AI návrhů | Ad hoc (dle potřeby) | OpenAI API – platba per request |
| **Export PDF reportu** | Při přípravě reportu pro klienta | Ad hoc | PDF generace – pouze na kliknutí |

---

## Postup pro Occasional moduly

```
1. Zapni modul v Nastavení → Moduly
2. Spusť scan / počkej na sběr dat
3. Prohlédni výsledky, oprav problémy
4. Vypni modul
```

---

## Závislosti modulů

| Modul | Vyžaduje |
|-------|----------|
| Export PDF reportu | Audit Dashboard |
| GSC Insights | Audit Dashboard + Rank Math Analytics s GSC |
| AI schvalovací fronta | Audit Dashboard |

---

## Poznámky k výkonu

- **JS Render Gap beacon** je JavaScript na každé stránce. Pokud nemáte aktivní monitoring, vypněte modul – beacon přestane se načítat.
- **Content Decay scan** nepotřebuje WP-Cron a proběhne za 1–3 sekundy i pro 200 stránek (čisté DB queries).
- **HTTP Hlavičky a JSON-LD skeny** používají WP-Cron batch (1 URL / 3 s) aby neblokovali server.
- **PageSpeed Insights** je kvótovaný Google API – při aktivním WP-Cronu se testy spouštějí automaticky. Vypnutím modulu WP-Cron dávky zastaví.

---

## Závislost na Rank Math

Tyto moduly čtou data Rank Math (ale nevyžadují Rank Math Pro):

| Modul | Co čte z Rank Math |
|-------|-------------------|
| GSC Insights | `wp_rank_math_analytics_gsc` (Analytics DB) |
| Content Decay | `wp_rank_math_analytics_gsc` (volitelné – pokud GSC připojeno) |
| AI schvalovací fronta | `rank_math_title`, `rank_math_description` meta pole |
| Audit Dashboard | `rank_math_title`, `rank_math_description`, `rank_math_rich_snippet` |
