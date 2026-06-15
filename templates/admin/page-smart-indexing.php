<?php
/**
 * Chytrá indexace katalogových a filtrovaných stránek (M14).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$si     = SEOB_Settings::get( SEOB_Settings::SMART_INDEXING );
$active = SEOB_Module_Manager::is_active( 'smart-indexing' );
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Chytrá indexace', 'seo-boost' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Modul vyhodnocuje kombinace oborů, služeb a lokalit (katalog) nebo facetů (e-shop) a navrhuje, které stránky indexovat jako landing page a které naopak chránit před index bloatem (noindex / canonical / blokace parametrů).', 'seo-boost' ); ?>
	</p>

	<?php if ( ! $active ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: odkaz na Nastavení */
					esc_html__( 'Modul „Chytrá indexace“ je vypnutý. Nastavení si můžete připravit, ale analýza ani úprava robots/canonical poběží až po zapnutí modulu v %s → Moduly (nebo tlačítkem „Zapnout“ na stránce Stav systému).', 'seo-boost' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=seob-settings' ) ) . '">' . esc_html__( 'Nastavení', 'seo-boost' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<details class="seob-schema-help">
		<summary><?php esc_html_e( 'Jak modul funguje – Tier A/B/C, skóre a doporučení (klikněte pro zobrazení)', 'seo-boost' ); ?></summary>
		<div class="seob-schema-help-body">
			<p><?php esc_html_e( 'Modul analyzuje katalog (detaily firem a kombinace obor × lokalita / služba × lokalita) a každou stránku/kombinaci zařadí do jedné ze tří úrovní:', 'seo-boost' ); ?></p>
			<ul>
				<li><strong>Tier A</strong> – <?php esc_html_e( 'navrhuje se k indexaci (samostatná landing page má smysl – dost firem nebo úplný profil).', 'seo-boost' ); ?></li>
				<li><strong>Tier B</strong> – <?php esc_html_e( '"noindex, follow" – stránka existuje a je procházená, ale do výsledků vyhledávání nepatří (málo firem / nedokončený profil / kandidát k ručnímu posouzení).', 'seo-boost' ); ?></li>
				<li><strong>Tier C</strong> – <?php esc_html_e( '"canonical" na čistou URL bez technických parametrů (řešeno polem "Zakázané parametry" níže, nikdy se nekombinuje s noindexem).', 'seo-boost' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Tabulka "Přehled SEO příležitostí" níže zobrazuje pro každý řádek:', 'seo-boost' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'URL / stránka', 'seo-boost' ); ?></strong> – <?php esc_html_e( 'reálná stránka (detail firmy, hlavní obor) nebo navržená URL pro kombinaci obor × lokalita / služba × lokalita. U navržených kombinací nemusí stránka ještě fyzicky existovat – jde o podklad pro rozhodnutí, ne o automatické vytvoření stránky.', 'seo-boost' ); ?></li>
				<li><strong><?php esc_html_e( 'Typ', 'seo-boost' ); ?></strong> – <?php esc_html_e( 'detail firmy, obor, obor + lokalita, nebo služba + lokalita.', 'seo-boost' ); ?></li>
				<li><strong><?php esc_html_e( 'Firem', 'seo-boost' ); ?></strong> – <?php esc_html_e( 'počet firem, které danou kombinaci splňují (u detailu firmy se nezobrazuje).', 'seo-boost' ); ?></li>
				<li><strong><?php esc_html_e( 'Skóre', 'seo-boost' ); ?></strong> – <?php esc_html_e( 'orientační 0–100 (poměr k prahu "Min. počet firem" / úplnost profilu firmy). V MVP nezahrnuje data z Google Search Console.', 'seo-boost' ); ?></li>
				<li><strong><?php esc_html_e( 'Doporučení', 'seo-boost' ); ?></strong> – <?php esc_html_e( 'aktuální vyhodnocení (Tier + důvod) v textové podobě.', 'seo-boost' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Tlačítka "Schválit (index)" / "Noindex" u řádku přepíší tier na A, resp. B a označí řádek jako ruční rozhodnutí – při dalším "Spustit analýzu" se už nepřepíše automaticky (přepočítají se jen počty firem a skóre). Pokud chcete ruční rozhodnutí zrušit a vrátit se k automatickému vyhodnocení, klikněte na opačné tlačítko a poté znovu na to, které odpovídá automatickému výsledku.', 'seo-boost' ); ?></p>
			<p class="description"><?php esc_html_e( 'V režimu Dry-run se Schválit/Noindex i analýza jen ukládají do databáze – na frontendu (canonical/robots) se nic nemění, dokud nepřepnete režim na "Poloautomat" nebo "Automat".', 'seo-boost' ); ?></p>
		</div>
	</details>

	<form id="seob-smart-indexing-settings-form">
		<h2><?php esc_html_e( 'Profil a režim', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-si-profile"><?php esc_html_e( 'Profil webu', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-profile" name="profile">
						<option value="catalog" <?php selected( $si['profile'], 'catalog' ); ?>><?php esc_html_e( 'Katalog (obor / služba / lokalita)', 'seo-boost' ); ?></option>
						<option value="eshop" <?php selected( $si['profile'], 'eshop' ); ?>><?php esc_html_e( 'E-shop (kategorie / facety)', 'seo-boost' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Informativní volba pro budoucí rozšíření (specifická pravidla pro e-shopové facety). V aktuální verzi modul vždy používá katalogovou logiku (mapování níže) bez ohledu na tuto volbu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-mode"><?php esc_html_e( 'Režim', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-mode" name="mode">
						<option value="dry_run" <?php selected( $si['mode'], 'dry_run' ); ?>><?php esc_html_e( 'Dry-run (jen sbírá data a navrhuje, nic nemění)', 'seo-boost' ); ?></option>
						<option value="semi_auto" <?php selected( $si['mode'], 'semi_auto' ); ?>><?php esc_html_e( 'Poloautomat (návrhy schvaluje admin, canonical/noindex se aplikují)', 'seo-boost' ); ?></option>
						<option value="auto" <?php selected( $si['mode'], 'auto' ); ?>><?php esc_html_e( 'Automat (skóre ≥ 80 se povyšuje automaticky)', 'seo-boost' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'V režimu Dry-run se neaplikují žádné canonical/robots úpravy na frontendu – pouze se naplní tabulka návrhů níže.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Mapování katalogu', 'seo-boost' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Vyberte, který typ obsahu představuje detail firmy/profilu a které taxonomie odpovídají oboru, lokalitě a (volitelně) službě. Vše je nepovinné – analýza vyhodnotí jen ty kombinace, pro které je mapování vyplněné.', 'seo-boost' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-si-company-post-type"><?php esc_html_e( 'Detail firmy / profilu (typ obsahu)', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-company-post-type" name="company_post_type" data-current="<?php echo esc_attr( $si['company_post_type'] ); ?>"><option value=""><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></option></select>
					<p class="description"><?php esc_html_e( 'Pokud vyplněno, analýza projde všechny publikované příspěvky tohoto typu a spočítá skóre úplnosti profilu (obsah, náhledový obrázek, perex, zařazení do oboru/lokality) → návrh Tier A (index) nebo Tier B (noindex, "nedokončený profil") podle prahu "Práh úplnosti profilu firmy" níže.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-category-taxonomy"><?php esc_html_e( 'Obor / kategorie (taxonomie)', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-category-taxonomy" name="category_taxonomy" data-current="<?php echo esc_attr( $si['category_taxonomy'] ); ?>"><option value=""><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></option></select>
					<p class="description"><?php esc_html_e( 'Pokud vyplněno, každý term této taxonomie se navrhne jako Tier A ("hlavní obor" – vždy index). V kombinaci s "Lokalita" níže se navíc vyhodnotí kombinace obor × lokalita.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-location-taxonomy"><?php esc_html_e( 'Lokalita (taxonomie)', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-location-taxonomy" name="location_taxonomy" data-current="<?php echo esc_attr( $si['location_taxonomy'] ); ?>"><option value=""><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></option></select>
					<p class="description"><?php esc_html_e( 'Samostatně se nevyhodnocuje – má smysl jen v kombinaci s "Obor / kategorie" a/nebo "Služba" výše/níže (kombinace obor × lokalita, služba × lokalita).', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-service-taxonomy"><?php esc_html_e( 'Služba (taxonomie, volitelné)', 'seo-boost' ); ?></label></th>
				<td>
					<select id="seob-si-service-taxonomy" name="service_taxonomy" data-current="<?php echo esc_attr( $si['service_taxonomy'] ); ?>"><option value=""><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></option></select>
					<p class="description"><?php esc_html_e( 'Pokud vyplněno SOUČASNĚ s "Lokalita", vyhodnotí se i kombinace služba × lokalita. Tyto kombinace se navrhují vždy jako Tier B – kandidát k ručnímu posouzení (modul je nepovyšuje automaticky).', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Prahy a pravidla', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-si-min-companies"><?php esc_html_e( 'Min. počet firem pro index (obor × město)', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-si-min-companies" name="min_companies" min="1" max="1000" value="<?php echo esc_attr( (int) $si['min_companies'] ); ?>">
					<p class="description"><?php esc_html_e( 'Kombinace obor × lokalita s alespoň tímto počtem firem se navrhne k indexaci (Tier A). Méně firem = kandidát nebo noindex.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-completeness"><?php esc_html_e( 'Práh úplnosti profilu firmy (%)', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-si-completeness" name="completeness_threshold" min="0" max="100" value="<?php echo esc_attr( (int) $si['completeness_threshold'] ); ?>">
					<p class="description"><?php esc_html_e( 'Detail firmy s nižší úplností (obsah, foto, perex, obor, lokalita) se navrhne jako noindex.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-si-max-depth"><?php esc_html_e( 'Maximální hloubka kombinace', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-si-max-depth" name="max_depth" min="1" max="4" value="<?php echo esc_attr( (int) $si['max_depth'] ); ?>">
					<p class="description"><?php esc_html_e( 'Kombinace nad tuto hloubku (např. obor + služba + lokalita + filtr) jdou vždy jen do ruční fronty ke schválení.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Zakázané parametry (blacklist)', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-si-blacklist"><?php esc_html_e( 'Parametry', 'seo-boost' ); ?></label></th>
				<td>
					<textarea id="seob-si-blacklist" name="blacklist_params" rows="3" class="large-text"><?php echo esc_textarea( $si['blacklist_params'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Seznam oddělený čárkou. Podporuje hvězdičku jako wildcard (např. utm_*). URL s těmito parametry dostanou canonical na čistou URL bez nich (mimo režim Dry-run).', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" id="seob-si-save-settings" class="button button-primary"><?php esc_html_e( 'Uložit nastavení', 'seo-boost' ); ?></button>
			<span id="seob-si-settings-status" class="seob-save-status"></span>
		</p>
	</form>

	<h2><?php esc_html_e( 'Přehled SEO příležitostí', 'seo-boost' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Analýza spočítá počty firem pro hlavní obory, kombinace obor × lokalita a (pokud je nakonfigurováno) služba × lokalita, a vyhodnotí detaily firem podle úplnosti profilu.', 'seo-boost' ); ?></p>

	<p>
		<button type="button" id="seob-si-run-scan" class="button button-primary"><?php esc_html_e( 'Spustit analýzu', 'seo-boost' ); ?></button>
		<span id="seob-si-scan-status" class="seob-save-status"></span>
	</p>

	<table class="wp-list-table widefat fixed seob-table seob-audit-table seob-smart-table">
		<thead>
			<tr>
				<th class="seob-col-url"><?php esc_html_e( 'URL / stránka', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Typ', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Firem', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Skóre', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Doporučení', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-si-results-body">
			<tr class="seob-empty-row">
				<td colspan="6"><?php esc_html_e( 'Zatím žádná analýza. Spusťte ji tlačítkem výše.', 'seo-boost' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>

<template id="seob-si-row-template">
	<tr>
		<td class="seob-col-url"></td>
		<td class="seob-si-type"></td>
		<td class="seob-si-count seob-col-score"></td>
		<td class="seob-si-score seob-col-score"></td>
		<td class="seob-si-recommendation"></td>
		<td class="seob-col-action">
			<button type="button" class="button seob-si-approve"><?php esc_html_e( 'Schválit (index)', 'seo-boost' ); ?></button>
			<button type="button" class="button seob-si-demote"><?php esc_html_e( 'Noindex', 'seo-boost' ); ?></button>
		</td>
	</tr>
</template>
