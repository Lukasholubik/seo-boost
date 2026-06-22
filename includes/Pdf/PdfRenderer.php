<?php
/**
 * Vyrenderuje data auditu do PDF (TCPDF) jako obchodní report.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Pdf_Renderer {

	/**
	 * Vyrenderuje report a vrátí obsah PDF jako string.
	 *
	 * @param array $data Data reportu (viz SEOB_Pdf_Report_Data::build()),
	 *                     případně s přepsanými texty z formuláře exportu.
	 */
	public function render( array $data ): string {
		require_once SEOB_PLUGIN_DIR . 'vendor/tcpdf/seob-tcpdf-loader.php';
		require_once SEOB_PLUGIN_DIR . 'includes/Pdf/Document.php';

		$pdf = new SEOB_Pdf_Document( 'P', 'mm', 'A4', true, 'UTF-8', false );

		$pdf->brand_logo_path    = $data['company']['logo_path'] ?? '';
		$pdf->brand_logo_w       = (float) ( $data['company']['logo_header_w'] ?? 0 );
		$pdf->brand_logo_h       = (float) ( $data['company']['logo_header_h'] ?? 14 );
		$pdf->brand_accent_color = $data['company']['accent_color'] ?? '#2271b1';
		$pdf->brand_site_name    = $data['site_name'];
		$pdf->brand_company_name = $data['company']['name'] ?? '';

		$pdf->setPrintHeader( true );
		$pdf->setPrintFooter( true );
		$pdf->SetCreator( 'SEO Booster Pro' );
		$pdf->SetAuthor( $data['company']['name'] ?: $data['site_name'] );
		$pdf->SetTitle( sprintf( 'SEO audit – %s', $data['site_name'] ) );

		$pdf->setHeaderMargin( 5 );
		$pdf->setFooterMargin( 10 );
		$pdf->SetMargins( 15, 32, 15 );
		$pdf->SetAutoPageBreak( true, 20 );

		$pdf->setRTL( false );
		$pdf->SetFont( 'dejavusans', '', 10 );

		$pdf->AddPage();

		$html = $this->render_template( $data );

		$pdf->writeHTML( $html, true, false, true, false, '' );

		return $pdf->Output( '', 'S' );
	}

	/**
	 * Vyrenderuje HTML šablonu reportu do stringu.
	 */
	private function render_template( array $data ): string {
		ob_start();
		include SEOB_PLUGIN_DIR . 'templates/pdf/report.php';

		return (string) ob_get_clean();
	}
}
