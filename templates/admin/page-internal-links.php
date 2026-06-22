<?php
/**
 * Interní prolinkování – reindex link grafu, osamocené stránky a návrhy prolinkování.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Interní prolinkování', 'seo-boost' ); ?></h1>

	<p>
		<?php esc_html_e( 'Reindex projde všechny publikované stránky, zaznamená jejich interní odkazy a na základě podobnosti obsahu (TF-IDF) navrhne, ze kterých příbuzných stránek by se mělo odkazovat na osamocené (orphan) stránky.', 'seo-boost' ); ?>
	</p>

	<p class="description">
		<?php esc_html_e( 'Mezi reindexy se link graf jednotlivé stránky automaticky aktualizuje při jejím uložení – návrhy prolinkování se ale přepočítají až při dalším plném reindexu.', 'seo-boost' ); ?>
	</p>

	<div class="seob-toolbar">
		<button type="button" id="seob-links-run" class="button button-primary">
			<?php esc_html_e( 'Spustit reindex', 'seo-boost' ); ?>
		</button>
		<span id="seob-links-meta" class="seob-scan-meta"></span>
	</div>

	<div id="seob-links-progress" class="seob-progress" hidden>
		<div class="seob-progress-row">
			<span id="seob-links-spinner" class="seob-spinner" aria-hidden="true"></span>
			<div class="seob-progress-bar"><div id="seob-links-progress-fill" class="seob-progress-fill"></div></div>
			<span id="seob-links-progress-text" class="seob-progress-text"></span>
		</div>
	</div>

	<div id="seob-links-summary" class="seob-summary-row" hidden>
		<div class="seob-summary-box">
			<div class="seob-summary-value" id="seob-links-summary-total"></div>
			<div class="seob-summary-label"><?php esc_html_e( 'Indexovaných stránek', 'seo-boost' ); ?></div>
		</div>
		<div class="seob-summary-box">
			<div class="seob-summary-value" id="seob-links-summary-orphans"></div>
			<div class="seob-summary-label">
				<?php esc_html_e( 'Osamocené stránky', 'seo-boost' ); ?>
				<span class="seob-psi-delta" id="seob-links-summary-orphans-delta"></span>
			</div>
		</div>
		<div class="seob-summary-box">
			<div class="seob-summary-value" id="seob-links-summary-avg"></div>
			<div class="seob-summary-label">
				<?php esc_html_e( 'Průměr interních odkazů na stránku', 'seo-boost' ); ?>
				<span class="seob-psi-delta" id="seob-links-summary-avg-delta"></span>
			</div>
		</div>
		<div class="seob-summary-box">
			<div class="seob-summary-value" id="seob-links-summary-health"></div>
			<div class="seob-summary-label">
				<?php esc_html_e( 'Zdraví prolinkování', 'seo-boost' ); ?>
				<span id="seob-links-summary-health-detail" style="display:block;font-size:10px;color:#646970;font-weight:normal;margin-top:2px"></span>
			</div>
		</div>
	</div>

	<h2><?php esc_html_e( 'Osamocené stránky', 'seo-boost' ); ?></h2>
	<div id="seob-links-orphan-groups">
		<p class="description"><?php esc_html_e( 'Zatím žádná data – spusťte reindex.', 'seo-boost' ); ?></p>
	</div>

	<h2 style="margin-top:24px"><?php esc_html_e( 'Všechny stránky', 'seo-boost' ); ?></h2>
	<div id="seob-links-page-groups">
		<p class="description"><?php esc_html_e( 'Zatím žádná data – spusťte reindex.', 'seo-boost' ); ?></p>
	</div>
</div>
