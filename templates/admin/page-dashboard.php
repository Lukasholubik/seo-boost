<?php
/**
 * SEO Audit Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Audit Dashboard', 'seo-boost' ); ?></h1>

	<div class="seob-toolbar">
		<button type="button" id="seob-run-scan" class="button button-primary">
			<?php esc_html_e( 'Spustit nový scan', 'seo-boost' ); ?>
		</button>
		<span id="seob-scan-meta" class="seob-scan-meta"></span>
	</div>

	<div id="seob-progress" class="seob-progress" hidden>
		<div class="seob-progress-bar"><div id="seob-progress-fill" class="seob-progress-fill"></div></div>
		<span id="seob-progress-text" class="seob-progress-text"></span>
	</div>

	<div id="seob-summary" class="seob-summary"></div>

	<div class="seob-filters">
		<label>
			<?php esc_html_e( 'Filtr:', 'seo-boost' ); ?>
			<select id="seob-filter-severity">
				<option value=""><?php esc_html_e( 'Vše', 'seo-boost' ); ?></option>
				<option value="critical"><?php esc_html_e( 'Jen kritické', 'seo-boost' ); ?></option>
				<option value="warning"><?php esc_html_e( 'Jen varování', 'seo-boost' ); ?></option>
				<option value="recommendation"><?php esc_html_e( 'Jen doporučení', 'seo-boost' ); ?></option>
			</select>
		</label>
		<input type="search" id="seob-filter-search" placeholder="<?php esc_attr_e( 'Hledat URL nebo titulek…', 'seo-boost' ); ?>">
	</div>

	<table class="wp-list-table widefat fixed striped seob-table">
		<thead>
			<tr>
				<th class="seob-col-url"><?php esc_html_e( 'Stránka', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Skóre', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Title', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Description', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'H1', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Alt texty', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Schéma', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-results-body">
			<tr class="seob-empty-row">
				<td colspan="8"><?php esc_html_e( 'Zatím žádný scan. Spusťte ho tlačítkem výše.', 'seo-boost' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>

<template id="seob-row-template">
	<tr class="seob-result-row">
		<td class="seob-col-url">
			<a class="seob-row-edit-link" href="#" target="_blank" rel="noopener"></a>
		</td>
		<td class="seob-col-score"><span class="seob-score-badge"></span></td>
		<td class="seob-col-title"></td>
		<td class="seob-col-description"></td>
		<td class="seob-col-h1"></td>
		<td class="seob-col-alt"></td>
		<td class="seob-col-schema"></td>
		<td class="seob-col-action">
			<button type="button" class="button seob-toggle-edit"><?php esc_html_e( 'Opravit', 'seo-boost' ); ?></button>
		</td>
	</tr>
	<tr class="seob-edit-row" hidden>
		<td colspan="8">
			<div class="seob-edit-panel">
				<div class="seob-field">
					<label><?php esc_html_e( 'SERP Title', 'seo-boost' ); ?></label>
					<input type="text" class="seob-input-title" maxlength="200">
					<span class="seob-pixel-meter seob-pixel-title"><span class="seob-pixel-fill"></span></span>
					<span class="seob-pixel-label"></span>
				</div>
				<div class="seob-field">
					<label><?php esc_html_e( 'Meta description', 'seo-boost' ); ?></label>
					<textarea class="seob-input-description" rows="2" maxlength="400"></textarea>
					<span class="seob-pixel-meter seob-pixel-description"><span class="seob-pixel-fill"></span></span>
					<span class="seob-pixel-label"></span>
				</div>
				<div class="seob-serp-preview">
					<div class="seob-serp-title"></div>
					<div class="seob-serp-url"></div>
					<div class="seob-serp-description"></div>
				</div>
				<div class="seob-issue-list"></div>
				<div class="seob-edit-actions">
					<button type="button" class="button button-primary seob-save-meta"><?php esc_html_e( 'Uložit', 'seo-boost' ); ?></button>
					<button type="button" class="button seob-cancel-edit"><?php esc_html_e( 'Zavřít', 'seo-boost' ); ?></button>
					<span class="seob-save-status"></span>
				</div>
			</div>
		</td>
	</tr>
</template>
