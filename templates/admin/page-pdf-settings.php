<?php
/**
 * Nastavení exportu PDF reportu (texty nálezů, obchodní nabídky, firemní údaje).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pdf = SEOB_Settings::get( SEOB_Settings::PDF );

$issue_labels = SEOB_Pdf_Report_Data::issue_labels();

$offer_score_hints = [
	'maintenance'   => __( 'skóre 85 a více', 'seo-boost' ),
	'standard'      => __( 'skóre 60–84', 'seo-boost' ),
	'comprehensive' => __( 'skóre pod 60', 'seo-boost' ),
];
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Export: Nastavení', 'seo-boost' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Tyto texty a šablony se používají při generování PDF reportu na stránce „Export reportu“.', 'seo-boost' ); ?>
	</p>

	<form id="seob-pdf-settings-form">
		<h2><?php esc_html_e( 'Texty nálezů', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Tyto texty se použijí v exportovaném PDF reportu jako popis dopadu nálezu (pokud se neřeší) a přínosu po jeho opravě.', 'seo-boost' ); ?>
		</p>
		<table class="form-table seob-pdf-issue-table" role="presentation">
			<?php foreach ( $issue_labels as $issue_type => $label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<p>
							<label for="seob-pdf-impact-<?php echo esc_attr( $issue_type ); ?>"><?php esc_html_e( 'Dopad, pokud se neřeší', 'seo-boost' ); ?></label><br>
							<textarea id="seob-pdf-impact-<?php echo esc_attr( $issue_type ); ?>" name="pdf_issue_<?php echo esc_attr( $issue_type ); ?>_impact" rows="2" class="large-text"><?php echo esc_textarea( $pdf['issue_texts'][ $issue_type ]['impact'] ?? '' ); ?></textarea>
						</p>
						<p>
							<label for="seob-pdf-benefit-<?php echo esc_attr( $issue_type ); ?>"><?php esc_html_e( 'Přínos po opravě', 'seo-boost' ); ?></label><br>
							<textarea id="seob-pdf-benefit-<?php echo esc_attr( $issue_type ); ?>" name="pdf_issue_<?php echo esc_attr( $issue_type ); ?>_benefit" rows="2" class="large-text"><?php echo esc_textarea( $pdf['issue_texts'][ $issue_type ]['benefit'] ?? '' ); ?></textarea>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Obchodní nabídky', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Šablona se vybere automaticky podle průměrného skóre auditu. V textu lze použít zástupné symboly: {site_name}, {score}, {critical_count}, {warning_count}.', 'seo-boost' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php foreach ( $pdf['offer_templates'] as $offer_key => $offer ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $offer_score_hints[ $offer_key ] ?? '' ); ?>
					</th>
					<td>
						<p>
							<label for="seob-pdf-offer-<?php echo esc_attr( $offer_key ); ?>-name"><?php esc_html_e( 'Název nabídky', 'seo-boost' ); ?></label><br>
							<input type="text" id="seob-pdf-offer-<?php echo esc_attr( $offer_key ); ?>-name" name="pdf_offer_<?php echo esc_attr( $offer_key ); ?>_name" class="large-text" value="<?php echo esc_attr( $offer['name'] ); ?>">
						</p>
						<p>
							<label for="seob-pdf-offer-<?php echo esc_attr( $offer_key ); ?>-body"><?php esc_html_e( 'Text nabídky', 'seo-boost' ); ?></label><br>
							<textarea id="seob-pdf-offer-<?php echo esc_attr( $offer_key ); ?>-body" name="pdf_offer_<?php echo esc_attr( $offer_key ); ?>_body" rows="4" class="large-text"><?php echo esc_textarea( $offer['body'] ); ?></textarea>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Firemní údaje a branding', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Logo a barevný akcent se zobrazí v záhlaví a v patičce vygenerovaného PDF reportu.', 'seo-boost' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-pdf-company-name"><?php esc_html_e( 'Název firmy', 'seo-boost' ); ?></label></th>
				<td><input type="text" id="seob-pdf-company-name" name="pdf_company_name" class="regular-text" value="<?php echo esc_attr( $pdf['company']['name'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-contact-person"><?php esc_html_e( 'Jméno zpracovatele', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="seob-pdf-company-contact-person" name="pdf_company_contact_person" class="regular-text" value="<?php echo esc_attr( $pdf['company']['contact_person'] ); ?>">
					<p class="description"><?php esc_html_e( 'Jméno osoby, která report/nabídku vystavila – zobrazí se na konci PDF u nabídky.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-ico"><?php esc_html_e( 'IČO', 'seo-boost' ); ?></label></th>
				<td><input type="text" id="seob-pdf-company-ico" name="pdf_company_ico" class="regular-text" value="<?php echo esc_attr( $pdf['company']['ico'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-contact"><?php esc_html_e( 'Kontakt', 'seo-boost' ); ?></label></th>
				<td>
					<textarea id="seob-pdf-company-contact" name="pdf_company_contact" rows="2" class="large-text"><?php echo esc_textarea( $pdf['company']['contact'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Např. e-mail, telefon, web – zobrazí se v patičce PDF.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-footer"><?php esc_html_e( 'Patička PDF', 'seo-boost' ); ?></label></th>
				<td><textarea id="seob-pdf-company-footer" name="pdf_company_footer" rows="2" class="large-text"><?php echo esc_textarea( $pdf['company']['footer_text'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-logo-button"><?php esc_html_e( 'Logo agentury', 'seo-boost' ); ?></label></th>
				<td>
					<input type="hidden" id="seob-pdf-company-logo-id" name="pdf_company_logo_id" value="<?php echo esc_attr( $pdf['company']['logo_id'] ); ?>">
					<div id="seob-pdf-company-logo-preview" style="margin-bottom:8px;<?php echo empty( $pdf['company']['logo_url'] ) ? 'display:none;' : ''; ?>">
						<img src="<?php echo esc_url( $pdf['company']['logo_url'] ); ?>" alt="" style="max-width:200px;max-height:80px;display:block;">
					</div>
					<button type="button" id="seob-pdf-company-logo-button" class="button"><?php esc_html_e( 'Vybrat logo', 'seo-boost' ); ?></button>
					<button type="button" id="seob-pdf-company-logo-remove" class="button" <?php echo empty( $pdf['company']['logo_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Odstranit logo', 'seo-boost' ); ?></button>
					<p class="description"><?php esc_html_e( 'Logo se zobrazí v záhlaví titulní strany PDF reportu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pdf-company-accent-color"><?php esc_html_e( 'Barevný akcent', 'seo-boost' ); ?></label></th>
				<td>
					<input type="color" id="seob-pdf-company-accent-color" name="pdf_company_accent_color" value="<?php echo esc_attr( $pdf['company']['accent_color'] ); ?>">
					<p class="description"><?php esc_html_e( 'Primární barva nadpisů, zvýraznění a grafů v PDF reportu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Rozsah reportu', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-pdf-report-detailed-limit"><?php esc_html_e( 'Detailní výpis u nejhorších stránek', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-pdf-report-detailed-limit" name="pdf_report_detailed_pages_limit" min="1" max="100" step="1" class="small-text" value="<?php echo esc_attr( $pdf['report']['detailed_pages_limit'] ); ?>">
					<p class="description"><?php esc_html_e( 'Kolik nejhorších stránek (dle skóre a počtu nálezů) se v PDF zobrazí jako samostatné karty s plným popisem. Zbylé stránky se shrnou do přehledu podle typu nálezu s odkazem na konkrétní URL.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" id="seob-save-pdf-settings" class="button button-primary"><?php esc_html_e( 'Uložit nastavení', 'seo-boost' ); ?></button>
			<span id="seob-pdf-settings-status" class="seob-save-status"></span>
		</p>
	</form>
</div>
