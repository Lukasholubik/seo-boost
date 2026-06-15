# Modul: Chytrá indexace (M14)

## Proč modul existuje

Katalogy a e-shopy s dynamickými filtry generují kombinatorickou explozi URL
(index bloat). Modul vyhodnocuje kombinace **obor / služba / lokalita**
(profil Katalog) a navrhuje, které kombinace indexovat jako landing page
(Tier A), které procházet bez indexace (Tier B – noindex,follow) a které
konsolidovat na čistou URL (Tier C – canonical bez utility parametrů).

Plný popis zadání: `C:\Users\PC\Downloads\SEO_Booster_Pro_Audit_Dashboard_a_nove_moduly.md`
(sekce M14 v2).

## Co modul dělá (MVP, verze 0.2.0)

- **Mapování** (Nastavení → Chytrá indexace): admin vybere typ obsahu pro
  "detail firmy" a taxonomie pro obor / lokalitu / (volitelně) službu.
  Modul je tím **generický** – nezávisí na konkrétním katalogovém pluginu.
- **Analýza** (`SEOB_SmartIndexing_Catalog_Scanner::run_scan()`):
  - Detaily firem → skóre úplnosti profilu (obsah ≥ 50 slov, náhledový
    obrázek, perex, zařazení do oboru, zařazení do lokality) → Tier A/B dle
    prahu `completeness_threshold`.
  - Hlavní obory → vždy Tier A.
  - Obor × lokalita → Tier A při ≥ `min_companies` firmách, jinak kandidát
    (3+ firem) nebo noindex (< 3).
  - Služba × lokalita (pokud je nakonfigurována taxonomie služeb) → vždy
    kandidát k ručnímu schválení.
  - Výsledky se ukládají do `wp_seo_booster_facet_urls`. Ruční rozhodnutí
    (`approved_manual` / `demoted_manual`) se při dalším scanu nepřepisují.
- **Frontend** (`SEOB_SmartIndexing_Frontend`, jen mimo režim Dry-run):
  - `rank_math/frontend/canonical` – pokud aktuální URL obsahuje parametr
    z blacklistu, canonical se přesměruje na čistou URL bez něj (Tier C,
    bez kombinace s noindexem).
  - `rank_math/frontend/robots` – pokud je aktuální URL v `facet_urls` s
    `tier = 'B'`, doplní se `noindex, follow`.

## Co modul (zatím) NEdělá

- Neimplementuje plný skórovací model z kap. 4.3 zadání (váhy pro hledanost
  z GSC/M7, interní prokliky atd.) – ten vyžaduje modul M7 (Search Console),
  který v pluginu ještě neexistuje. Skóre v MVP je jen orientační
  (poměr `result_count` k `min_companies`).
- Nevytváří fyzické landing page pro kombinace obor × lokalita – jen
  je navrhuje a počítá, kolik firem by na takové stránce bylo. Vytvoření
  čisté URL + šablony je úkol pro web/téma, ne pro tento plugin.
- Neřeší robots.txt blokace (Tier C "tracking" parametry dle kap. 4.1) –
  pouze canonical pro "technické/utility" parametry.
- Health check zatím nekontroluje plnou konzistenci
  noindex+sitemap/canonical (kap. 9 zadání) – jen mapování, režim a
  poslední běh analýzy.

## Crocoblock / JetSmartFilters

Web běží na JetEngine + JetSmartFilters. Důležité body pro tento modul:

