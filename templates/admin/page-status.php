<?php
/**
 * Stav systému – přehled modulů, health checky a trendy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Stav systému', 'seo-boost' ); ?></h1>

	<p id="seob-status-loading"><?php esc_html_e( 'Načítám stav systému…', 'seo-boost' ); ?></p>
	<p id="seob-status-error" class="description" hidden></p>

	<div id="seob-status-content" hidden>
		<h2><?php esc_html_e( 'Obecné', 'seo-boost' ); ?></h2>
		<ul id="seob-status-general" class="seob-status-list"></ul>

		<h2><?php esc_html_e( 'Moduly', 'seo-boost' ); ?></h2>
		<table class="widefat striped seob-status-modules">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Modul', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Popis', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'seo-boost' ); ?></th>
					<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
				</tr>
			</thead>
			<tbody id="seob-status-modules-body"></tbody>
		</table>

		<h2><?php esc_html_e( 'Health checky', 'seo-boost' ); ?></h2>
		<div id="seob-status-checks"></div>

		<h2><?php esc_html_e( 'Trendy', 'seo-boost' ); ?></h2>
		<div id="seob-status-trends" class="seob-status-trends">
			<div class="seob-status-trend">
				<h3><?php esc_html_e( 'Audit – průměrné skóre', 'seo-boost' ); ?></h3>
				<div class="seob-sparkline" data-module="audit" data-key="score_avg" data-label="<?php esc_attr_e( 'Skóre', 'seo-boost' ); ?>"></div>
			</div>
			<div class="seob-status-trend">
				<h3><?php esc_html_e( 'Přesměrování – nevyřešené 404', 'seo-boost' ); ?></h3>
				<div class="seob-sparkline" data-module="redirects" data-key="unresolved_404_count" data-label="<?php esc_attr_e( 'Počet 404', 'seo-boost' ); ?>"></div>
			</div>
		</div>
	</div>
</div>
