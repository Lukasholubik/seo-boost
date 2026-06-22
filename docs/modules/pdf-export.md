# Modul: Export PDF reportu

## Proč tento modul existuje

Výsledky auditu jsou užitečné interně, ale pro klienty/obchodní účely je
potřeba prezentovatelný výstup. Tento modul generuje PDF report z výsledků
posledního (nebo zvoleného) scanu – shrnutí, popis nálezů s dopadem a
přínosem opravy, a obchodní nabídku ve 3 variantách podle průměrného skóre.

## Co se zlepší a jak to poznám

- Stránka **Export reportu** umožňuje upravit úvodní shrnutí a vybrat
  obchodní nabídku (`maintenance` / `standard` / `comprehensive`) před
  stažením.
- PDF se generuje serverově přes vendorovaný TCPDF (`vendor/tcpdf/`) bez
  zápisu na disk – přímé stažení přes AJAX `seob_pdf_export`.
- Texty nálezů, šablony nabídek a firemní údaje jsou editovatelné na
  stránce **Export – nastavení**.

## Struktura reportu (od verze 0.3.0)

Aby byl report použitelný i pro weby s desítkami až stovkami stránek,
neobsahuje detailní kartu pro každou stránku, ale rozděluje nálezy na dvě
části:

- **Nejdůležitější zjištění** – plná karta s popisem nálezů (dopad/přínos)
  jen pro N nejhůře hodnocených stránek (`SEOB_Pdf_Report_Data::build()`,
  řazeno dle skóre a počtu nálezů). Počet `N` se nastavuje v **Export –
  nastavení → Rozsah reportu → Detailní výpis u nejhorších stránek**
  (`pdf_settings['report']['detailed_pages_limit']`, default 12).
- **Souhrn nálezů podle typu** – zbylé stránky se seskupí podle typu nálezu
  (`issue_summary`), s počtem postižených stránek a přílohou se seznamem
  konkrétních URL (max 25 per typ v PDF, zbytek jako "… a dalších N").

## Odhad obchodního dopadu (volitelné)

Na stránce **Export reportu** lze před stažením vyplnit tři nepovinná pole:
měsíční návštěvnost, konverzní poměr (%) a průměrná hodnota objednávky/
leadu (Kč). Pokud je vyplněna alespoň návštěvnost, PDF doplní sekci s
orientačním odhadem dopadu zlepšení SEO (`SEOB_Pdf_Report_Data::compute_business_impact()`):

- Najde počet stránek s nálezy ovlivňujícími SERP (title/description/
  duplicity) → odhadne nárůst CTR (1–20 %, heuristika dle podílu postižených
  stránek).
- Z CTR uplift + zadané návštěvnosti spočítá odhad dalších návštěv/měsíc,
  z konverzního poměru odhad dalších konverzí a z hodnoty objednávky odhad
  dalších tržeb/měsíc.
- Vše je v PDF explicitně označeno jako orientační odhad, ne záruka.

## Branding (od verze 0.3.0)

V **Export – nastavení → Firemní údaje a branding** lze nastavit:

- **Logo agentury** – nahrání přes media uploader (`wp.media`), uloží se
  `company.logo_id` + `company.logo_url`; v PDF se zobrazí v záhlaví
  (cestu k souboru pro TCPDF řeší `get_attached_file()` v
  `ReportData::build()` → `company.logo_path`). Pokud zde žádné logo není
  nahráno (`logo_id` prázdné), `ReportData::build()` použije jako fallback
  logo webu nastavené v **Vzhled → Přizpůsobit → Logo webu**
  (`get_theme_mod('custom_logo')`) – v záhlaví PDF se tak vždy zobrazí
  alespoň logo webu, pokud existuje.
