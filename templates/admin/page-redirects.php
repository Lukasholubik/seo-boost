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

	<!-- Nové přesměrování -->
	<h2><?php esc_html_e( 'Nové přesměrování', 'seo-boost' ); ?></h2>
	<div class="seob-redirect-form">
		<input type="text" id="seob-new-source" placeholder="<?php esc_attr_e( '/stara-adresa/', 'seo-boost' ); ?>">
		<span class="seob-arrow">→</span>
		<input type="text" id="seob-new-target" placeholder="<?php esc_attr_e( '/nova-adresa/', 'seo-boost' ); ?>" value="/">
		<button type="button" id="seob-add-redirect" class="button button-primary"><?php esc_html_e( 'Vytvořit přesměrování', 'seo-boost' ); ?></button>
		<span id="seob-add-status" class="seob-save-status"></span>
	</div>

	<!-- Rychlé cíle -->
	<div style="margin:8px 0 20px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
		<span style="font-size:12px;color:#50575e;white-space:nowrap"><?php esc_html_e( 'Rychlé cíle:', 'seo-boost' ); ?></span>
		<button type="button" class="button seob-quick-target" data-url="/" style="font-size:12px;padding:2px 10px;height:auto">🏠 HP (/)</button>
		<button type="button" class="button seob-quick-target" data-url="/kontakt/" style="font-size:12px;padding:2px 10px;height:auto">/kontakt/</button>
		<button type="button" class="button seob-quick-target" data-url="/blog/" style="font-size:12px;padding:2px 10px;height:auto">/blog/</button>
		<button type="button" class="button seob-quick-target" data-url="/slovnik/" style="font-size:12px;padding:2px 10px;height:auto">/slovnik/</button>
		<button type="button" id="seob-load-pages" class="button" style="font-size:12px;padding:2px 10px;height:auto">
			↓ <?php esc_html_e( 'Načíst stránky webu', 'seo-boost' ); ?>
		</button>
		<span id="seob-pages-list" style="display:flex;gap:6px;flex-wrap:wrap;"></span>
	</div>

	<!-- Aktivní přesměrování -->
	<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
		<h2 style="margin:0"><?php esc_html_e( 'Aktivní přesměrování', 'seo-boost' ); ?></h2>
		<div style="display:flex;gap:8px;">
			<button type="button" id="seob-export-csv" class="button">
				⬇ <?php esc_html_e( 'Export CSV', 'seo-boost' ); ?>
			</button>
			<button type="button" id="seob-export-htaccess" class="button">
				⬇ <?php esc_html_e( 'Export .htaccess', 'seo-boost' ); ?>
			</button>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:32px"><input type="checkbox" id="seob-redirects-check-all" title="Vybrat vše"></th>
				<th><?php esc_html_e( 'Zdroj', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Cíl', 'seo-boost' ); ?></th>
				<th style="width:60px"><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-redirects-body">
			<tr class="seob-empty-row"><td colspan="5"><?php esc_html_e( 'Žádná přesměrování zatím nejsou nastavena.', 'seo-boost' ); ?></td></tr>
		</tbody>
	</table>

	<!-- Bulk akce pro přesměrování -->
	<div id="seob-redirects-bulk-bar" style="display:none;margin-top:8px;padding:8px 12px;background:#f0f6fc;border:1px solid #c3d9ee;border-radius:4px;display:flex;align-items:center;gap:10px;">
		<span id="seob-redirects-bulk-count" style="font-size:13px;color:#1d2327;"></span>
		<button type="button" id="seob-redirects-bulk-delete" class="button" style="color:#b32d2e;border-color:#b32d2e;">
			🗑 <?php esc_html_e( 'Smazat vybrané', 'seo-boost' ); ?>
		</button>
	</div>

	<hr>

	<!-- Hromadný import z CSV -->
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

		<div id="seob-import-result" style="display:none;margin-top:16px;"></div>
	</div>

	<!-- Zaznamenané 404 -->
	<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
		<h2 style="margin:0"><?php esc_html_e( 'Zaznamenané 404', 'seo-boost' ); ?></h2>
		<div style="display:flex;align-items:center;gap:8px;">
			<label style="font-size:13px;color:#50575e;">
				<?php esc_html_e( 'Min. zásahů:', 'seo-boost' ); ?>
				<input type="number" id="seob-404-min-hits" value="1" min="1" max="9999"
					style="width:60px;margin-left:4px;" title="Zobrazit jen záznamy s alespoň N zásahy">
			</label>
			<button type="button" id="seob-404-filter-btn" class="button" style="font-size:12px">
				<?php esc_html_e( 'Filtrovat', 'seo-boost' ); ?>
			</button>
			<span id="seob-404-filter-info" style="font-size:12px;color:#50575e;"></span>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:32px"><input type="checkbox" id="seob-404-check-all" title="Vybrat vše"></th>
				<th><?php esc_html_e( 'URL', 'seo-boost' ); ?></th>
				<th style="width:100px"><?php esc_html_e( 'Počet zásahů', 'seo-boost' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'Poslední výskyt', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Přesměrovat na', 'seo-boost' ); ?></th>
				<th style="width:220px"><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-404-body">
			<tr class="seob-empty-row"><td colspan="6"><?php esc_html_e( 'Zatím nebyly zaznamenány žádné 404 chyby.', 'seo-boost' ); ?></td></tr>
		</tbody>
	</table>

	<!-- Bulk akce pro 404 -->
	<div id="seob-404-bulk-bar" style="display:none;margin-top:8px;padding:8px 12px;background:#f0f6fc;border:1px solid #c3d9ee;border-radius:4px;">
		<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
			<span id="seob-404-bulk-count" style="font-size:13px;color:#1d2327;"></span>
			<input type="text" id="seob-404-bulk-target" placeholder="/nova-adresa/" value="/"
				style="width:200px" title="Cíl přesměrování pro vybrané záznamy">
			<button type="button" id="seob-404-bulk-save" class="button button-primary">
				✓ <?php esc_html_e( 'Přesměrovat vybrané', 'seo-boost' ); ?>
			</button>
			<button type="button" id="seob-404-bulk-delete" class="button" style="color:#b32d2e;border-color:#b32d2e;">
				🗑 <?php esc_html_e( 'Smazat vybrané', 'seo-boost' ); ?>
			</button>
		</div>
	</div>

</div>
