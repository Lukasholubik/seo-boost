<?php
/**
 * Redirect Manager + 404 log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Přesměrování', 'seo-boost' ); ?></h1>

	<h2><?php esc_html_e( 'Nové přesměrování', 'seo-boost' ); ?></h2>
	<div class="seob-redirect-form">
		<input type="text" id="seob-new-source" placeholder="<?php esc_attr_e( '/stara-adresa/', 'seo-boost' ); ?>">
		<span class="seob-arrow">→</span>
		<input type="text" id="seob-new-target" placeholder="<?php esc_attr_e( '/nova-adresa/ nebo https://…', 'seo-boost' ); ?>">
		<button type="button" id="seob-add-redirect" class="button button-primary"><?php esc_html_e( 'Vytvořit přesměrování', 'seo-boost' ); ?></button>
		<span id="seob-add-status" class="seob-save-status"></span>
	</div>

	<h2><?php esc_html_e( 'Aktivní přesměrování', 'seo-boost' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Zdroj', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Cíl', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-redirects-body">
			<tr class="seob-empty-row"><td colspan="4"><?php esc_html_e( 'Žádná přesměrování zatím nejsou nastavena.', 'seo-boost' ); ?></td></tr>
		</tbody>
	</table>

	<hr>

	<h2><?php esc_html_e( 'Hromadný import z CSV', 'seo-boost' ); ?></h2>

	<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;margin-bottom:24px;max-width:780px;">

		<p style="margin-top:0;color:#50575e;">
			<?php esc_html_e( 'Nahrajte CSV nebo exportujte z Google Sheets. Sloupec A = zdrojová URL (původní), sloupec B = cílová URL (nová). Povoleny plné URL i relativní cesty. Oddělovač čárka nebo středník, hlavička volitelná.', 'seo-boost' ); ?>
		</p>

		<code style="display:block;background:#fff;border:1px solid #dcdcde;padding:8px 12px;margin-bottom:16px;font-size:12px;color:#444;line-height:1.7;">
			Původní,Nové<br>
			https://reboost.cz/slovicek-pojmu/,https://reboost.cz/slovnik/<br>
			/stara-stranka/,/nova-stranka/<br>
			/blog/old/,https://externi-web.cz/
		</code>

		<!-- Krok 1: výběr souboru + typ -->
		<div id="seob-csv-step1" style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
			<div>
				<label for="seob-csv-file" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'CSV soubor', 'seo-boost' ); ?></label>
				<input type="file" id="seob-csv-file" accept=".csv,.txt,text/csv,text/plain">
			</div>
			<div>
				<label for="seob-http-code" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Typ přesměrování', 'seo-boost' ); ?></label>
				<select id="seob-http-code" style="height:30px;min-width:240px;">
					<option value="301">301 – Trvalé přesměrování (doporučeno pro SEO)</option>
					<option value="302">302 – Dočasné přesměrování</option>
					<option value="307">307 – Dočasné (zachová metodu POST)</option>
					<option value="308">308 – Trvalé (zachová metodu POST)</option>
				</select>
			</div>
			<div>
				<button type="button" id="seob-preview-csv" class="button button-primary"><?php esc_html_e( 'Náhled párování', 'seo-boost' ); ?></button>
			</div>
		</div>

		<!-- Krok 2: náhled tabulky (skrytý dokud není náhled) -->
		<div id="seob-csv-step2" style="display:none;margin-top:20px;">
			<div id="seob-preview-summary" style="margin-bottom:10px;font-weight:600;"></div>
			<div style="overflow-x:auto;max-height:360px;overflow-y:auto;border:1px solid #dcdcde;border-radius:3px;">
				<table class="wp-list-table widefat fixed striped" style="font-size:12px;">
					<thead style="position:sticky;top:0;background:#fff;z-index:1;">
						<tr>
							<th style="width:36px;">#</th>
							<th><?php esc_html_e( 'Zdrojová cesta (původní)', 'seo-boost' ); ?></th>
							<th><?php esc_html_e( 'Cíl přesměrování (nová)', 'seo-boost' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
						</tr>
					</thead>
					<tbody id="seob-preview-body"></tbody>
				</table>
			</div>
			<div style="margin-top:14px;display:flex;gap:12px;align-items:center;">
				<button type="button" id="seob-confirm-import" class="button button-primary"><?php esc_html_e( 'Potvrdit a importovat', 'seo-boost' ); ?></button>
				<button type="button" id="seob-cancel-preview" class="button"><?php esc_html_e( 'Zrušit', 'seo-boost' ); ?></button>
				<span id="seob-import-status" style="color:#666;font-style:italic;"></span>
			</div>
		</div>

		<!-- Výsledek importu -->
		<div id="seob-import-result" style="display:none;margin-top:16px;"></div>

	</div>

	<h2><?php esc_html_e( 'Zaznamenané 404', 'seo-boost' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Počet zásahů', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Poslední výskyt', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Přesměrovat na', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-404-body">
			<tr class="seob-empty-row"><td colspan="5"><?php esc_html_e( 'Zatím nebyly zaznamenány žádné 404 chyby.', 'seo-boost' ); ?></td></tr>
		</tbody>
	</table>
</div>
