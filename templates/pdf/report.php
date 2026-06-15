<?php
/**
 * HTML šablona pro PDF report (renderováno přes TCPDF::writeHTML).
 *
 * Dostupné proměnné:
 * @var array  $data Výstup SEOB_Pdf_Report_Data::build() s případně přepsanými texty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$severity_colors = [
	'critical'       => '#d63638',
	'warning'        => '#dba617',
	'recommendation' => '#2271b1',
];

$accent  = $data['company']['accent_color'] ?? '#2271b1';
$company = $data['company'];

$max_pages_per_group = 25;
?>
<style>
	body { font-family: dejavusans; font-size: 10pt; color: #1d2327; }
	h1 { font-size: 19pt; color: <?php echo esc_html( $accent ); ?>; margin-bottom: 2px; }
	h2 { font-size: 13pt; color: <?php echo esc_html( $accent ); ?>; margin-top: 14px; margin-bottom: 6px; border-bottom: 1px solid #dcdcde; padding-bottom: 2px; }
	h3 { font-size: 11pt; margin-top: 8px; margin-bottom: 4px; }
	p { line-height: 1.4; }
	.seob-pdf-header-title { font-size: 9pt; color: #646970; text-transform: uppercase; letter-spacing: 1px; }
	.seob-pdf-meta { color: #646970; font-size: 9pt; margin-bottom: 10px; }
	.seob-pdf-summary-box { background-color: #f6f7f7; border-left: 3px solid <?php echo esc_html( $accent ); ?>; padding: 8px 8px 8px 6mm; }
	table.seob-pdf-counts { width: 100%; margin-bottom: 10px; }
	table.seob-pdf-counts td { text-align: center; padding: 8px 4px; border: 1px solid #dcdcde; }
	.seob-pdf-score-big { font-size: 16pt; font-weight: bold; }
	.seob-pdf-page { border: 1px solid #dcdcde; padding: 6px; margin-bottom: 8px; }
	.seob-pdf-page-title { font-size: 11pt; font-weight: bold; }
	.seob-pdf-page-url { color: <?php echo esc_html( $accent ); ?>; font-size: 8pt; }
	.seob-pdf-score { font-weight: bold; }
	.seob-pdf-issue { margin-top: 6px; padding: 1px 0 1px 6mm; border-left: 3px solid #dcdcde; }
	.seob-pdf-issue-title { font-weight: bold; }
	.seob-pdf-issue-text { font-size: 9pt; color: #3c434a; }
	table.seob-pdf-summary-table { width: 100%; margin-bottom: 6px; font-size: 9pt; }
	table.seob-pdf-summary-table th, table.seob-pdf-summary-table td { border: 1px solid #dcdcde; padding: 4px; text-align: left; }
	table.seob-pdf-summary-table th { background-color: #f6f7f7; }
	.seob-pdf-appendix-group { margin-bottom: 6px; }
	.seob-pdf-appendix-title { font-weight: bold; font-size: 9pt; }
	.seob-pdf-appendix-url { font-size: 8pt; color: #3c434a; margin-left: 8px; }
	.seob-pdf-impact-box { background-color: #f0f6fc; border: 1px solid <?php echo esc_html( $accent ); ?>; padding: 10px; margin-top: 6px; }
	.seob-pdf-impact-number { font-size: 14pt; font-weight: bold; color: <?php echo esc_html( $accent ); ?>; }
	.seob-pdf-offer-box { background-color: #f0f6fc; border: 1px solid #c5d9ed; padding: 10px; margin-top: 6px; }
	.seob-pdf-footer { font-size: 8pt; color: #646970; margin-top: 16px; border-top: 1px solid #dcdcde; padding-top: 6px; }
	.seob-pdf-recap-item { margin-bottom: 8px; padding-left: 6mm; border-left: 3px solid <?php echo esc_html( $accent ); ?>; }
	.seob-pdf-recap-title { font-weight: bold; }
	.seob-pdf-issuer-box { border: 1px solid #dcdcde; padding: 10px; margin-top: 6px; }
	.seob-pdf-issuer-logo { margin-bottom: 6px; }
</style>

<div class="seob-pdf-header-title"><?php esc_html_e( 'SEO Audit – Report', 'seo-boost' ); ?></div>
<h1><?php echo esc_html( $data['site_name'] ); ?></h1>
<p class="seob-pdf-meta">
	<?php
	printf(
		/* translators: %s: datum vygenerování reportu */
		esc_html__( 'Vygenerováno %s', 'seo-boost' ),
		esc_html( $data['generated_at'] )
	);
	?>
