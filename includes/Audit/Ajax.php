<?php
/**
 * AJAX endpointy pro Audit Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Audit_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	private SEOB_Audit_ScanRunner $runner;

	public function __construct() {
		$this->runner = new SEOB_Audit_ScanRunner();

		add_action( 'wp_ajax_seob_scan_start', [ $this, 'scan_start' ] );
		add_action( 'wp_ajax_seob_scan_batch', [ $this, 'scan_batch' ] );
		add_action( 'wp_ajax_seob_scan_results', [ $this, 'scan_results' ] );
		add_action( 'wp_ajax_seob_save_meta', [ $this, 'save_meta' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	public function scan_start(): void {
		$this->check_request();

		$result = $this->runner->start_scan( 'manual' );

		wp_send_json_success( $result );
	}

	public function scan_batch(): void {
		$this->check_request();

		$scan_id    = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : 0;
		$batch_size = (int) SEOB_Settings::get( SEOB_Settings::AUDIT )['batch_size'];

		if ( $scan_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatné ID scanu.', 'seo-boost' ) ], 400 );
		}

		$result = $this->runner->process_batch( $scan_id, $batch_size > 0 ? $batch_size : 20 );

		wp_send_json_success( $result );
	}

	public function scan_results(): void {
		$this->check_request();

		$scan_id = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : null;

		$result = $this->runner->get_results( $scan_id ?: null );

		wp_send_json_success( $result );
	}

	public function save_meta(): void {
		$this->check_request();

		$object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		$field     = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value     = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		$allowed_fields = [
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
		];

		if ( $object_id <= 0 || ! isset( $allowed_fields[ $field ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $object_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění upravit tento obsah.', 'seo-boost' ) ], 403 );
		}

		update_post_meta( $object_id, $allowed_fields[ $field ], $value );

		wp_send_json_success( [
			'value'       => $value,
			'pixel_width' => SEOB_Pixel_Width::calculate( $value ),
		] );
	}
}
