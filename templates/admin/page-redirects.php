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
	<p style="color:#666;margin-bottom:12px;">
		<?php esc_html_e( 'Nahrajte CSV soubor se dvěma sloupci: zdrojová cesta a cíl přesměrování. Oddělovač čárka nebo středník, hlavička volitelná.', 'seo-boost' ); ?>
	</p>
	<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:16px 20px;margin-bottom:24px;max-width:680px;">
		<code style="display:block;background:#fff;border:1px solid #dcdcde;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#444;">/stara-stranka/,/nova-stranka/<br>/dalsi-stranka/,https://externi-web.cz/cil/<br>/kategorie/produkt/,/nove-kategorie/produkt/</code>
		<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<input type="file" id="seob-csv-file" accept=".csv,text/csv,text/plain" style="flex:1;min-width:240px;">
			<button type="button" id="seob-import-csv" class="button button-primary"><?php esc_html_e( 'Importovat', 'seo-boost' ); ?></button>
		</div>
		<div id="seob-import-result" style="margin-top:12px;display:none;"></div>
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