</p>

<h2><?php esc_html_e( 'Souhrn auditu', 'seo-boost' ); ?></h2>
<div class="seob-pdf-summary-box">
	<p><?php echo nl2br( esc_html( $data['intro_summary'] ) ); ?></p>
</div>

<table class="seob-pdf-counts" cellpadding="4">
	<tr>
		<td>
			<div class="seob-pdf-score-big" style="color: <?php echo esc_attr( $accent ); ?>;"><?php echo (int) $data['scan']['score_avg']; ?>/100</div>
			<div><?php esc_html_e( 'Průměrné skóre', 'seo-boost' ); ?></div>
		</td>
		<td>
			<div class="seob-pdf-score-big" style="color: <?php echo esc_attr( $severity_colors['critical'] ); ?>;"><?php echo (int) $data['counts']['critical']; ?></div>
			<div><?php esc_html_e( 'Kritické nálezy', 'seo-boost' ); ?></div>
		</td>
		<td>
			<div class="seob-pdf-score-big" style="color: <?php echo esc_attr( $severity_colors['warning'] ); ?>;"><?php echo (int) $data['counts']['warning']; ?></div>
			<div><?php esc_html_e( 'Varování', 'seo-boost' ); ?></div>
		</td>
		<td>
			<div class="seob-pdf-score-big" style="color: <?php echo esc_attr( $severity_colors['recommendation'] ); ?>;"><?php echo (int) $data['counts']['recommendation']; ?></div>
			<div><?php esc_html_e( 'Doporučení', 'seo-boost' ); ?></div>
		</td>
		<td>
			<div class="seob-pdf-score-big"><?php echo (int) $data['pages_ok_count']; ?>/<?php echo (int) $data['scan']['urls_total']; ?></div>
			<div><?php esc_html_e( 'Stránky bez nálezů', 'seo-boost' ); ?></div>
		</td>
	</tr>
</table>

