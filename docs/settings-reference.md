# SEO Booster Pro – Reference nastavení

Všechna nastavení pluginu jsou uložena jako WordPress options s prefixem `seob_`.
Přístup vždy přes `SEOB_Settings` (nikdy `get_option` napřímo v business logice).

---

## seob_general_settings

```php
[
    'debug'               => 0|1,
    'delete_on_uninstall' => 0|1,
    'modules'             => [
        'audit'     => 1,
        'redirects' => 1,
    ],
]
```

---

## seob_audit_settings

```php
[
    'cron_enabled'       => 0|1,   // noční scan přes WP Cron
    'batch_size'         => 20,    // počet URL zpracovaných na jeden běh
    'thin_content_words' => 300,   // hranice pro "thin content" varování
]
```

---

## seob_redirect_settings

```php
[
    'log_404'            => 0|1,
    'log_retention_days' => 30,    // jak dlouho držet 404 záznamy
]
```

---

## DB tabulky

Přístup přes `SEOB_Database::audit_table()`, `::scan_runs_table()`, `::ai_queue_table()`, `::links_table()`.

### {prefix}seo_booster_audit

Jeden řádek na URL a běh scanu.

```sql
CREATE TABLE wp_seo_booster_audit (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scan_id       BIGINT UNSIGNED NOT NULL,        -- FK na scan_runs
  object_id     BIGINT UNSIGNED NOT NULL,        -- post_id / term_id
  object_type   VARCHAR(20) NOT NULL,            -- post, page, product, term
  url           VARCHAR(2083) NOT NULL,
  score         TINYINT UNSIGNED,                -- 0–100 skóre stránky
  issues_json   LONGTEXT,                        -- pole nálezů (typ, závažnost, detail)
  content_hash  CHAR(32),                        -- MD5 obsahu → přeskočení nezměněných
  scanned_at    DATETIME NOT NULL,
  KEY idx_scan (scan_id), KEY idx_object (object_id, object_type)
);
```

**`issues_json` formát** – pole nálezů, např.:

```json
[
  { "type": "missing_meta_description", "severity": "critical", "detail": null },
  { "type": "title_too_long", "severity": "warning", "detail": "612px" },
  { "type": "missing_alt", "severity": "warning", "detail": "3/8" }
]
```

Závažnosti: `critical` | `warning` | `recommendation`.

### {prefix}seo_booster_scan_runs

Historie běhů scanu (graf vývoje skóre v čase).

```sql
CREATE TABLE wp_seo_booster_scan_runs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  started_at    DATETIME NOT NULL,
  finished_at   DATETIME,
  trigger_type  VARCHAR(10),     -- cron / manual / on_save
  urls_total    INT UNSIGNED,
  urls_done     INT UNSIGNED,
  score_avg     TINYINT UNSIGNED,
  status        VARCHAR(10)      -- running / done / failed
);
```

### {prefix}seo_booster_ai_queue

Fronta AI návrhů (meta description, alt texty, …) čekajících na schválení adminem.
**Nikdy se neukládá automaticky** – vždy `pending` → admin schválí/zamítne.

```sql
CREATE TABLE wp_seo_booster_ai_queue (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  object_id     BIGINT UNSIGNED NOT NULL,
  field         VARCHAR(30) NOT NULL,            -- title / description / alt:attachment_id
  suggestion    TEXT NOT NULL,
  status        VARCHAR(10) DEFAULT 'pending',   -- pending / approved / rejected
  created_at    DATETIME NOT NULL,
  reviewed_by   BIGINT UNSIGNED,                 -- user_id schvalovatele
  reviewed_at   DATETIME
);
```

### {prefix}seo_booster_links

Broken links a 404 log (sdíleno s Redirect Managerem).

```sql
CREATE TABLE wp_seo_booster_links (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id     BIGINT UNSIGNED,                 -- post obsahující odkaz (NULL = externí hit)
  target_url    VARCHAR(2083) NOT NULL,
  link_type     VARCHAR(10),                     -- internal / external
  http_status   SMALLINT UNSIGNED,
  last_checked  DATETIME,
  hits_404      INT UNSIGNED DEFAULT 0,          -- počet zachycených 404 návštěv
  redirect_to   VARCHAR(2083)                    -- nastavené 301, pokud existuje
);
```

---

## Rank Math kompatibilita

Audit Dashboard čte/zapisuje title a meta description přes Rank Math meta klíče
(`rank_math_title`, `rank_math_description`) přes `update_post_meta()` – žádná
duplicitní evidence, žádný konflikt s Rank Math.
