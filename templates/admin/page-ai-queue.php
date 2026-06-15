<?php
/**
 * AI schvalovací fronta – přehled návrhů a jejich schvalování/zamítání.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – AI fronta', 'seo-boost' ); ?></h1>

	<p>
		<?php esc_html_e( 'AI návrhy se nikdy neukládají automaticky. Zde je schválíte (zapíší se do skutečných polí) nebo zamítnete.', 'seo-boost' ); ?>
	</p>

	<p>
		<label for="seob-ai-queue-status"><?php esc_html_e( 'Stav:', 'seo-boost' ); ?></label>
		<select id="seob-ai-queue-status">
			<option value="pending"><?php esc_html_e( 'Čeká na schválení', 'seo-boost' ); ?></option>
			<option value="approved"><?php esc_html_e( 'Schváleno', 'seo-boost' ); ?></option>
			<option value="rejected"><?php esc_html_e( 'Zamítnuto', 'seo-boost' ); ?></option>
		</select>
	</p>

	<table class="wp-list-table widefat fixed striped seob-table seob-ai-queue-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Typ pole', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Stránka / Obrázek', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Aktuální hodnota', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Návrh AI', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-ai-queue-body">
			<tr class="seob-empty-row"><td colspan="5"><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></td></tr>
		</tbody>
	</table>
</div>

<template id="seob-ai-queue-row-template">
	<tr class="seob-result-row">
		<td class="seob-aiq-field"></td>
		<td class="seob-aiq-object">
			<a class="seob-aiq-edit-link" href="#" target="_blank"></a>
			<br>
			<img class="seob-aiq-preview" src="" alt="" style="max-width:60px;height:auto;display:none;margin-top:4px;">
		</td>
		<td class="seob-aiq-current"></td>
		<td class="seob-aiq-suggestion"></td>
		<td class="seob-aiq-actions">
			<button type="button" class="button button-primary seob-aiq-approve"><?php esc_html_e( 'Schválit', 'seo-boost' ); ?></button>
			<button type="button" class="button seob-aiq-reject"><?php esc_html_e( 'Zamítnout', 'seo-boost' ); ?></button>
		</td>
	</tr>
</template>
