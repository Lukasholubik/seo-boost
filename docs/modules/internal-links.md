# Modul: Interní prolinkování (Internal Link Assistant)

## Proč modul existuje

Audit Dashboard hodnotí jednotlivé stránky izolovaně (title, description,
schema...), ale neříká nic o tom, jak jsou stránky propojené mezi sebou.
Stránky bez příchozích interních odkazů ("orphan" stránky) se hůř indexují
a hůř předávají autoritu mezi souvisejícím obsahem. Modul proto:

- indexuje interní link graf webu (kdo odkazuje na koho),
- detekuje osamocené (orphan) stránky – bez jediného příchozího interního
  odkazu,
- navrhuje, ze kterých 3 obsahově nejpodobnějších stránek by se na danou
  orphan stránku mělo odkázat.

Podobnost obsahu se počítá **lokálně pomocí TF-IDF a kosinové podobnosti –
bez jakékoli externí AI/API**. Modul tedy nepotřebuje žádný API klíč ani
síťové volání a funguje i offline.

## Co se zlepší a jak to poznám

1. V SEO Booster Pro → Nastavení → Moduly zapněte „Interní prolinkování“
   (`modules.internal-links`, výchozí vypnuto).
2. Na nové stránce **Interní prolinkování** klikněte na „Spustit reindex“ –
   modul postupně (po dávkách) projde všechny publikované stránky a:
   - zaznamená jejich interní odkazy do tabulky `internal_links`,
   - přepočítá TF-IDF podobnost mezi všemi stránkami,
   - pro každou orphan stránku vybere top 3 nejpodobnější stránky a uloží
     je do `link_suggestions`.
3. Po dokončení reindexu se zobrazí:
   - souhrn – počet indexovaných stránek, počet osamocených stránek a
     průměr interních odkazů na stránku (s trendem oproti předchozímu
     reindexu),
   - tabulka **Osamocené stránky** – stránka, typ a 3 návrhy "odkázat z"
     (odkaz na editaci navrhované stránky),
   - tabulka **Všechny stránky** – stránka, typ, počet příchozích a
     odchozích interních odkazů, seřazeno vzestupně podle příchozích
     odkazů (nejvíc osamocené nahoře).
4. V editoru každé indexované stránky (post/page/vlastní typy zahrnuté do
   auditu) se zobrazí postranní box **„Interní prolinkování“** s počtem
   příchozích/odchozích odkazů a top 3 návrhy podobných stránek.

Mezi plnými reindexy se link graf jednotlivé stránky automaticky
aktualizuje při jejím uložení (`save_post`) – ale `link_suggestions` (a tedy
souhrnné počty orphan stránek) se přepočítají až při dalším plném reindexu.

## Jak to funguje technicky

`includes/InternalLinks/`:

- **`Extractor.php`** (`SEOB_InternalLinks_Extractor`) – pro každý příspěvek
  získá HTML obsah (stejná 3-strategie jako Audit Dashboard: `_elementor_data`
  → `post_content` → meta pole) a:
  - `extract_links()` – najde `<a href>` směřující na vlastní web (přes
    `url_to_postid()`), vyloučí odkazy na sebe sama, externí domény,
    `mailto:`/`tel:`/kotvy,
  - `extract_text()` – vrátí čistý text (titulek + obsah) pro TF-IDF.
- **`Similarity.php`** (`SEOB_InternalLinks_Similarity`) – čisté funkce bez
  DB/WordPress: `tokenize()` (lowercase, bez diakritiky, stoplist CZ/EN,
  min. 3 znaky), `build_tfidf()`, `cosine()`, `top_similar()`.
- **`ScanRunner.php`** (`SEOB_InternalLinks_ScanRunner`) – dávkový reindex
  (vzor `PageSpeed/ScanRunner.php`): `start_scan()` / `process_batch()` /
  `finalize_scan()`. Po dokončení zapíše metriky `orphans_count` a
  `avg_inlinks` přes `SEOB_Metrics::record()` a uloží historii běhu do
  `link_scans`.
- **`Indexer.php`** (`SEOB_InternalLinks_Indexer`) – `save_post` hook,
  udržuje `internal_links` aktuální mezi reindexy pro upravovanou stránku.
- **`MetaBox.php`** (`SEOB_InternalLinks_MetaBox`) – postranní box v
  editoru, čte přímo z DB (žádný AJAX).
- **`Ajax.php`** (`SEOB_InternalLinks_Ajax`) – `seob_links_start/batch/
  results/history/active` pro stránku modulu.

### Databázové tabulky

- `wp_seo_booster_internal_links` – aktuální link graf (source → target,
  text odkazu).
- `wp_seo_booster_link_suggestions` – top 3 návrhy prolinkování pro každou
  stránku z posledního plného reindexu.
- `wp_seo_booster_link_scans` – historie běhů reindexu (pro progress bar a
  health check).

## Co modul (zatím) NEdělá

- Nepoužívá žádnou externí AI ani API – podobnost je čistě textová
  (TF-IDF), nerozumí synonymům ani sémantickému významu. Dvě stránky o
  stejném tématu napsané velmi odlišnou slovní zásobou se mohou jevit jako
  málo podobné.
- Nenabízí automatické vkládání odkazů do obsahu – pouze doporučuje, kam
  odkaz manuálně doplnit.
- `link_suggestions` (a počty orphan stránek v souhrnu) se aktualizují jen
  při plném reindexu, ne při každém uložení stránky.

## Jak to monitorovat

- Trendy `orphans_count` a `avg_inlinks` se ukládají do
  `seo_booster_metrics` po každém reindexu a zobrazují se jako delta
  (`(+/-N)`) v souhrnu stránky modulu.
- Doporučení: po větším doplnění obsahu spusťte reindex znovu, ať se
  link graf a návrhy aktualizují.

## Health check

`SEOB_Health_Checks::internal_links_checks()`:

- **Kritická**, pokud ještě neproběhl žádný dokončený reindex – akce
  „Spustit reindex“ vede na stránku modulu.
- **Varování**, pokud poslední reindex je starší než 30 dní – doporučení
  spustit nový.
- **Varování**, pokud `orphans_count` > 0 – zobrazí počet osamocených
  stránek.
- **OK**, pokud žádné osamocené stránky nejsou.

Modul nemá závislosti (`depends_on: []`), ale interně využívá
`SEOB_Audit_ScanRunner::get_audit_post_types()` pro výběr indexovaných
post typů (stejná sada jako Audit Dashboard).
