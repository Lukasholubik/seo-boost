<?php
/**
 * Stránka Local SEO (CZ) – nastavení LocalBusiness JSON-LD schématu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s = SEOB_Settings::get( SEOB_Settings::LOCAL_SEO );

$conflict   = SEOB_LocalSeo_Frontend::has_rank_math_local_seo();
$types      = SEOB_LocalSeo_Frontend::business_types();
$days_label = [
	'Mo' => 'Pondělí',
	'Tu' => 'Úterý',
	'We' => 'Středa',
	'Th' => 'Čtvrtek',
	'Fr' => 'Pátek',
	'Sa' => 'Sobota',
	'Su' => 'Neděle',
];

$pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'Local SEO (CZ)', 'seo-boost' ); ?></h1>

	<?php if ( $conflict ) : ?>
		<div class="notice notice-error" id="seob-local-seo-conflict-banner">
			<p>
				<strong><?php esc_html_e( 'Konflikt detekován:', 'seo-boost' ); ?></strong>
				<?php esc_html_e( 'Rank Math Local SEO modul je aktivní a spravuje LocalBusiness schéma za tebe. Tento modul je automaticky deaktivován, aby nevznikly duplicitní JSON-LD bloky. Pokud chceš používat tento modul místo Rank Math Local SEO, deaktivuj Rank Math Local SEO modul v Rank Math → Nastavení → Moduly.', 'seo-boost' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div id="seob-local-seo-status" class="seob-status-banner" style="display:none;"></div>

	<form id="seob-local-seo-form">

		<?php /* ── 1. Základní informace ─────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Základní informace', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ls-business-name"><?php esc_html_e( 'Název firmy *', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-business-name" name="business_name" class="regular-text"
						value="<?php echo esc_attr( $s['business_name'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'Název přesně tak, jak je uveden v živnostenském rejstříku nebo OR.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-business-type"><?php esc_html_e( 'Typ podnikání', 'seo-boost' ); ?></label></th>
				<td>
					<select id="ls-business-type" name="business_type">
						<?php foreach ( $types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['business_type'], $value ); ?>><?php echo esc_html( $label ); ?> (<?php echo esc_html( $value ); ?>)</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Podtyp LocalBusiness ze schema.org. Zvolte nejpřesnější odpovídající typ.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-description"><?php esc_html_e( 'Popis firmy', 'seo-boost' ); ?></label></th>
				<td>
					<textarea id="ls-description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $s['description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Krátký popis firmy (1–2 věty). Zobrazuje se v Google znalostním panelu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-phone"><?php esc_html_e( 'Telefon', 'seo-boost' ); ?></label></th>
				<td>
					<input type="tel" id="ls-phone" name="phone" class="regular-text"
						value="<?php echo esc_attr( $s['phone'] ); ?>" placeholder="+420 123 456 789">
					<p class="description"><?php esc_html_e( 'Preferovaný formát: +420 123 456 789 (mezinárodní). Tento formát bude porovnáván při NAP scanu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-email"><?php esc_html_e( 'E-mail', 'seo-boost' ); ?></label></th>
				<td>
					<input type="email" id="ls-email" name="email" class="regular-text"
						value="<?php echo esc_attr( $s['email'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-price-range"><?php esc_html_e( 'Cenová kategorie', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-price-range" name="price_range" class="small-text"
						value="<?php echo esc_attr( $s['price_range'] ); ?>" placeholder="$$">
					<p class="description"><?php esc_html_e( 'Nepovinné pole priceRange. Hodnoty: $, $$, $$$, $$$$', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<?php /* ── 2. Adresa ─────────────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Adresa a GPS', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ls-address-street"><?php esc_html_e( 'Ulice a číslo', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-address-street" name="address_street" class="regular-text"
						value="<?php echo esc_attr( $s['address_street'] ); ?>" placeholder="Náměstí Republiky 1">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-address-city"><?php esc_html_e( 'Město', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-address-city" name="address_city" class="regular-text"
						value="<?php echo esc_attr( $s['address_city'] ); ?>" placeholder="Praha">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-address-zip"><?php esc_html_e( 'PSČ', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-address-zip" name="address_zip" class="small-text"
						value="<?php echo esc_attr( $s['address_zip'] ); ?>" placeholder="110 00">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-address-country"><?php esc_html_e( 'Stát (ISO 3166-1)', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-address-country" name="address_country" class="small-text"
						value="<?php echo esc_attr( $s['address_country'] ); ?>" placeholder="CZ" maxlength="3">
					<p class="description"><?php esc_html_e( 'Dvoupísmenný kód: CZ, SK, DE, AT…', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-lat"><?php esc_html_e( 'GPS – Zeměpisná šířka', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-lat" name="lat" class="regular-text"
						value="<?php echo esc_attr( $s['lat'] ); ?>" placeholder="50.075200">
					<p class="description">
						<?php esc_html_e( 'Souřadnice najdete na ', 'seo-boost' ); ?>
						<a href="https://mapy.cz" target="_blank" rel="noopener">mapy.cz</a>
						<?php esc_html_e( ' nebo Google Maps – klikněte pravým tlačítkem na bod a zkopírujte souřadnice.', 'seo-boost' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-lng"><?php esc_html_e( 'GPS – Zeměpisná délka', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-lng" name="lng" class="regular-text"
						value="<?php echo esc_attr( $s['lng'] ); ?>" placeholder="14.437800">
				</td>
			</tr>
		</table>

		<?php /* ── 3. CZ specifika ─────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'CZ specifika – IČO / DIČ', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'IČO a DIČ se vkládají do schématu jako PropertyValue identifikátory. Pomáhají vyhledávačům ztotožnit entitu s rejstříky (ARES, OR) a posilují důvěryhodnost Knowledge Panelu.', 'seo-boost' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ls-ico"><?php esc_html_e( 'IČO', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-ico" name="ico" class="small-text"
						value="<?php echo esc_attr( $s['ico'] ); ?>" placeholder="12345678" maxlength="12">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls-dic"><?php esc_html_e( 'DIČ', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="ls-dic" name="dic" class="small-text"
						value="<?php echo esc_attr( $s['dic'] ); ?>" placeholder="CZ12345678" maxlength="15">
				</td>
			</tr>
		</table>

		<?php /* ── 4. Otevírací doba ──────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Otevírací doba', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Zaškrtněte "Zavřeno" pro dny, kdy je provozovna uzavřena. Časy zadávejte ve formátu HH:MM (24 hodin).', 'seo-boost' ); ?>
		</p>
		<table class="wp-list-table widefat fixed striped seob-table seob-hours-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Den', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Otevřeno od', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Zavřeno v', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Zavřeno (celý den)', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $days_label as $day_key => $day_name ) :
					$h = $s['opening_hours'][ $day_key ] ?? [ 'open' => '', 'close' => '', 'closed' => 0 ];
					$k = strtolower( $day_key );
				?>
				<tr class="seob-hours-row <?php echo ! empty( $h['closed'] ) ? 'seob-hours-closed' : ''; ?>">
					<td><strong><?php echo esc_html( $day_name ); ?></strong></td>
					<td>
						<input type="time" name="hours_<?php echo esc_attr( $k ); ?>_open"
							value="<?php echo esc_attr( $h['open'] ); ?>"
							class="seob-hours-input"
							<?php echo ! empty( $h['closed'] ) ? 'disabled' : ''; ?>>
					</td>
					<td>
						<input type="time" name="hours_<?php echo esc_attr( $k ); ?>_close"
							value="<?php echo esc_attr( $h['close'] ); ?>"
							class="seob-hours-input"
							<?php echo ! empty( $h['closed'] ) ? 'disabled' : ''; ?>>
					</td>
					<td>
						<label>
							<input type="checkbox" name="hours_<?php echo esc_attr( $k ); ?>_closed" value="1"
								class="seob-hours-closed-cb"
								<?php checked( ! empty( $h['closed'] ) ); ?>>
							<?php esc_html_e( 'Zavřeno', 'seo-boost' ); ?>
						</label>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php /* ── 5. Obrázek / logo ──────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Logo / obrázek firmy', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Obrázek', 'seo-boost' ); ?></th>
				<td>
					<div id="seob-ls-image-preview" style="margin-bottom:8px;">
						<?php if ( ! empty( $s['image_url'] ) ) : ?>
							<img src="<?php echo esc_url( $s['image_url'] ); ?>" style="max-width:200px;max-height:100px;border:1px solid #ddd;padding:2px;">
						<?php endif; ?>
					</div>
					<input type="hidden" id="ls-image-id" name="image_id" value="<?php echo esc_attr( (int) $s['image_id'] ); ?>">
					<input type="hidden" id="ls-image-url" name="image_url" value="<?php echo esc_attr( $s['image_url'] ); ?>">
					<button type="button" id="seob-ls-image-select" class="button">
						<?php esc_html_e( 'Vybrat z Média', 'seo-boost' ); ?>
					</button>
					<button type="button" id="seob-ls-image-remove" class="button" <?php echo empty( $s['image_url'] ) ? 'style="display:none"' : ''; ?>>
						<?php esc_html_e( 'Odebrat obrázek', 'seo-boost' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Doporučeno: logo ve formátu PNG nebo JPG, alespoň 112 × 112 px.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<?php /* ── 6. Kde vkládat ─────────────────────────────────────── */ ?>
		<h2><?php esc_html_e( 'Kde vkládat JSON-LD', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Umístění výstupu', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="radio" name="output_on" value="homepage" <?php checked( $s['output_on'], 'homepage' ); ?>>
						<?php esc_html_e( 'Pouze na úvodní stránce (výchozí a doporučeno)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="radio" name="output_on" value="all" <?php checked( $s['output_on'], 'all' ); ?>>
						<?php esc_html_e( 'Na všech stránkách', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="radio" name="output_on" value="contact" <?php checked( $s['output_on'], 'contact' ); ?>>
						<?php esc_html_e( 'Pouze na konkrétní stránce (kontakt):', 'seo-boost' ); ?>
						<select name="contact_page_id" id="ls-contact-page">
							<option value="0"><?php esc_html_e( '— Zvolit stránku —', 'seo-boost' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $s['contact_page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<p class="description"><?php esc_html_e( 'Pokud produkujete schéma na více místech, Google bere v potaz jen jedno – doporučujeme pouze úvodní stránku nebo kontaktní stránku.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<?php /* ── Akce ────────────────────────────────────────────────── */ ?>
		<p style="margin-top:20px;">
			<button type="button" id="seob-ls-save" class="button button-primary">
				<?php esc_html_e( 'Uložit nastavení', 'seo-boost' ); ?>
			</button>
			&nbsp;
			<button type="button" id="seob-ls-preview" class="button">
				<?php esc_html_e( 'Náhled JSON-LD', 'seo-boost' ); ?>
			</button>
			&nbsp;
			<button type="button" id="seob-ls-nap-scan" class="button">
				<?php esc_html_e( 'Spustit NAP scan', 'seo-boost' ); ?>
			</button>
			<span id="seob-ls-status" class="seob-save-status"></span>
		</p>
	</form>

	<?php /* ── JSON-LD náhled ─────────────────────────────────────────── */ ?>
	<div id="seob-ls-preview-box" style="display:none; margin-top:20px;">
		<h2><?php esc_html_e( 'Náhled JSON-LD', 'seo-boost' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Toto je JSON-LD blok, který se vloží do <head> stránky. Výstup je generován z aktuálně uloženého nastavení.', 'seo-boost' ); ?></p>
		<pre id="seob-ls-preview-code" style="background:#f0f0f1;padding:16px;overflow:auto;max-height:500px;border:1px solid #ccc;font-size:12px;"></pre>
		<p>
			<a href="https://validator.schema.org/" target="_blank" rel="noopener">
				<?php esc_html_e( 'Otestovat v Schema.org Validátoru →', 'seo-boost' ); ?>
			</a>
			&nbsp;|&nbsp;
			<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">
				<?php esc_html_e( 'Testovat v Google Rich Results Test →', 'seo-boost' ); ?>
			</a>
		</p>
	</div>

	<?php /* ── NAP scan výsledky ─────────────────────────────────────── */ ?>
	<div id="seob-ls-nap-results" style="display:none; margin-top:20px;">
		<h2><?php esc_html_e( 'Výsledky NAP scanu', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'NAP (Name, Address, Phone) scan prohledá obsah publikovaných příspěvků a stránek a najde výskyty telefonu, města a názvu firmy. Hledá zejména nestandardní formáty telefonu, které mohou způsobit nesoulad s JSON-LD.', 'seo-boost' ); ?>
		</p>
		<div id="seob-ls-nap-summary"></div>
		<table class="wp-list-table widefat fixed striped seob-table" id="seob-ls-nap-table" style="display:none;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Stránka', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Nalezeno', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody id="seob-ls-nap-body"></tbody>
		</table>
	</div>

	<?php /* ── Dokumentace ─────────────────────────────────────────────── */ ?>
	<details class="seob-schema-help" style="margin-top:30px;">
		<summary><?php esc_html_e( 'Dokumentace: co je Local SEO a jak to funguje (klikněte pro zobrazení)', 'seo-boost' ); ?></summary>
		<div class="seob-schema-help-body">

			<h3><?php esc_html_e( 'Co je LocalBusiness JSON-LD?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'LocalBusiness je typ strukturovaných dat ze schema.org. Říká Googlu a dalším vyhledávačům: "tato firma existuje na konkrétním místě, má tuto adresu, telefon a otevírací dobu." Správně nastavené schéma může:', 'seo-boost' ); ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'Zobrazit otevírací dobu přímo ve výsledcích vyhledávání (rich results)', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Posílit nebo vytvořit Knowledge Panel (informační panel) ve vyhledávači', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Zlepšit zobrazení v Mapy Google / mapy.cz při propojení s Google Business Profile', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Zvýšit důvěryhodnost entity v kontextu E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness)', 'seo-boost' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Proč IČO a DIČ?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Google ztotožňuje entity (firmy) s rejstříky, jako je ARES nebo Obchodní rejstřík. IČO (a DIČ) slouží jako jednoznačný identifikátor, který pomáhá propojit webovou entitu se záznamy v rejstřících a zesiluje Knowledge Graph signal. Tyto hodnoty se vkládají do pole identifier jako PropertyValue.', 'seo-boost' ); ?>
			</p>

			<h3><?php esc_html_e( 'Příklad výstupu JSON-LD', 'seo-boost' ); ?></h3>
			<pre style="background:#f0f0f1;padding:12px;overflow:auto;font-size:12px;">{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "name": "ACME s.r.o.",
  "url": "https://acme.cz/",
  "telephone": "+420 123 456 789",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Náměstí Republiky 1",
    "addressLocality": "Praha",
    "postalCode": "110 00",
    "addressCountry": "CZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 50.0875,
    "longitude": 14.4213
  },
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": "https://schema.org/Monday",
      "opens": "09:00",
      "closes": "17:00"
    }
  ],
  "identifier": [
    { "@type": "PropertyValue", "name": "IČO", "value": "12345678" },
    { "@type": "PropertyValue", "name": "DIČ", "value": "CZ12345678" }
  ]
}</pre>

			<h3><?php esc_html_e( 'Co je NAP a proč záleží na konzistenci?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'NAP = Name, Address, Phone. Vyhledávače křížově porovnávají kontaktní informace z různých zdrojů (web, Google Business Profile, výpisy v rejstřících, citace). Pokud jsou data nekonzistentní (různé formáty telefonu, různé adresní varianty), důvěryhodnost lokální entity klesá.', 'seo-boost' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'NAP scan v tomto modulu prohledá obsah webu a najde výskyty telefonu se zvláštní pozorností na různé formáty zápisu (např. 123 456 789 vs. +420123456789 vs. 123-456-789). Cílem je ujistit se, že na webu používáte konzistentně stejný formát jako v JSON-LD.', 'seo-boost' ); ?>
			</p>

			<h3><?php esc_html_e( 'Kde vkládat JSON-LD?', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Možnost', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Kdy použít', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Pouze úvodní stránka', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Nejčastější volba pro firmy s jednou pobočkou. Google obvykle crawluje homepage jako "hlavní entitu".', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Kontaktní stránka', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Vhodné pokud kontaktní stránka obsahuje mapu, adresu a otevírací dobu – schéma je kontextově relevantní.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Všechny stránky', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Silnější signál, ale zvyšuje objem HTML. Google stejně bere v potaz jen jeden výstup pro entitu – vhodné spíše pro weby s malým počtem stránek.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Konflikty s jinými pluginy', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Rank Math Free</td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Bez konfliktu – RM Free Local SEO neobsahuje.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td>Rank Math Pro (Local SEO modul)</td>
						<td style="color:red;">&#9888; <?php esc_html_e( 'Konflikt – RM Pro Local SEO modul spravuje LocalBusiness schéma. Tento modul se automaticky deaktivuje.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td>Yoast SEO Free</td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Bez konfliktu – Yoast Free nevkládá LocalBusiness.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td>Yoast Local SEO</td>
						<td style="color:red;">&#9888; <?php esc_html_e( 'Potenciální konflikt – pokud máte aktivní Yoast Local SEO plugin, může docházet k duplicitním JSON-LD blokům. Deaktivujte jeden z nich.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Validace a testování', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Po uložení nastavení klikněte na "Náhled JSON-LD" a zkopírujte výstup do:', 'seo-boost' ); ?>
			</p>
			<ul>
				<li><a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validátor</a> – základní validace struktury</li>
				<li><a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a> – ověří, zda Google schéma rozpozná</li>
			</ul>
		</div>
	</details>
</div>