- **AJAX filtrování** (JetSmartFilters výchozí chování, "Apply Type: Apply
  button" bez URL Query, nebo Ajax storage) jde přes `admin-ajax.php`.
  `is_admin()` v WordPressu vrací `true` i pro `admin-ajax.php`, takže
  `SEOB_SmartIndexing_Frontend` se na tyto požadavky vůbec neuplatní –
  AJAX filtrování není ovlivněno.
- **URL Query storage** (filtr nastaven na "Storage Type: URL Query") vede
  ke klasickému načtení stránky s query parametry, kde Rank Math i tento
  modul běží normálně. Názvy parametrů si ale definuje každý filtr widget
  zvlášť (pole "Query Var" v JetSmartFilters – obvykle slug taxonomie/meta
  klíče, ne jednotný prefix).
- **Postup pro admina:** v JetSmartFilters zkontrolovat "Query Var" u všech
  utility filtrů (seřazení, "otevřeno teď", hodnocení, rozsah ceny, radius
  apod.) a tyto názvy doplnit do pole "Zakázané parametry" v nastavení
  tohoto modulu. Naopak parametry představující **obor/lokalitu** (pokud
  je tak filtr nastaven) do blacklistu **nepatří** – ty se mají řešit jako
  kandidáti na Tier A/B přes sekci "Přehled SEO příležitostí", ne canonical
  pryč.

## Nastavení

Option klíč: `seob_smart_indexing_settings` (`SEOB_Settings::SMART_INDEXING`).

| Klíč | Výchozí | Popis |
|---|---|---|
| `profile` | `catalog` | `catalog` / `eshop` |
| `mode` | `dry_run` | `dry_run` / `semi_auto` / `auto` |
| `company_post_type` | `''` | CPT pro detail firmy |
| `category_taxonomy` | `''` | taxonomie oboru |
| `location_taxonomy` | `''` | taxonomie lokality |
| `service_taxonomy` | `''` | taxonomie služby (volitelné) |
| `min_companies` | `5` | práh pro Tier A u obor × lokalita |
| `completeness_threshold` | `60` | práh úplnosti profilu firmy (%) |
| `max_depth` | `2` | (informativní, vynucení hloubky je v dalším kole) |
| `blacklist_params` | seznam dle kap. 4.1 | parametry → canonical na čistou URL |

## DB tabulky (od `SEOB_DB_VERSION` 0.4.0)

- `wp_seo_booster_facet_rules` – připraveno pro oborové/lokalitní overridy
  (kap. 6, sekce 2-3 UI mockupu), v MVP se ještě nezapisuje.
- `wp_seo_booster_facet_urls` – návrhy z analýzy + ruční rozhodnutí.
- `wp_seo_booster_facet_signals` – denní signály (imprese, kliky, hledání) –
  připraveno pro napojení na M7, v MVP prázdné.

## AJAX endpointy

`seob_smart_index_options`, `seob_smart_index_save_settings`,
`seob_smart_index_scan`, `seob_smart_index_results`,
`seob_smart_index_approve`, `seob_smart_index_demote`
(vše `manage_options` + nonce `seob_admin_nonce`).

## Health check

`SEOB_Health_Checks::smart_indexing_checks()`: mapování nastaveno?,
aktuální režim (upozornění při Dry-run), datum poslední analýzy.

## Nápověda v adminu

Stránka „Chytrá indexace“ obsahuje přímo nápovědu (sbalitelný blok pod
úvodním popisem) s vysvětlením Tier A/B/C, významu sloupců tabulky výsledků
a tlačítek Schválit/Noindex, a u každého nastavení (profil, mapování
CPT/taxonomií) popisek, co konkrétně dělá. Tento dokument popisuje totéž
podrobněji + doporučený postup nasazení a rizika – v adminu je jen
zhuštěná verze pro rychlou orientaci.

## Zapnutí / vypnutí modulu

Modul je ve výchozím nastavení **vypnutý**. Zapnout/vypnout lze dvěma
způsoby:

- Nastavení → Moduly → checkbox „Chytrá indexace“ + „Uložit“.
- **Stav systému** (`seob-status`) → tabulka „Moduly“ → tlačítko
  „Zapnout“/„Vypnout“ u řádku modulu – přepne stav okamžitě přes AJAX
  (`seob_status_toggle_module`), bez nutnosti otevírat Nastavení. Stejné
  tlačítko je u všech modulů (Audit, Redirect Manager, Export PDF, Chytrá
  indexace).

Vypnutí modulu se promítne i do **admin menu** – položka „Chytrá indexace“
v levém menu se zobrazuje jen pokud je modul aktivní
(`includes/Admin/Admin.php::register_menus()`). „Stav systému“ a
„Nastavení“ jsou v menu vždy, aby šel modul znovu zapnout.

Vypnutí modulu je **úplné**: `SEOB_Module_Manager::init_active()`
instanciuje `SEOB_SmartIndexing_Ajax` a `SEOB_SmartIndexing_Frontend` jen
pro aktivní moduly, takže při vypnutí se nezaregistrují ani AJAX endpointy,
ani filtry `rank_math/frontend/canonical`/`...robots` – web se chová, jako
by modul nainstalovaný nebyl. Data v `facet_*` tabulkách (návrhy, ruční
rozhodnutí) zůstávají zachována pro opětovné zapnutí.

## Doporučený postup nasazení (rollout)

1. **Mapování** – v Nastavení → Chytrá indexace namapovat CPT pro detail
   firmy a taxonomie obor/lokalita (služba volitelně). Zkontrolovat
   `min_companies` (kolik firem musí být ve městě, aby dávalo smysl
   landing page obor × lokalita – 5 je rozumný start pro menší katalogy,
   u velkých katalogů zvážit 8–10) a `completeness_threshold` (60 % je
   benevolentní – pro reálné klienty zvážit 70–80 %, aby se do indexu
   nedostávaly téměř prázdné profily firem).
2. **Blacklist parametrů** – projít aktivní filtry (zejména JetSmartFilters
   s "URL Query" storage, viz sekce výše) a doplnit jejich "Query Var"
   názvy. Spustit zkušebně několik URL s filtry a ověřit v
   nástroji pro inspekci canonicalu (Rank Math / "View Source"), že se
   skutečně přesměrují na čistou URL.
3. **Dry-run analýza** – zatímco modul je vypnutý nebo v režimu Dry-run,
   spustit „Spustit analýzu“ opakovaně (po úpravách mapování/prahů) a
   procházet návrhy. V tomto režimu se nic na frontendu nemění – jen se
   plní `facet_urls`.
4. **Ruční review návrhů** – u kombinací obor × lokalita s Tier B
   (`candidate`/`too_few`) a u firem s Tier B (`rule_thin_profile`)
   rozhodnout Schválit/Noindex. U `service_city` (vždy
   `candidate_manual_review`) prověřit zejména kombinace s vysokým
   `result_count` – ty jsou kandidáti na samostatné landing page (mimo
   tento modul, viz "Co modul NEdělá").
5. **Zapnutí modulu + Semi-auto** – až bude mapování a první review
   hotové, zapnout modul (Stav systému → „Zapnout“) a přepnout `mode` na
   `semi_auto`. Tím se aktivují filtry canonical/robots pro **již
   schválené/zamítnuté** URL (`approved_manual`/`demoted_manual` i
   automaticky vyhodnocené Tier A/B), ale nová URL stále čekají na review
   při dalším scanu.
6. **Auto** (`mode = auto`) zvážit jen u větších katalogů, kde je manuální
   review jednotlivých kombinací obor × lokalita neúnosné – pak modul
   sám reaguje na výsledky `CatalogScanner` bez čekání na schválení.
   Doporučení: i v `auto` režimu pravidelně kontrolovat „Stav systému“
   (datum poslední analýzy) a stránku „Chytrá indexace“, zda nevznikají
   neočekávané kombinace (např. nový obor s velmi nízkým počtem firem).
7. **Pravidelné rescany** – zatím bez vlastního cronu (kap. "Co modul
   NEdělá" / druhá iterace). Doporučeno spustit „Spustit analýzu“ ručně po
   každé větší změně katalogu (import nových firem, nová lokalita/obor).

## Rizika a na co si dát pozor

- **Tier C vs. Tier B se nikdy nekombinují** – `filter_canonical()` řeší
  jen utility parametry z blacklistu (canonical na čistou URL), `noindex`
  z `filter_robots()` se aplikuje jen pokud URL v `facet_urls` nemá tier
  C-relevantní parametr v URL. Pokud admin omylem dá do blacklistu
  parametr, který zároveň reprezentuje obor/lokalitu, hrozí, že se
  validní landing page (Tier A) přesměruje na jinou (obecnější) URL –
  proto blacklist obsahuje jen "technické" parametry (řazení, stránkování
  filtrů, tracking, …), nikdy obor/lokalitu/službu.
- **Synteticky generované URL** (`/{obor}/{lokalita}/`) pro
  `category_city`/`service_city` v `facet_urls` jsou **návrhy**, ne
  existující stránky. Pokud na webu taková URL neexistuje (404), zařazení
  do Tier A v `facet_urls` nic neudělá – fyzickou stránku/routu musí
  vytvořit web/téma. Než se stránka vytvoří, schválené návrhy v tabulce
  neslouží k ničemu navíc než jako TODO list.
- **Ruční rozhodnutí přežívají rescan**, ale ne mapování – pokud se
  později změní `category_taxonomy`/`location_taxonomy` apod., staré
  záznamy v `facet_urls` mohou odkazovat na neexistující termy/URL.
  Doporučeno po větší změně mapování staré výsledky zkontrolovat (případně
  v budoucí iteraci doplnit možnost smazat historii analýzy, podobně jako
  u Audit Dashboardu).
- **Dry-run nic neomezuje** – i v Dry-run modu `CatalogScanner` čte
  produkční DB (počty příspěvků dle `tax_query`), takže na velkých
  katalozích může analýza chvíli trvat (`MAX_COMBOS = 500` limituje počet
  zkoumaných kombinací obor × lokalita).
