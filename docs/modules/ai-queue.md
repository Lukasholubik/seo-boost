# Modul: AI schvalovací fronta

## Proč modul existuje

Master zadání (sekce 2 a 5) požaduje, aby AI nikdy nezapisovala SEO hodnoty
(title, description, alt texty) přímo – každý návrh musí projít schvalovací
frontou a do skutečných polí se zapíše až po ručním potvrzení adminem. API
klíč k AI providerovi se navíc nesmí ukládat jako plaintext v `wp_options`.

Modul `ai-queue` (`depends_on: ['audit']`) je **výchozí vypnutý**, dokud
admin nevyplní API klíč v Nastavení.

## Jak to zapnout

1. SEO Booster Pro → Nastavení → Moduly: zaškrtnout „AI schvalovací fronta“.
2. Sekce „AI asistent“ ve stejné stránce:
   - **Endpoint** – URL OpenAI-compatible API (bez `/chat/completions` na
     konci, to se přidává automaticky). Plugin je navržen jako obecný
     OpenAI-compatible adaptér, takže funguje s jakýmkoli API ve stejném
     formátu. Zdarma dostupné možnosti:
     - Google Gemini: `https://generativelanguage.googleapis.com/v1beta/openai/`
       (model např. `gemini-2.0-flash`, API klíč z
       [Google AI Studio](https://aistudio.google.com/)).
     - Groq: `https://api.groq.com/openai/v1/` (model např.
       `llama-3.1-8b-instant`, API klíč z [console.groq.com](https://console.groq.com/)).
   - **Model** – název modelu dle dokumentace providera.
   - **Max tokens** – limit délky odpovědi (1–4000, default 300).
   - **API klíč** – uloží se **šifrovaně** (AES-256-CBC, klíč odvozený z
     `wp_salt('auth')`). Pole zůstává prázdné při zobrazení; pokud necháte
     prázdné a uložíte, stávající klíč se zachová. Jiné API nikdy nedostane
     zpět dešifrovaný klíč (jen `has_api_key: true/false`).
3. Uložit (tlačítko v horní liště Nastavení).

## Jak fronta funguje

- V Audit Dashboardu (po rozkliknutí „Opravit“ u stránky) se zobrazí
  tlačítka „Navrhnout pomocí AI“ u polí SERP Title a Meta description, a
  „Navrhnout alt texty obrázků“ pod seznamem nálezů (jen pokud stránka má
  nález „Obrázky bez alt textu“).
- Kliknutí zavolá AJAX (`seob_ai_suggest` / `seob_ai_suggest_alt`), který
  sestaví prompt (`SEOB_AiQueue_Prompt_Builder`), zavolá AI providera
  (`SEOB_AiQueue_OpenAi_Compatible_Provider::complete()`) a **vloží návrh do
  tabulky `seo_booster_ai_queue` se stavem `pending`** – **nic se nezapíše**
  do `rank_math_title`/`rank_math_description`/`_wp_attachment_image_alt`.
- U alt textů se procházejí `<img>` tagy v obsahu příspěvku bez alt textu
  nebo s genericky vyhlížejícím alt (`img1`, `dsc_0001`...), max. 5 obrázků
  na stránku.
- Stránka **SEO Booster → AI fronta** (`seob-ai-queue`) zobrazuje návrhy
  podle stavu (Čeká na schválení / Schváleno / Zamítnuto) s náhledem aktuální
  hodnoty a návrhu AI:
  - **Schválit** – zapíše návrh do skutečného pole (`update_post_meta`) a
    nastaví stav na `approved`.
  - **Zamítnout** – jen změní stav na `rejected`, hodnotu nezapíše.

## Architektura (pro vývojáře)

`includes/AiQueue/`:

- `Crypt.php` (`SEOB_AiQueue_Crypt`) – `encrypt()`/`decrypt()`, AES-256-CBC,
  klíč `hash('sha256', wp_salt('auth'), true)`, náhodné IV per volání.
- `ProviderInterface.php` (`SEOB_AiQueue_Provider_Interface`) – `complete(string $prompt): string|WP_Error`.
  Vyměnitelný adaptér – pro jiného providera (např. přímé OpenAI SDK, Claude)
  stačí přidat novou implementaci a vrátit ji z `SEOB_AiQueue_Ajax::build_provider()`.
- `OpenAiCompatibleProvider.php` – jediná implementace, `wp_remote_post` na
  `{endpoint}/chat/completions`.
- `Repository.php` (`SEOB_AiQueue_Repository`) – CRUD nad tabulkou
  `seo_booster_ai_queue` (`insert`, `get`, `get_list`, `set_status`,
  `count_by_status`).
- `PromptBuilder.php` (`SEOB_AiQueue_Prompt_Builder`) – čisté funkce
  (`for_title`/`for_description`/`for_alt`), kryté PHPUnit testy.
- `Ajax.php` (`SEOB_AiQueue_Ajax`) – AJAX endpointy
  (`seob_ai_suggest`, `seob_ai_suggest_alt`, `seob_ai_queue_list`,
  `seob_ai_queue_approve`, `seob_ai_queue_reject`), stejný vzor jako ostatní
  moduly (`check_ajax_referer` + `current_user_can`).

Tabulka `seo_booster_ai_queue` (`SEOB_Database::ai_queue_table()`): `id,
object_id, field, suggestion, status (pending|approved|rejected),
created_at, reviewed_by, reviewed_at`. Pro alt texty je `object_id`
attachment ID a `field = 'alt_text'`; pro title/description je `object_id`
ID stránky a `field` = `rank_math_title` / `rank_math_description`.

## Health check

`SEOB_Health_Checks::ai_queue_checks()`:

- **Kritická**, pokud je modul zapnutý, ale chybí API klíč – odkaz do
  Nastavení.
- **Varování**, pokud čeká alespoň 1 návrh na schválení – odkaz do AI fronty.
- **OK**, pokud žádné návrhy nečekají.

## Co modul (zatím) NEdělá

- Žádné automatické zápisy – vše čeká na ruční schválení.
- Žádné grafy/trendy v „Stav systému“ (jen surová data přes
  `SEOB_Metrics::record('ai-queue', 'approved_total'|'rejected_total', ...)`
  pro budoucí použití).
- Žádné hromadné generování pro celý web najednou – návrhy se generují
  per-stránka z Audit Dashboardu.
