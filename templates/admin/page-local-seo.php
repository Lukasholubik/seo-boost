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
				<strong><?php esc_html_e( 'Automaticky deaktivováno – Rank Math Pro Local SEO:', 'seo-boost' ); ?></strong>
				<?php esc_html_e( 'Rank Math Pro Local SEO modul je aktivní a spravuje LocalBusiness schéma automaticky a globálně. Tento modul proto nevkládá nic, aby nevznikly duplicitní JSON-LD bloky. Pokud chceš používat tento modul místo RM Pro Local SEO, deaktivuj Local SEO modul v Rank Math → Nastavení → Moduly.', 'seo-boost' ); ?>
			</p>
		</div>
	<?php elseif ( SEOB_LocalSeo_Frontend::rank_math_free_has_local_business_schema() ) : ?>
		<div class="notice notice-warning" id="seob-local-seo-rm-free-banner">
			<p>
				<strong><?php esc_html_e( 'Pozor – Rank Math Free má nakonfigurované LocalBusiness schéma:', 'seo-boost' ); ?></strong>
				<?php esc_html_e( 'Rank Math Free Schema modul má pro jeden nebo více typů obsahu nastaven typ schématu LocalBusiness (nebo jeho podtyp). Pokud je tento modul také aktivní, vzniknou na stránce dvě LocalBusiness JSON-LD schémata – to je chyba. Ověřte kliknutím na „Náhled JSON-LD" a pak zkontrolujte výstup stránky v Google Rich Results Test. Vypněte buď schéma v Rank Math (Rank Math → Títuly a meta → typ obsahu → záložka Schema) nebo deaktivujte tento modul.', 'seo-boost' ); ?>
			</p>
		</div>
	<?php elseif ( SEOB_LocalSeo_Frontend::has_rank_math_free() ) : ?>
		<div class="notice notice-info" id="seob-local-seo-rm-free-info">
			<p>
				<strong><?php esc_html_e( 'Rank Math Free je aktivní:', 'seo-boost' ); ?></strong>
				<?php esc_html_e( 'Rank Math Free Schema modul LocalBusiness JSON-LD také umí – globálně (Rank Math → Títuly a meta → typ obsahu → záložka Schema) nebo per-stránka (záložka „Rich Snippets" v editoru). Pokud tuto funkci v RM zatím nepoužíváte, je vše v pořádku a tento modul vloží schéma za vás. Pokud v RM LocalBusiness schéma máte nastavené, ověřte výstup stránky v Google Rich Results Test, zda nevznikají duplicity.', 'seo-boost' ); ?>
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
		<summary><?php esc_html_e( 'Jak Local SEO nastavit a proč to má smysl – kompletní průvodce (klikněte)', 'seo-boost' ); ?></summary>
		<div class="seob-schema-help-body">

			<h3><?php esc_html_e( 'Proč vůbec Local SEO řešit?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Lokální vyhledávání je pro fyzické provozovny a firmy poskytující služby v konkrétní oblasti jeden z nejsilnějších zdrojů zákazníků. Přes 46 % všech Google vyhledávání má lokální záměr – uživatel hledá "instalatér Praha", "zubař Brno Vinohrady" nebo "kavárna blízko mě". Abyste se v těchto výsledcích dobře umístili, potřebujete správně předat Googlu informaci, kdo jste, kde jste a co nabízíte.', 'seo-boost' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Strukturovaná data LocalBusiness jsou tím nejpřímějším způsobem, jak tuto informaci vyhledávači sdělit. Nejde o magický trik – jde o hygienický základ, bez kterého řada věcí (Knowledge Panel, rich results s otevírací dobou, propojení s Mapami) jednoduše nefunguje.', 'seo-boost' ); ?>
			</p>

			<h4><?php esc_html_e( 'Konkrétní přínosy správně nastaveného schématu:', 'seo-boost' ); ?></h4>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Co to dá', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Proč je to důležité', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Otevírací doba ve výsledcích vyhledávání', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Uživatel vidí přímo ve výsledcích, zda jste otevřeni – zvyšuje proklik a snižuje zklamání při příjezdu.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Knowledge Panel (informační panel)', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Google zobrazí vpravo vedle výsledků kartu s adresou, telefonem, fotkami a hodnoceními. Výrazně posiluje důvěryhodnost firmy.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Propojení s Google Business Profile', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Schéma na webu + ověřený GBP profil = silnější signal lokální přítomnosti. Google propojuje obě entity a zobrazuje firmu v Mapách.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'E-E-A-T (důvěryhodnost entity)', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Strukturovaná data s IČO/DIČ propojená s ARES/OR signalizují Googlu, že entita existuje v reálném světě. Zesiluje autoritu webu ve výsledcích.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Hlasové vyhledávání a asistenti', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Google Asistent, Siri a Alexa čerpají z strukturovaných dat. Dotaz „Kdy zavírají?" nebo „Zavolej [firma]" funguje spolehlivěji.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Rychlý průvodce – jak začít (5 kroků)', 'seo-boost' ); ?></h3>
			<ol style="font-size:14px;line-height:1.8;">
				<li>
					<strong><?php esc_html_e( 'Vyplňte název firmy a typ podnikání', 'seo-boost' ); ?></strong> –
					<?php esc_html_e( 'Název musí souhlasit s tím, jak je firma zapsána v živnostenském/obchodním rejstříku. Typ zvolte co nejpřesnější.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Doplňte telefon ve formátu +420 XXX XXX XXX', 'seo-boost' ); ?></strong> –
					<?php esc_html_e( 'Mezinárodní formát s mezerami. Tento formát pak budete hlídat přes NAP scan, zda ho web konzistentně používá.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Vyplňte adresu a GPS souřadnice', 'seo-boost' ); ?></strong> –
					<?php esc_html_e( 'GPS je klíčové pro zobrazení v Mapách. Souřadnice snadno zkopírujete z mapy.cz (pravý klik → Souřadnice místa) nebo Google Maps.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Přidejte IČO (a DIČ pokud jste plátce DPH)', 'seo-boost' ); ?></strong> –
					<?php esc_html_e( 'Slouží jako jednoznačný identifikátor pro propojení s rejstříky. Obě hodnoty vkládáme jako PropertyValue identifikátory.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Uložte, klikněte na „Náhled JSON-LD" a vložte výstup do Rich Results Test', 'seo-boost' ); ?></strong> –
					<?php esc_html_e( 'Ověřte, že Google schéma správně rozpozná. Pak spusťte NAP scan, zda web nepoužívá různé formáty telefonu.', 'seo-boost' ); ?>
				</li>
			</ol>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Co je LocalBusiness JSON-LD (technické pozadí)', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'JSON-LD (JavaScript Object Notation for Linked Data) je formát strukturovaných dat, který se vkládá jako neviditelný blok do hlavičky HTML stránky. Prohlížeč ho ignoruje, ale vyhledávací roboti ho čtou přednostně. Je to nejčistší způsob předání schématu – nevyžaduje úpravu HTML obsahu stránky a nezávisí na layoutu šablony.', 'seo-boost' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'LocalBusiness je podtyp schema.org Thing → Organization → LocalBusiness. Sděluje vyhledávačům: název firmy, typ podnikání, adresu, telefon, e-mail, GPS polohu, otevírací dobu, logo a identifikátory (IČO, DIČ). Každý vyplněný atribut je jeden krok k lepšímu porozumění entity Googlem.', 'seo-boost' ); ?>
			</p>

			<h4><?php esc_html_e( 'Ukázka výstupu pro firmu ze servisu', 'seo-boost' ); ?></h4>
			<pre style="background:#f0f0f1;padding:12px;overflow:auto;font-size:12px;line-height:1.5;">&lt;script type="application/ld+json"&gt;
{
  "@context": "https://schema.org",
  "@type": "AutoRepair",
  "name": "Autoservis Novák s.r.o.",
  "url": "https://autoservis-novak.cz/",
  "telephone": "+420 777 123 456",
  "email": "info@autoservis-novak.cz",
  "description": "Komplexní servis osobních vozidel v Praze 9.",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Průmyslová 42",
    "addressLocality": "Praha",
    "postalCode": "190 00",
    "addressCountry": "CZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 50.1234,
    "longitude": 14.5678
  },
  "openingHoursSpecification": [
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Monday",    "opens": "07:00", "closes": "17:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Tuesday",   "opens": "07:00", "closes": "17:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Wednesday", "opens": "07:00", "closes": "17:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Thursday",  "opens": "07:00", "closes": "17:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Friday",    "opens": "07:00", "closes": "15:00" }
  ],
  "identifier": [
    { "@type": "PropertyValue", "name": "IČO", "value": "12345678" },
    { "@type": "PropertyValue", "name": "DIČ", "value": "CZ12345678" }
  ],
  "image": "https://autoservis-novak.cz/logo.png",
  "priceRange": "$$"
}
&lt;/script&gt;</pre>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Výběr správného typu podnikání', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Typ podnikání (@type) je jedním z nejdůležitějších atributů. Čím přesnější, tím lépe – Google může zobrazit specifické rich result formáty (např. pro Restaurant zobrazí hodnocení a rezervaci). Nikdy nezvolte obecný LocalBusiness, pokud existuje přesnější podtyp.', 'seo-boost' ); ?>
			</p>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Typ schema.org', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Kdy použít', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td>LocalBusiness</td><td><?php esc_html_e( 'Pouze pokud žádný přesnější typ neodpovídá. Nejméně specifický.', 'seo-boost' ); ?></td></tr>
					<tr><td>ProfessionalService</td><td><?php esc_html_e( 'IT firmy, konzultanti, marketingové agentury, reklamní agentury.', 'seo-boost' ); ?></td></tr>
					<tr><td>LegalService</td><td><?php esc_html_e( 'Advokáti, notáři, exekutoři.', 'seo-boost' ); ?></td></tr>
					<tr><td>AccountingService</td><td><?php esc_html_e( 'Účetní firmy, daňoví poradci.', 'seo-boost' ); ?></td></tr>
					<tr><td>Dentist</td><td><?php esc_html_e( 'Zubní kliniky, ordinace zubního lékaře.', 'seo-boost' ); ?></td></tr>
					<tr><td>Physician / MedicalClinic</td><td><?php esc_html_e( 'Ordinace lékaře, zdravotní klinika, centrum.', 'seo-boost' ); ?></td></tr>
					<tr><td>Restaurant</td><td><?php esc_html_e( 'Restaurace – Google může zobrazit menu, hodnocení a rezervaci.', 'seo-boost' ); ?></td></tr>
					<tr><td>AutoRepair</td><td><?php esc_html_e( 'Autoservisy, pneuservisy.', 'seo-boost' ); ?></td></tr>
					<tr><td>HomeAndConstructionBusiness</td><td><?php esc_html_e( 'Stavební firmy, rekonstrukce, interiérový design.', 'seo-boost' ); ?></td></tr>
					<tr><td>BeautySalon / HairSalon</td><td><?php esc_html_e( 'Kosmetické salony, kadeřnictví, nehtové studio.', 'seo-boost' ); ?></td></tr>
					<tr><td>Hotel / LodgingBusiness</td><td><?php esc_html_e( 'Hotely, penziony, ubytovny – Google zobrazí dostupnost a ceny.', 'seo-boost' ); ?></td></tr>
					<tr><td>RealEstateAgent</td><td><?php esc_html_e( 'Realitní kanceláře a agenti.', 'seo-boost' ); ?></td></tr>
				</tbody>
			</table>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Proč IČO a DIČ? (CZ specifika)', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Google Knowledge Graph propojuje entity z různých zdrojů – web, Google Business Profile, Wikidata, rejstříky. V Česku jsou klíčovými rejstříky ARES (administrativní registr ekonomických subjektů) a Obchodní rejstřík (OR). Pokud Google nalezne IČO na vašem webu a v ARES, může tyto dvě entity ztotožnit a výrazně posílit Knowledge Panel.', 'seo-boost' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Technicky se IČO a DIČ vkládají jako PropertyValue objekty v poli identifier. Toto je standardní způsob schema.org pro identifikátory, které nemají vlastní atribut.', 'seo-boost' ); ?>
			</p>
			<pre style="background:#f0f0f1;padding:10px;overflow:auto;font-size:12px;">"identifier": [
  { "@type": "PropertyValue", "name": "IČO", "value": "12345678" },
  { "@type": "PropertyValue", "name": "DIČ", "value": "CZ12345678" }
]</pre>
			<p>
				<strong><?php esc_html_e( 'Tip:', 'seo-boost' ); ?></strong>
				<?php esc_html_e( 'IČO vyplňte vždy (platí pro OSVČ i s.r.o. a a.s.). DIČ vyplňte pouze pokud jste plátci DPH – neplátci DIČ nemají.', 'seo-boost' ); ?>
			</p>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Jak získat GPS souřadnice', 'seo-boost' ); ?></h3>
			<p><?php esc_html_e( 'GPS souřadnice jsou důležité pro propojení schématu s mapovými službami. Existují tři snadné způsoby:', 'seo-boost' ); ?></p>
			<ol>
				<li>
					<strong>mapy.cz:</strong>
					<?php esc_html_e( 'Najděte provozovnu → pravý klik na bod → „Souřadnice místa" → zkopírujte zeměpisnou šířku (první číslo) a délku (druhé číslo).', 'seo-boost' ); ?>
				</li>
				<li>
					<strong>Google Maps:</strong>
					<?php esc_html_e( 'Klikněte pravým tlačítkem přímo na budovu → první řádek v menu jsou souřadnice ve formátu šířka, délka → klikněte pro zkopírování.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Google Business Profile:', 'seo-boost' ); ?></strong>
					<?php esc_html_e( 'Pokud máte ověřený GBP profil, souřadnice jsou v URL při zobrazení profilu na Mapách (parametr @lat,lng).', 'seo-boost' ); ?>
				</li>
			</ol>
			<p class="description">
				<?php esc_html_e( 'Zeměpisná šířka pro ČR: přibližně 49–51. Zeměpisná délka: přibližně 12–18. Pokud vám vychází jiná čísla, máte prohozené souřadnice.', 'seo-boost' ); ?>
			</p>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Otevírací doba – jak správně vyplnit', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Otevírací doba se vkládá jako pole OpeningHoursSpecification. Každý den je samostatný objekt s atributy dayOfWeek, opens a closes.', 'seo-boost' ); ?>
			</p>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Situace', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Jak nastavit', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Standardní otevírací hodiny', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Vyplňte pole „Otevřeno od" a „Zavřeno v" ve formátu HH:MM (24 hodin). Například 09:00 a 17:00.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Den bez provozu (SO, NE)', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Zaškrtněte „Zavřeno" – pole pro časy se deaktivují a den se do JSON-LD nevloží.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Provoz přes půlnoc (např. bar)', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Zadejte například 18:00 a 02:00. Schema.org tuto situaci zvládá.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Non-stop provoz', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Zadejte 00:00 jako otevření a 23:59 jako zavření pro každý den.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'Poznámka: schéma nepodporuje různé svátky ani sezónní výjimky – pouze standardní týdenní rozvrh.', 'seo-boost' ); ?>
			</p>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Co je NAP konzistence a proč ji hlídat?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'NAP = Name, Address, Phone (Název, Adresa, Telefon). Lokální SEO stojí na principu, že tyto tři informace musí být všude na webu i mimo web (Google Business Profile, online katalogy, sociální sítě) uváděny identicky – stejný formát, stejný zápis.', 'seo-boost' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Problém nastává zejména u telefonních čísel. Na webu se může vyskytovat tentýž telefon v desítkách různých zápisů:', 'seo-boost' ); ?>
			</p>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Zápis telefonu', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Doporučeno?', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Poznámka', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>+420 777 123 456</code></td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Ano', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Mezinárodní formát s mezerami – nejčitelnější, používejte na webu i v JSON-LD.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><code>+420777123456</code></td>
						<td style="color:orange;">&#9888; <?php esc_html_e( 'Částečně', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Mezinárodní formát bez mezer – NAP scan to označí jako neshodný formát, ale číslo je správné.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><code>777 123 456</code></td>
						<td style="color:orange;">&#9888; <?php esc_html_e( 'Slabší', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Bez předvolby – pro lokální použití OK, ale v JSON-LD doporučujeme vždy s +420.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><code>777-123-456</code></td>
						<td style="color:red;">&#10007; <?php esc_html_e( 'Ne', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Pomlčky jako oddělovač – nekonzistentní formát, nahraďte mezerami.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><code>777.123.456</code></td>
						<td style="color:red;">&#10007; <?php esc_html_e( 'Ne', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Tečky jako oddělovač – neobvyklý formát v CZ prostředí.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<?php esc_html_e( 'NAP scan prohledá obsah všech publikovaných příspěvků a stránek a najde výskyty vašeho telefonního čísla (ve všech formátech). Výsledky přehledně ukáže, kde je formát odlišný od referenčního zápisu z nastavení. Opravte neshodné výskyty přímo klikem na „Upravit".', 'seo-boost' ); ?>
			</p>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Kde vkládat JSON-LD schéma?', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Možnost', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Pro koho', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Výhody / nevýhody', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Pouze úvodní stránka', 'seo-boost' ); ?></strong></td>
						<td><?php esc_html_e( 'Většina firem s jednou provozovnou', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Google crawluje homepage jako hlavní entitu. Schéma je přítomno tam, kde uživatel nejčastěji „přijde". Doporučeno.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Kontaktní stránka', 'seo-boost' ); ?></strong></td>
						<td><?php esc_html_e( 'Weby s oddělenou /kontakt stránkou obsahující mapu', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Kontextově relevantnější – schéma je na stránce, která adresu a provozovnu fyzicky prezentuje. Dobré alternativní řešení.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Všechny stránky', 'seo-boost' ); ?></strong></td>
						<td><?php esc_html_e( 'Malé weby (3–10 stránek)', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Silnější signal, Google schéma vidí při crawlu každé stránky. Na velkých webech zbytečně zvyšuje objem HTML a nemá přidanou hodnotu.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Validace a testování', 'seo-boost' ); ?></h3>
			<p><?php esc_html_e( 'Vždy ověřte schéma po prvním nastavení i po každé změně. Doporučený postup:', 'seo-boost' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'Klikněte na „Uložit nastavení".', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Klikněte na „Náhled JSON-LD" – zobrazí se vygenerovaný blok.', 'seo-boost' ); ?></li>
				<li>
					<?php esc_html_e( 'Zkopírujte JSON a vložte do ', 'seo-boost' ); ?>
					<a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validátoru</a>
					<?php esc_html_e( ' – ověřte strukturu.', 'seo-boost' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Otevřete ', 'seo-boost' ); ?>
					<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a>
					<?php esc_html_e( ' a zadejte URL vaší úvodní stránky – Google ukáže, zda schéma rozpoznal.', 'seo-boost' ); ?>
				</li>
				<li><?php esc_html_e( 'Spusťte NAP scan a opravte případné neshodné formáty telefonu.', 'seo-boost' ); ?></li>
			</ol>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Konflikty s jinými pluginy', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Klíčové je pochopit rozdíl mezi Rank Math Free a Rank Math Pro. RM Free Schema modul LocalBusiness umí, ale jen pokud ho explicitně nastavíte. RM Pro Local SEO modul ho nastavuje automaticky a globálně – to je pravý konflikt.', 'seo-boost' ); ?>
			</p>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Co to znamená v praxi', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>Rank Math Free</strong><br><small><?php esc_html_e( 'bez nastaveného LocalBusiness schématu', 'seo-boost' ); ?></small></td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Bez konfliktu', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Nejběžnější scénář. RM Free se stará o meta tagy a jiné typy schémat – LocalBusiness vloží tento modul. V pořádku.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong>Rank Math Free</strong><br><small><?php esc_html_e( 's nastaveným LocalBusiness schématem (Títuly a meta → Schema)', 'seo-boost' ); ?></small></td>
						<td style="color:orange;">&#9888; <?php esc_html_e( 'Pozor – ověřte', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'RM Free může LocalBusiness JSON-LD vkládat také. Vzniknou dvě schémata na jedné stránce. Ověřte v Google Rich Results Test a vypněte jedno z nich – buď schéma v RM Free (Rank Math → Títuly a meta → typ obsahu → záložka Schema → přepněte na None) nebo deaktivujte tento modul.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong>Rank Math Pro</strong><br><small><?php esc_html_e( 'Local SEO modul aktivní', 'seo-boost' ); ?></small></td>
						<td style="color:red;">&#10007; <?php esc_html_e( 'Konflikt – auto. deaktivováno', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'RM Pro Local SEO modul spravuje LocalBusiness schéma automaticky, globálně a s pokročilými funkcemi (více poboček, store locator). Tento modul se sám deaktivuje a nevloží nic. Pokud přesto chcete tento modul, deaktivujte Local SEO modul v RM Pro → Nastavení → Moduly.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong>Yoast SEO Free</strong></td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Bez konfliktu', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Yoast Free LocalBusiness nevkládá. Plně kompatibilní.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong>Yoast Local SEO</strong><br><small><?php esc_html_e( 'samostatný premium plugin', 'seo-boost' ); ?></small></td>
						<td style="color:orange;">&#9888; <?php esc_html_e( 'Pozor – ověřte', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Yoast Local SEO plugin LocalBusiness JSON-LD produkuje. Pokud ho máte, deaktivujte tento modul.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><strong>WPML, Polylang, TranslatePress</strong></td>
						<td style="color:green;">&#10003; <?php esc_html_e( 'Bez konfliktu', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Vícejazyčné pluginy schéma neovlivňují.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'Jak ověřit, zda nemáte duplicitní schéma: otevřete zdrojový kód stránky (Ctrl+U) a vyhledejte „LocalBusiness". Pokud se výsledek objeví dvakrát, máte duplicitu.', 'seo-boost' ); ?>
			</p>

			<hr style="margin:24px 0;">

			<h3><?php esc_html_e( 'Časté chyby a jak je opravit', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Chyba', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Příčina', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Řešení', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'JSON-LD se nevkládá do stránky', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Chybí název firmy nebo je modul deaktivován z důvodu konfliktu.', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Vyplňte název firmy a uložte. Zkontrolujte banner konfliktu nahoře.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Schéma prochází Schema.org validátorem, ale NE Rich Results Testem', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Chybí povinné atributy pro konkrétní typ schématu (např. Restaurant vyžaduje servesCuisine pro rich results).', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Doplňte popis firmy. Pro speciální typy může být potřeba ručně dodat atributy přes theme hook.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Google nezobrazuje Knowledge Panel', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Knowledge Panel buduje Google postupně na základě všech signálů, ne jen schématu. Vyžaduje čas (týdny až měsíce).', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Ověřte a propojte Google Business Profile, zajistěte citace v kvalitních adresářích (Firmy.cz, Zlaté stránky), zajistěte konzistentní NAP.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'NAP scan hlásí neshodné formáty, ale nevím kde', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Telefon je na webu zadán různě v různých místech (zápatí, kontaktní stránka, patička článků).', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Klikněte na „Upravit" u problematické stránky a sjednoťte formát na ten z nastavení tohoto modulu.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Otevírací doba se v JSON-LD nevyskytuje', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Pro všechny dny jsou prázdná pole bez zatrhnutí „Zavřeno".', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Vyplňte konkrétní časy ve formátu HH:MM. Dny bez provozu zaškrtněte jako „Zavřeno".', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

		</div>
	</details>
</div>