<?php if ( ! empty( $data['detailed_rows'] ) ) : ?>
	<h2><?php esc_html_e( 'Nejdůležitější zjištění', 'seo-boost' ); ?></h2>
	<p class="seob-pdf-meta">
		<?php
		printf(
			/* translators: 1: počet detailně popsaných stránek, 2: celkový počet stránek s nálezy */
			esc_html__( 'Detailní popis nálezů u %1$d nejhůře hodnocených stránek (z celkem %2$d stránek s nálezy).', 'seo-boost' ),
			count( $data['detailed_rows'] ),
			(int) $data['pages_with_issues_count']
		);
		?>
	</p>

	<?php foreach ( $data['detailed_rows'] as $row ) : ?>
		<div class="seob-pdf-page">
			<div class="seob-pdf-page-title"><?php echo esc_html( $row['title'] ?: $row['url'] ); ?> &ndash; <?php echo (int) $row['score']; ?>/100</div>
			<div class="seob-pdf-page-url"><?php echo esc_html( $row['url'] ); ?></div>

			<?php foreach ( $row['issues'] as $issue ) : ?>
				<?php $color = $severity_colors[ $issue['severity'] ] ?? '#646970'; ?>
				<div class="seob-pdf-issue" style="border-left-color: <?php echo esc_attr( $color ); ?>;">
					<div class="seob-pdf-issue-title" style="color: <?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( $issue['severity_label'] ); ?>: <?php echo esc_html( $issue['label'] ); ?>
					</div>
					<?php if ( ! empty( $issue['impact'] ) ) : ?>
						<div class="seob-pdf-issue-text"><strong><?php esc_html_e( 'Dopad, pokud se neřeší:', 'seo-boost' ); ?></strong> <?php echo esc_html( $issue['impact'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $issue['benefit'] ) ) : ?>
						<div class="seob-pdf-issue-text"><strong><?php esc_html_e( 'Přínos po opravě:', 'seo-boost' ); ?></strong> <?php echo esc_html( $issue['benefit'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( ! empty( $data['issue_summary'] ) ) : ?>
	<h2><?php esc_html_e( 'Souhrn nálezů podle typu', 'seo-boost' ); ?></h2>
	<?php if ( (int) $data['remaining_count'] > 0 ) : ?>
		<p class="seob-pdf-meta">
			<?php
			printf(
				/* translators: %d: počet zbývajících stránek */
				esc_html__( 'Přehled všech nálezů na webu seskupený podle typu. U dalších %d stránek (mimo výše uvedený detailní výpis) je seznam konkrétních URL v sekci „Příloha“.', 'seo-boost' ),
				(int) $data['remaining_count']
			);
			?>
		</p>
	<?php endif; ?>

	<table class="seob-pdf-summary-table" cellpadding="4">
		<tr>
			<th><?php esc_html_e( 'Závažnost', 'seo-boost' ); ?></th>
			<th><?php esc_html_e( 'Nález', 'seo-boost' ); ?></th>
			<th><?php esc_html_e( 'Počet stránek', 'seo-boost' ); ?></th>
		</tr>
		<?php foreach ( $data['issue_summary'] as $group ) : ?>
			<?php $color = $severity_colors[ $group['severity'] ] ?? '#646970'; ?>
			<tr>
				<td style="color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $group['severity_label'] ); ?></td>
				<td><?php echo esc_html( $group['label'] ); ?></td>
				<td style="text-align: center;"><?php echo (int) $group['count']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<h2><?php esc_html_e( 'Příloha – stránky podle typu nálezu', 'seo-boost' ); ?></h2>
	<?php foreach ( $data['issue_summary'] as $group ) : ?>
		<?php $color = $severity_colors[ $group['severity'] ] ?? '#646970'; ?>
		<div class="seob-pdf-appendix-group">
			<div class="seob-pdf-appendix-title" style="color: <?php echo esc_attr( $color ); ?>;">
				<?php echo esc_html( $group['severity_label'] ); ?>: <?php echo esc_html( $group['label'] ); ?> (<?php echo (int) $group['count']; ?>)
			</div>
			<?php foreach ( array_slice( $group['pages'], 0, $max_pages_per_group ) as $page ) : ?>
				<div class="seob-pdf-appendix-url"><?php echo esc_html( $page['title'] ?: $page['url'] ); ?> &ndash; <?php echo esc_html( $page['url'] ); ?></div>
			<?php endforeach; ?>
			<?php if ( count( $group['pages'] ) > $max_pages_per_group ) : ?>
				<div class="seob-pdf-appendix-url">
					<?php
					printf(
						/* translators: %d: počet dalších stránek */
						esc_html__( '… a dalších %d stránek.', 'seo-boost' ),
						count( $group['pages'] ) - $max_pages_per_group
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( ! empty( $data['issue_summary'] ) ) : ?>
	<h2><?php esc_html_e( 'Souhrn dopadů a přínosů zlepšení', 'seo-boost' ); ?></h2>
	<p class="seob-pdf-meta">
		<?php esc_html_e( 'Celkové shrnutí dopadů jednotlivých typů nálezů a přínosů po jejich opravě, napříč celým webem.', 'seo-boost' ); ?>
	</p>
	<?php foreach ( $data['issue_summary'] as $group ) : ?>
		<?php if ( empty( $group['impact'] ) && empty( $group['benefit'] ) ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<?php $color = $severity_colors[ $group['severity'] ] ?? '#646970'; ?>
		<div class="seob-pdf-recap-item" style="border-left-color: <?php echo esc_attr( $color ); ?>;">
			<div class="seob-pdf-recap-title" style="color: <?php echo esc_attr( $color ); ?>;">
				<?php echo esc_html( $group['severity_label'] ); ?>: <?php echo esc_html( $group['label'] ); ?>
				<?php
				printf(
					/* translators: %d: počet ovlivněných stránek */
					esc_html__( ' (%d stránek)', 'seo-boost' ),
					(int) $group['count']
				);
				?>
			</div>
			<?php if ( ! empty( $group['impact'] ) ) : ?>
				<div class="seob-pdf-issue-text"><strong><?php esc_html_e( 'Dopad, pokud se neřeší:', 'seo-boost' ); ?></strong> <?php echo esc_html( $group['impact'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $group['benefit'] ) ) : ?>
				<div class="seob-pdf-issue-text"><strong><?php esc_html_e( 'Přínos po opravě:', 'seo-boost' ); ?></strong> <?php echo esc_html( $group['benefit'] ); ?></div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( ! empty( $data['business_impact'] ) ) : ?>
	<?php $impact = $data['business_impact']; ?>
	<h2><?php esc_html_e( 'Odhad obchodního dopadu zlepšení SEO', 'seo-boost' ); ?></h2>
	<div class="seob-pdf-impact-box">
		<p>
			<?php
			printf(
				/* translators: 1: počet stránek ovlivňujících SERP, 2: odhadovaný nárůst CTR v % */
				esc_html__( 'Na webu jsme identifikovali %1$d stránek s nálezy, které přímo ovlivňují, jak se stránka zobrazuje ve výsledcích vyhledávání (title, description, duplicity). Po jejich opravě lze orientačně očekávat nárůst míry prokliku (CTR) o cca %2$d %%.', 'seo-boost' ),
				(int) $impact['serp_affected_pages'],
				(int) $impact['ctr_uplift_percent']
			);
			?>
		</p>
		<table class="seob-pdf-counts" cellpadding="4">
			<tr>
				<td>
					<div class="seob-pdf-impact-number">+<?php echo (int) $impact['additional_visits']; ?></div>
					<div><?php esc_html_e( 'návštěv / měsíc', 'seo-boost' ); ?></div>
				</td>
				<?php if ( $impact['conversion_rate'] > 0 ) : ?>
					<td>
						<div class="seob-pdf-impact-number">+<?php echo (int) $impact['additional_conversions']; ?></div>
						<div><?php esc_html_e( 'konverzí / měsíc', 'seo-boost' ); ?></div>
					</td>
				<?php endif; ?>
				<?php if ( $impact['avg_value'] > 0 ) : ?>
					<td>
						<div class="seob-pdf-impact-number">+<?php echo number_format_i18n( (int) $impact['additional_revenue'] ); ?> Kč</div>
						<div><?php esc_html_e( 'odhad. tržby / měsíc', 'seo-boost' ); ?></div>
					</td>
				<?php endif; ?>
			</tr>
		</table>
		<p class="seob-pdf-meta">
			<?php esc_html_e( 'Jedná se o orientační odhad založený na zadané návštěvnosti, konverzním poměru a hodnotě objednávky/leadu. Skutečné výsledky se mohou lišit a závisí na konkurenci, sezónnosti a dalších faktorech.', 'seo-boost' ); ?>
		</p>
	</div>
<?php endif; ?>

<h2><?php esc_html_e( 'Naše nabídka', 'seo-boost' ); ?></h2>
<div class="seob-pdf-offer-box">
	<h3><?php echo esc_html( $data['offer_name'] ); ?></h3>
	<p><?php echo nl2br( esc_html( $data['offer_body'] ) ); ?></p>
</div>

<?php if ( ! empty( $company['name'] ) || ! empty( $company['contact_person'] ) || ! empty( $company['ico'] ) || ! empty( $company['contact'] ) || ! empty( $company['logo_path'] ) ) : ?>
	<h2><?php esc_html_e( 'Vystavil', 'seo-boost' ); ?></h2>
	<div class="seob-pdf-issuer-box">
		<?php if ( ! empty( $company['logo_path'] ) ) : ?>
			<img class="seob-pdf-issuer-logo" src="<?php echo esc_attr( $company['logo_path'] ); ?>" width="<?php echo esc_attr( $company['logo_issuer_w'] ?? 35 ); ?>mm" height="<?php echo esc_attr( $company['logo_issuer_h'] ?? 18 ); ?>mm">
		<?php endif; ?>
		<?php if ( ! empty( $company['contact_person'] ) ) : ?>
			<p><strong><?php echo esc_html( $company['contact_person'] ); ?></strong></p>
		<?php endif; ?>
		<?php if ( ! empty( $company['name'] ) ) : ?>
			<p><strong style="color: <?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( $company['name'] ); ?></strong></p>
		<?php endif; ?>
		<?php if ( ! empty( $company['ico'] ) ) : ?>
			<p><?php echo esc_html__( 'IČO: ', 'seo-boost' ); ?><?php echo esc_html( $company['ico'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $company['contact'] ) ) : ?>
			<p><?php echo nl2br( esc_html( $company['contact'] ) ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $company['name'] ) || ! empty( $company['contact'] ) || ! empty( $company['footer_text'] ) ) : ?>
	<div class="seob-pdf-footer">
		<?php if ( ! empty( $company['name'] ) ) : ?>
			<p><strong style="color: <?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( $company['name'] ); ?></strong></p>
		<?php endif; ?>
		<?php if ( ! empty( $company['contact'] ) ) : ?>
			<p><?php echo nl2br( esc_html( $company['contact'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $company['footer_text'] ) ) : ?>
			<p><?php echo nl2br( esc_html( $company['footer_text'] ) ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>
