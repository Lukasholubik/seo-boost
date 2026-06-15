<?php
/**
 * AJAX endpointy pro export PDF reportu auditu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Pdf_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	private SEOB_Pdf_Report_Data $report_data;

	public function __construct() {
		$this->report_data = new SEOB_Pdf_Report_Data();

		add_action( 'wp_ajax_seob_pdf_report_data', [ $this, 'report_data' ] );
		add_action( 'wp_ajax_seob_pdf_export', [ $this, 'export' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	/**
	 * Vrátí připravená data pro stránku Export reportu.
	 */
	public function report_data(): void {
		$this->check_request();

		$scan_id = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : 0;

		$data = $this->report_data->build( $scan_id > 0 ? $scan_id : null );

		if ( null === $data ) {
			wp_send_json_error( [ 'message' => __( 'Scan nebyl nalezen.', 'seo-boost' ) ], 404 );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Vygeneruje a odešle PDF report ke stažení.
	 */
	public function export(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění.', 'seo-boost' ), '', [ 'response' => 403 ] );
		}

		$scan_id = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : 0;

		$data = $this->report_data->build( $scan_id > 0 ? $scan_id : null );

		if ( null === $data ) {
			wp_die( esc_html__( 'Scan nebyl nalezen.', 'seo-boost' ), '', [ 'response' => 404 ] );
		}

		$scan_id = (int) $data['scan']['id'];

		if ( isset( $_POST['intro_summary'] ) ) {
			$data['intro_summary'] = sanitize_textarea_field( wp_unslash( $_POST['intro_summary'] ) );
		}

		$offer_key = isset( $_POST['offer_key'] ) ? sanitize_key( wp_unslash( $_POST['offer_key'] ) ) : $data['offer_suggestion'];

		if ( ! isset( $data['offer_templates'][ $offer_key ] ) ) {
			$offer_key = $data['offer_suggestion'];
		}

		$data['offer_name'] = $data['offer_templates'][ $offer_key ]['name'];
		$data['offer_body'] = $data['offer_templates'][ $offer_key ]['body'];

		if ( isset( $_POST['offer_name'] ) ) {
			$data['offer_name'] = sanitize_text_field( wp_unslash( $_POST['offer_name'] ) );
		}

		if ( isset( $_POST['offer_body'] ) ) {
			$data['offer_body'] = sanitize_textarea_field( wp_unslash( $_POST['offer_body'] ) );
		}

		$business = [
			'monthly_visits'  => isset( $_POST['biz_monthly_visits'] ) ? (float) $_POST['biz_monthly_visits'] : 0,
			'conversion_rate' => isset( $_POST['biz_conversion_rate'] ) ? (float) $_POST['biz_conversion_rate'] : 0,
			'avg_value'       => isset( $_POST['biz_avg_value'] ) ? (float) $_POST['biz_avg_value'] : 0,
		];

		$data['business_impact'] = $this->report_data->compute_business_impact( $data, $business );

		$renderer = new SEOB_Pdf_Renderer();
		$pdf      = $renderer->render( $data );

		SEOB_Metrics::record( 'pdf', 'export_count', 1 );

		$filename = sanitize_file_name( sprintf( 'seo-audit-%s-%d.pdf', sanitize_title( $data['site_name'] ), $scan_id ) );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );

		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binární obsah PDF.

		exit;
	}
}
