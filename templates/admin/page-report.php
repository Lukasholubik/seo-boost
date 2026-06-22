<?php
/**
 * Export reportu – příprava a stažení obchodního PDF z dokončeného auditu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$modules = SEOB_Settings::get( SEOB_Settings::GENERAL )['modules'];

if ( empty( $modules['pdf'] ) ) {
	?>
	<div class="wrap seob-wrap">
		<h1><?php esc_html_e( 'SEO Booster Pro – Export reportu', 'seo-boost' ); ?></h1>
		<p><?php esc_html_e( 'Modul Export PDF reportu je vypnutý. Zapněte jej v Nastavení.', 'seo-boost' ); ?></p>
	</div>
	<?php
	return;
}

$scan_id = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0;
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Export reportu', 'seo-boost' ); ?></h1>

	<div id="seob-report-loading">
		<p><?php esc_html_e( 'Načítám data auditu…', 'seo-boost' ); ?></p>
		<div id="seob-report-progress" class="seob-progress">
			<div class="seob-progress-bar"><div id="seob-report-progress-fill" class="seob-progress-fill"></div></div>
			<span id="seob-report-progress-text" class="seob-progress-text"></span>
		</div>
	</div>
	<div id="seob-report-empty" hidden>
		<p><?php esc_html_e( 'Nebyl nalezen žádný dokončený scan. Spusťte nejprve audit v Audit Dashboardu.', 'seo-boost' ); ?></p>
		<p id="seob-report-error" class="description"></p>
	</div>

	<div id="seob-report-content" hidden>
		<div id="seob-report-summary" class="seob-summary"></div>

		<h2><?php esc_html_e( 'Úvodní shrnutí', 'seo-boost' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Tento text se zobrazí v PDF jako shrnutí auditu. Můžete jej před exportem upravit.', 'seo-boost' ); ?></p>
		<textarea id="seob-report-intro" rows="5" class="large-text"></textarea>

		<h2><?php esc_html_e( 'Nejhorší stránky (detailně)', 'seo-boost' ); ?></h2>
		<p class="description" id="seob-report-pages-note"></p>
		<div id="seob-report-pages"></div>

		<h2><?php esc_html_e( 'Souhrn nálezů podle typu (ostatní stránky)', 'seo-boost' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Tyto nálezy se v PDF zobrazí jako souhrnná tabulka s počtem postižených stránek a přílohou s konkrétními URL, nikoli jako samostatné karty.', 'seo-boost' ); ?></p>
		<div id="seob-report-issue-summary"></div>

		<h2><?php esc_html_e( 'Odhad obchodního dopadu (nepovinné)', 'seo-boost' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Vyplníte-li tato pole, PDF report doplní orientační odhad přínosu zlepšení SEO – jde pouze o ilustrativní výpočet, ne o záruku výsledků.', 'seo-boost' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-report-biz-visits"><?php esc_html_e( 'Měsíční návštěvnost webu', 'seo-boost' ); ?></label></th>
				<td><input type="number" id="seob-report-biz-visits" min="0" step="1" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-report-biz-conversion"><?php esc_html_e( 'Konverzní poměr (%)', 'seo-boost' ); ?></label></th>
				<td><input type="number" id="seob-report-biz-conversion" min="0" step="0.1" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-report-biz-value"><?php esc_html_e( 'Průměrná hodnota objednávky/leadu (Kč)', 'seo-boost' ); ?></label></th>
				<td><input type="number" id="seob-report-biz-value" min="0" step="1" class="regular-text"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Naše nabídka', 'seo-boost' ); ?></h2>
		<p>
			<label for="seob-report-offer-select"><?php esc_html_e( 'Šablona nabídky', 'seo-boost' ); ?></label>
			<select id="seob-report-offer-select"></select>
		</p>
		<p>
			<label for="seob-report-offer-name"><?php esc_html_e( 'Název nabídky', 'seo-boost' ); ?></label><br>
			<input type="text" id="seob-report-offer-name" class="large-text">
		</p>
		<p>
			<label for="seob-report-offer-body"><?php esc_html_e( 'Text nabídky', 'seo-boost' ); ?></label><br>
			<textarea id="seob-report-offer-body" rows="6" class="large-text"></textarea>
		</p>

		<p>
			<button type="button" id="seob-report-download" class="button button-primary"><?php esc_html_e( 'Stáhnout PDF', 'seo-boost' ); ?></button>
			<span id="seob-report-status" class="seob-save-status"></span>
		</p>
	</div>

	<template id="seob-report-page-template">
		<div class="seob-pdf-page">
			<div class="seob-pdf-page-title"></div>
			<div class="seob-pdf-page-url"></div>
			<div class="seob-pdf-issues"></div>
		</div>
	</template>

	<template id="seob-report-issue-template">
		<div class="seob-pdf-issue">
			<div class="seob-pdf-issue-title"></div>
			<div class="seob-pdf-issue-text seob-pdf-issue-impact"></div>
			<div class="seob-pdf-issue-text seob-pdf-issue-benefit"></div>
		</div>
	</template>

	<template id="seob-report-issue-summary-template">
		<details class="seob-pdf-issue-summary">
			<summary class="seob-pdf-issue-summary-title"></summary>
			<div class="seob-pdf-issue-text seob-pdf-issue-impact"></div>
			<div class="seob-pdf-issue-text seob-pdf-issue-benefit"></div>
			<ul class="seob-pdf-issue-summary-pages"></ul>
		</details>
	</template>
</div>
