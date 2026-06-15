<?php
/**
 * PageSpeed Insights – spuštění analýzy vzorku stránek a souhrn podle typu obsahu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seob_psi_settings = SEOB_Settings::get( SEOB_Settings::PAGESPEED );
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – PageSpeed Insights', 'seo-boost' ); ?></h1>

	<p>
		<?php esc_html_e( 'Pro každý veřejný typ obsahu se vybere náhodný vzorek publikovaných stránek a otestuje se přes Google PageSpeed Insights (mobil i desktop). Výsledky jsou shrnuty podle typu obsahu, včetně nejčastějších SEO nálezů.', 'seo-boost' ); ?>
	</p>

	<p class="description">
		<?php esc_html_e( 'Analýza běží i na pozadí (WP-Cron) – klidně stránku zavřete, běh se dokončí sám. Posledních 10 dokončených běhů se uchovává pro porovnání skóre před/po opravě.', 'seo-boost' ); ?>
	</p>

	<?php if ( '' === $seob_psi_settings['api_key_enc'] ) : ?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'Chybí API klíč pro PageSpeed Insights.', 'seo-boost' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seob-settings' ) ); ?>"><?php esc_html_e( 'Doplnit v Nastavení', 'seo-boost' ); ?></a>
		</p>
	</div>
	<?php endif; ?>

	<div class="seob-toolbar">
		<button type="button" id="seob-psi-run" class="button button-primary" <?php disabled( '' === $seob_psi_settings['api_key_enc'] ); ?>>
			<?php esc_html_e( 'Spustit analýzu', 'seo-boost' ); ?>
		</button>
		<label class="seob-scan-history-label">
			<?php esc_html_e( 'Historie běhů:', 'seo-boost' ); ?>
			<select id="seob-psi-history"></select>
		</label>
		<button type="button" id="seob-psi-delete" class="button">
			<?php esc_html_e( 'Smazat vybraný běh', 'seo-boost' ); ?>
		</button>
		<span id="seob-psi-meta" class="seob-scan-meta"></span>
	</div>

	<div id="seob-psi-progress" class="seob-progress" hidden>
		<div class="seob-progress-row">
			<span id="seob-psi-spinner" class="seob-spinner" aria-hidden="true"></span>
			<div class="seob-progress-bar"><div id="seob-psi-progress-fill" class="seob-progress-fill"></div></div>
			<span id="seob-psi-progress-text" class="seob-progress-text"></span>
		</div>
		<p id="seob-psi-progress-status" class="seob-progress-status"></p>
	</div>

	<div id="seob-psi-overall"></div>

	<div id="seob-psi-results"></div>
</div>

<template id="seob-psi-overall-template">
	<div class="seob-psi-group seob-psi-overall">
		<h2 class="seob-psi-group-title"><?php esc_html_e( 'Celkový přehled webu (průměr ze všech typů obsahu)', 'seo-boost' ); ?></h2>

		<div class="seob-psi-strategies">
			<div class="seob-psi-strategy" data-strategy="mobile">
				<h3><?php esc_html_e( 'Mobil', 'seo-boost' ); ?></h3>
				<ul class="seob-psi-scores">
					<li><?php esc_html_e( 'Performance', 'seo-boost' ); ?>: <span class="seob-psi-score-performance_avg"></span> <span class="seob-psi-delta seob-psi-delta-performance_avg"></span></li>
					<li><?php esc_html_e( 'Accessibility', 'seo-boost' ); ?>: <span class="seob-psi-score-accessibility_avg"></span> <span class="seob-psi-delta seob-psi-delta-accessibility_avg"></span></li>
					<li><?php esc_html_e( 'Best Practices', 'seo-boost' ); ?>: <span class="seob-psi-score-best_practices_avg"></span> <span class="seob-psi-delta seob-psi-delta-best_practices_avg"></span></li>
					<li><?php esc_html_e( 'SEO', 'seo-boost' ); ?>: <span class="seob-psi-score-seo_avg"></span> <span class="seob-psi-delta seob-psi-delta-seo_avg"></span></li>
				</ul>
			</div>
			<div class="seob-psi-strategy" data-strategy="desktop">
				<h3><?php esc_html_e( 'Desktop', 'seo-boost' ); ?></h3>
				<ul class="seob-psi-scores">
					<li><?php esc_html_e( 'Performance', 'seo-boost' ); ?>: <span class="seob-psi-score-performance_avg"></span> <span class="seob-psi-delta seob-psi-delta-performance_avg"></span></li>
					<li><?php esc_html_e( 'Accessibility', 'seo-boost' ); ?>: <span class="seob-psi-score-accessibility_avg"></span> <span class="seob-psi-delta seob-psi-delta-accessibility_avg"></span></li>
					<li><?php esc_html_e( 'Best Practices', 'seo-boost' ); ?>: <span class="seob-psi-score-best_practices_avg"></span> <span class="seob-psi-delta seob-psi-delta-best_practices_avg"></span></li>
					<li><?php esc_html_e( 'SEO', 'seo-boost' ); ?>: <span class="seob-psi-score-seo_avg"></span> <span class="seob-psi-delta seob-psi-delta-seo_avg"></span></li>
				</ul>
			</div>
		</div>
	</div>
</template>

<template id="seob-psi-group-template">
	<div class="seob-psi-group">
		<h2 class="seob-psi-group-title"></h2>

		<div class="seob-psi-strategies">
			<div class="seob-psi-strategy" data-strategy="mobile">
				<h3><?php esc_html_e( 'Mobil', 'seo-boost' ); ?></h3>
				<ul class="seob-psi-scores">
					<li><?php esc_html_e( 'Performance', 'seo-boost' ); ?>: <span class="seob-psi-score-performance_avg"></span> <span class="seob-psi-delta seob-psi-delta-performance_avg"></span></li>
					<li><?php esc_html_e( 'Accessibility', 'seo-boost' ); ?>: <span class="seob-psi-score-accessibility_avg"></span> <span class="seob-psi-delta seob-psi-delta-accessibility_avg"></span></li>
					<li><?php esc_html_e( 'Best Practices', 'seo-boost' ); ?>: <span class="seob-psi-score-best_practices_avg"></span> <span class="seob-psi-delta seob-psi-delta-best_practices_avg"></span></li>
					<li><?php esc_html_e( 'SEO', 'seo-boost' ); ?>: <span class="seob-psi-score-seo_avg"></span> <span class="seob-psi-delta seob-psi-delta-seo_avg"></span></li>
				</ul>
			</div>
			<div class="seob-psi-strategy" data-strategy="desktop">
				<h3><?php esc_html_e( 'Desktop', 'seo-boost' ); ?></h3>
				<ul class="seob-psi-scores">
					<li><?php esc_html_e( 'Performance', 'seo-boost' ); ?>: <span class="seob-psi-score-performance_avg"></span> <span class="seob-psi-delta seob-psi-delta-performance_avg"></span></li>
					<li><?php esc_html_e( 'Accessibility', 'seo-boost' ); ?>: <span class="seob-psi-score-accessibility_avg"></span> <span class="seob-psi-delta seob-psi-delta-accessibility_avg"></span></li>
					<li><?php esc_html_e( 'Best Practices', 'seo-boost' ); ?>: <span class="seob-psi-score-best_practices_avg"></span> <span class="seob-psi-delta seob-psi-delta-best_practices_avg"></span></li>
					<li><?php esc_html_e( 'SEO', 'seo-boost' ); ?>: <span class="seob-psi-score-seo_avg"></span> <span class="seob-psi-delta seob-psi-delta-seo_avg"></span></li>
				</ul>
			</div>
		</div>

		<h4><?php esc_html_e( 'Nejčastější SEO nálezy', 'seo-boost' ); ?></h4>
		<ul class="seob-psi-issues"></ul>

		<h4><?php esc_html_e( 'Vzorek stránek', 'seo-boost' ); ?></h4>
		<ul class="seob-psi-samples"></ul>
	</div>
</template>

<template id="seob-psi-issue-template">
	<li class="seob-psi-issue">
		<strong class="seob-psi-issue-title"></strong>
		<span class="seob-psi-issue-count"></span>
		<p class="seob-psi-issue-description"></p>
	</li>
</template>

<template id="seob-psi-sample-template">
	<li class="seob-psi-sample">
		<a class="seob-psi-sample-link" href="#" target="_blank" rel="noopener"></a>
		(<a class="seob-psi-sample-edit" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'upravit', 'seo-boost' ); ?></a>)
	</li>
</template>
