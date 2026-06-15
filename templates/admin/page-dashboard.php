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
		<label class="seob-scan-history-label">
			<?php esc_html_e( 'Historie scanů:', 'seo-boost' ); ?>
			<select id="seob-scan-history"></select>
		</label>
		<button type="button" id="seob-scan-delete" class="button" title="<?php esc_attr_e( 'Smazat vybraný scan z historie', 'seo-boost' ); ?>">
			<?php esc_html_e( 'Smazat scan', 'seo-boost' ); ?>
		</button>
		<a href="#" id="seob-export-pdf" class="button" target="_blank" rel="noopener" hidden>
			<?php esc_html_e( 'Export PDF', 'seo-boost' ); ?>
		</a>
		<span id="seob-scan-meta" class="seob-scan-meta"></span>
	</div>

	<div id="seob-progress" class="seob-progress" hidden>
		<div class="seob-progress-bar"><div id="seob-progress-fill" class="seob-progress-fill"></div></div>
		<span id="seob-progress-text" class="seob-progress-text"></span>
	</div>

	<div id="seob-summary" class="seob-summary"></div>

	<div id="seob-gsc-notice" class="notice notice-info inline seob-gsc-notice" hidden>
		<p>
			<?php
			printf(
				/* translators: %s: odkaz do Rank Math nastavení */
				esc_html__( 'Sloupce Search Console (zobrazení, kliky, CTR, pozice) se zobrazí, jakmile v Rank Math připojíte Google účet a modul Analytics: %s.', 'seo-boost' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=rank-math-options-general' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Rank Math – Obecná nastavení', 'seo-boost' ) . '</a>'
			);
			?>
		</p>
	</div>

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

	<div id="seob-groups">
		<p class="seob-empty-groups"><?php esc_html_e( 'Zatím žádný scan. Spusťte ho tlačítkem výše.', 'seo-boost' ); ?></p>
	</div>
</div>

<template id="seob-group-template">
	<div class="seob-audit-group">
		<button type="button" class="seob-group-toggle" aria-expanded="false">
			<span class="seob-group-arrow" aria-hidden="true">▶</span>
			<span class="seob-group-title"></span>
			<span class="seob-group-count"></span>
			<span class="seob-group-score-label"></span>
			<span class="seob-group-score-badge seob-score-badge"></span>
			<span class="seob-group-counts">
				<span class="seob-group-count-critical seob-count-critical"></span>
				<span class="seob-group-count-warning seob-count-warning"></span>
				<span class="seob-group-count-recommendation seob-count-recommendation"></span>
				<span class="seob-group-count-resolved seob-count-resolved"></span>
			</span>
			<span class="seob-group-gsc"></span>
		</button>
		<div class="seob-group-body" hidden>
			<table class="wp-list-table widefat seob-table seob-audit-table">
				<thead>
					<tr>
						<th class="seob-col-url"><?php esc_html_e( 'Stránka', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Skóre', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Title', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Description', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'H1', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Alt texty', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Schéma', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Obsah', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Od minula', 'seo-boost' ); ?></th>
						<th class="seob-col-gsc"><?php esc_html_e( 'Zobrazení', 'seo-boost' ); ?></th>
						<th class="seob-col-gsc"><?php esc_html_e( 'Kliky', 'seo-boost' ); ?></th>
						<th class="seob-col-gsc"><?php esc_html_e( 'CTR', 'seo-boost' ); ?></th>
						<th class="seob-col-gsc"><?php esc_html_e( 'Pozice', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody class="seob-group-rows"></tbody>
			</table>
		</div>
	</div>
</template>

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
		<td class="seob-col-thin"></td>
		<td class="seob-col-resolved"></td>
		<td class="seob-col-gsc seob-col-gsc-impressions"></td>
		<td class="seob-col-gsc seob-col-gsc-clicks"></td>
		<td class="seob-col-gsc seob-col-gsc-ctr"></td>
		<td class="seob-col-gsc seob-col-gsc-position"></td>
		<td class="seob-col-action">
			<button type="button" class="button seob-toggle-edit"><?php esc_html_e( 'Opravit', 'seo-boost' ); ?></button>
		</td>
	</tr>
	<tr class="seob-edit-row" hidden>
		<td colspan="14">
			<div class="seob-edit-panel">
				<div class="seob-field">
					<label><?php esc_html_e( 'SERP Title', 'seo-boost' ); ?></label>
					<input type="text" class="seob-input-title" maxlength="200">
					<span class="seob-pixel-meter seob-pixel-title"><span class="seob-pixel-fill"></span></span>
					<span class="seob-pixel-label"></span>
					<button type="button" class="button seob-ai-suggest-btn" data-field="title" hidden><?php esc_html_e( 'Navrhnout pomocí AI', 'seo-boost' ); ?></button>
				</div>
				<div class="seob-field">
					<label><?php esc_html_e( 'Meta description', 'seo-boost' ); ?></label>
					<textarea class="seob-input-description" rows="2" maxlength="400"></textarea>
					<span class="seob-pixel-meter seob-pixel-description"><span class="seob-pixel-fill"></span></span>
					<span class="seob-pixel-label"></span>
					<button type="button" class="button seob-ai-suggest-btn" data-field="description" hidden><?php esc_html_e( 'Navrhnout pomocí AI', 'seo-boost' ); ?></button>
				</div>
				<div class="seob-serp-preview">
					<div class="seob-serp-title"></div>
					<div class="seob-serp-url"></div>
					<div class="seob-serp-description"></div>
				</div>
				<div class="seob-field">
					<label><?php esc_html_e( 'Schéma (strukturovaná data)', 'seo-boost' ); ?></label>
					<select class="seob-input-schema"></select>
					<p class="seob-schema-source description"></p>
				</div>
				<div class="seob-issue-list"></div>
				<div class="seob-ai-alt-wrap">
					<button type="button" class="button seob-ai-suggest-alt-btn" hidden><?php esc_html_e( 'Navrhnout alt texty obrázků', 'seo-boost' ); ?></button>
				</div>
				<div class="seob-gsc-queries">
					<h4><?php esc_html_e( 'Klíčová slova ve vyhledávání (28 dní)', 'seo-boost' ); ?></h4>
					<table class="seob-gsc-queries-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Dotaz', 'seo-boost' ); ?></th>
								<th><?php esc_html_e( 'Pozice', 'seo-boost' ); ?></th>
								<th><?php esc_html_e( 'Kliky', 'seo-boost' ); ?></th>
								<th><?php esc_html_e( 'Zobrazení', 'seo-boost' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
					<p class="seob-gsc-queries-empty description"></p>
				</div>
				<div class="seob-edit-actions">
					<button type="button" class="button button-primary seob-save-meta"><?php esc_html_e( 'Uložit', 'seo-boost' ); ?></button>
					<button type="button" class="button seob-cancel-edit"><?php esc_html_e( 'Zavřít', 'seo-boost' ); ?></button>
					<span class="seob-save-status"></span>
					<span class="seob-ai-status"></span>
				</div>
			</div>
		</td>
	</tr>
</template>
