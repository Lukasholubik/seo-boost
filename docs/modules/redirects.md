# Modul: Redirect Manager

## Proč tento modul existuje

Když se na webu změní URL adresy (přejmenování stránky, smazání obsahu),
vznikají 404 chyby, které poškozují SEO i uživatelský zážitek. Redirect
Manager loguje příchozí 404 požadavky a umožňuje je jedním kliknutím
přesměrovat (301) na novou adresu, bez nutnosti ručně procházet logy
serveru.

## Co se zlepší a jak to poznám

- Stránka **Přesměrování** zobrazuje seznam 404 požadavků seřazený podle
  počtu výskytů (`hits_404`).
- U každého záznamu lze jedním kliknutím vytvořit 301 přesměrování na
  zvolenou cílovou adresu.
- Aktivní přesměrování se cachují (transient `seob_redirects_map`, 12 h) a
  aplikují přes `template_redirect`.
- Staré 404 záznamy bez přesměrování se denně automaticky promazávají podle
  nastavené retence (`log_retention_days`).

## Jak to monitorovat

- Tabulka `seo_booster_metrics` (`module = 'redirects'`):
  - `unresolved_404_count` – počet 404 záznamů bez nastaveného
    přesměrování, počítá se při každém běhu denního úklidu
    (`SEOB_Redirect_Manager::cleanup_old_logs()`). Trend je vidět na stránce
    **SEO Booster → Stav systému**.
- Surová data jsou v tabulce `seo_booster_links`
  (`target_url`, `redirect_to`, `hits_404`, `last_checked`).

## Health check

Implementováno v `SEOB_Health_Checks::redirects_checks()`, zobrazeno na
stránce **Stav systému** a integrováno do WP Site Health:

- **Úklid 404 logů (cron)** – `good` pokud je naplánován cron
  `seob_redirects_cleanup` (`wp_next_scheduled`), jinak `critical`. Krok k
  nápravě: zkontrolovat Nastavení / reaktivovat plugin (cron se plánuje při
  aktivaci modulu).
- **Nevyřešené 404** – `warning`, pokud je `unresolved_404_count > 0`,
  jinak `good`. Krok k nápravě: otevřít stránku Přesměrování a vyřešit
  nahlášené 404 adresy.
