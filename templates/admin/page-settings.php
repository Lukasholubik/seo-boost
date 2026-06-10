<?php
/**
 * Obecné nastavení pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$general  = SEOB_Settings::get( SEOB_Settings::GENERAL );
$audit    = SEOB_Settings::get( SEOB_Settings::AUDIT );
$redirect = SEOB_Settings::get( SEOB_Settings::REDIRECT );
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Nastavení', 'seo-boost' ); ?></h1>

	<form id="seob-settings-form">
		<h2><?php esc_html_e( 'Obecné', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Moduly', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="modules_audit" value="1" <?php checked( ! empty( $general['modules']['audit'] ) ); ?>>
						<?php esc_html_e( 'Audit Dashboard', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_redirects" value="1" <?php checked( ! empty( $general['modules']['redirects'] ) ); ?>>
						<?php esc_html_e( 'Redirect Manager (404 log + přesměrování)', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ladicí režim', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="debug" value="1" <?php checked( ! empty( $general['debug'] ) ); ?>>
						<?php esc_html_e( 'Zapnout debug log', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Odinstalace', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $general['delete_on_uninstall'] ) ); ?>>
						<?php esc_html_e( 'Při odinstalaci smazat všechna data pluginu (DB tabulky a nastavení)', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Audit Dashboard', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-batch-size"><?php esc_html_e( 'Velikost dávky', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-batch-size" name="batch_size" min="1" max="100" value="<?php echo esc_attr( (int) $audit['batch_size'] ); ?>">
					<p class="description"><?php esc_html_e( 'Počet URL zpracovaných v jedné dávce při spuštění scanu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-thin-content"><?php esc_html_e( 'Hranice thin content', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-thin-content" name="thin_content_words" min="50" max="2000" step="10" value="<?php echo esc_attr( (int) $audit['thin_content_words'] ); ?>">
					<p class="description"><?php esc_html_e( 'Stránky s menším počtem slov budou označeny jako "thin content".', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Noční scan (cron)', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cron_enabled" value="1" <?php checked( ! empty( $audit['cron_enabled'] ) ); ?>>
						<?php esc_html_e( 'Spouštět scan automaticky každou noc', 'seo-boost' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Poznámka: automatický noční scan zatím není implementován, nastavení se uloží pro budoucí verzi.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Přesměrování', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( '404 log', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="log_404" value="1" <?php checked( ! empty( $redirect['log_404'] ) ); ?>>
						<?php esc_html_e( 'Zaznamenávat 404 chyby', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-log-retention"><?php esc_html_e( 'Retence 404 logu', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-log-retention" name="log_retention_days" min="1" max="365" value="<?php echo esc_attr( (int) $redirect['log_retention_days'] ); ?>">
					<p class="description"><?php esc_html_e( 'Počet dní, po které se uchovávají 404 záznamy bez nastaveného přesměrování.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" id="seob-save-settings" class="button button-primary"><?php esc_html_e( 'Uložit nastavení', 'seo-boost' ); ?></button>
			<span id="seob-settings-status" class="seob-save-status"></span>
		</p>
	</form>
</div>