- **Velikost loga v PDF** – logo se nikdy nevykresluje větší, než jaká je
  jeho přirozená velikost (`SEOB_Pdf_Report_Data::logo_dimensions_mm()`
  přepočítá px rozměry souboru přes `getimagesize()` při 150 DPI), jen se
  případně zmenší, aby se vešlo do max. boxu (50×14 mm v záhlaví stránky,
  35×18 mm v sekci „Vystavil“). Zabraňuje to rozmazání/pixelaci malých log
  při roztažení na větší plochu. Pro nejlepší výsledek doporučte agentuře
  nahrát logo alespoň v rozlišení odpovídajícím ~150 DPI při tištěné
  velikosti (např. logo zobrazené na ~32×10 mm by mělo mít alespoň cca
  190×60 px).
- **Barevný akcent** (`company.accent_color`, default `#2271b1`) – barva
  nadpisů, zvýraznění skóre a rámečků v `templates/pdf/report.php`.

## Hlavičkový papír na každé stránce

`includes/Pdf/Document.php` (`SEOB_Pdf_Document extends TCPDF`) nahrazuje
přímou instanci `TCPDF` v `PdfRenderer::render()`. Vlastní `Header()` vykreslí
na **každé** stránce logo agentury (`company.logo_path`), název webu a
akcentovou linku; `Footer()` vykreslí akcentovou linku, název firmy a číslo
stránky (`X / Y`). Kvůli místu pro hlavičku/patičku jsou okraje dokumentu
zvětšené (`SetMargins(15, 32, 15)`, `setHeaderMargin(5)`, `setFooterMargin(10)`).

## Souhrn dopadů a přínosů zlepšení

Sekce na konci reportu (před "Naše nabídka"), postavená nad `issue_summary`
z `ReportData::build()` – pro každý typ nálezu vypíše dopad ("pokud se
neřeší") a přínos ("po opravě") jednou za celý web, spolu s počtem
ovlivněných stránek. Slouží jako stručné celkové shrnutí pro klienta, aniž by
bylo nutné procházet jednotlivé karty stránek.

## Sekce "Vystavil"

Na konci PDF (po "Naše nabídka") se zobrazí, kdo report/nabídku vystavil:
logo agentury, jméno zpracovatele (`company.contact_person`), název firmy
(`company.name`), IČO (`company.ico`) a kontakt (`company.contact`). Všechna
pole se editují v **Export – nastavení → Firemní údaje a branding** (pole
"Jméno zpracovatele" a "IČO"). Sekce se zobrazí jen pokud je vyplněn alespoň
jeden z těchto údajů.

## Oprava emoji v titulku PDF

`SEOB_Pdf_Report_Data::sanitize_text()` odstraní z názvu webu znaky nad
U+FFFF (typicky emoji), které font DejaVu Sans v TCPDF nezvládá a zobrazoval
je jako "tofu" znaky (např. 🏢 před "RE:Boost"). Použito na `site_name` před
vložením do `<h1>` i do `SetTitle()`/`SetAuthor()`.

## Jak to monitorovat

- Tabulka `seo_booster_metrics` (`module = 'pdf'`):
  - `export_count` – záznam (hodnota `1`) při každém úspěšném vygenerování
    PDF, zapisuje se v `SEOB_Pdf_Ajax::export()`. Počet exportů za období
    lze odvodit z počtu řádků v daném rozmezí `recorded_at`.

## Health check

Implementováno v `SEOB_Health_Checks::pdf_checks()`, zobrazeno na stránce
**Stav systému** a integrováno do WP Site Health:

- **Knihovna TCPDF** – `good` pokud existuje
  `vendor/tcpdf/seob-tcpdf-loader.php`, jinak `critical` („export PDF
  reportů nebude fungovat“). Krok k nápravě: zkontrolovat instalaci pluginu
  (chybějící `vendor/tcpdf`).

Modul závisí na **Audit Dashboardu** (`depends_on: ['audit']` v
`SEOB_Module_Manager::MODULES`) – bez dat ze scanu nemá report co
zobrazovat.
