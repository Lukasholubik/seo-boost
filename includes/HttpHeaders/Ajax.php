<?php
/**
 * AJAX endpointy pro HTTP Headers Scanner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_HttpHeaders_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_http_headers_start_scan',   [ $this, 'start_scan' ] );
		add_action( 'wp_ajax_seob_http_headers_cancel_scan',  [ $this, 'cancel_scan' ] );
		add_action( 'wp_ajax_seob_http_headers_scan_status',  [ $this, 'scan_status' ] );
		add_action( 'wp_ajax_seob_http_headers_get_history',  [ $this, 'get_history' ] );
		add_action( 'wp_ajax_seob_http_headers_get_results',  [ $this, 'get_results' ] );
		add_action( 'wp_ajax_seob_http_headers_check_url',    [ $this, 'check_single_url' ] );
	}

	public function start_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$limit = min( (int) ( $_POST['limit'] ?? 50 ), 100 );
		wp_send_json_success( SEOB_HttpHeaders_ScanRunner::start( $limit ) );
	}

	public function cancel_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		SEOB_HttpHeaders_ScanRunner::cancel();
		wp_send_json_success( [ 'message' => 'Scan zrušen.' ] );
	}

	public function scan_status(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$active = SEOB_HttpHeaders_ScanRunner::get_active();
		wp_send_json_success( empty( $active ) ? [ 'status' => 'none' ] : $active );
	}

	public function get_history(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		wp_send_json_success( SEOB_HttpHeaders_ScanRunner::get_history() );
	}

	public function get_results(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$scan_id = (int) ( $_POST['scan_id'] ?? 0 );
		if ( $scan_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Chybí scan_id.' ] );
		}

		wp_send_json_success( SEOB_HttpHeaders_ScanRunner::get_results( $scan_id ) );
	}

	/** Rychlá kontrola jedné URL (panel "Zkontrolovat URL"). */
	public function check_single_url(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( empty( $url ) ) {
			wp_send_json_error( [ 'message' => 'Chybí URL parametr.' ] );
		}

		wp_send_json_success( SEOB_HttpHeaders_Checker::check( $url ) );
	}
}
