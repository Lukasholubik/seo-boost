<?php
/**
 * AJAX endpointy pro JSON-LD Validátor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_JsonLd_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_json_ld_start_scan',   [ $this, 'start_scan' ] );
		add_action( 'wp_ajax_seob_json_ld_cancel_scan',  [ $this, 'cancel_scan' ] );
		add_action( 'wp_ajax_seob_json_ld_scan_status',  [ $this, 'scan_status' ] );
		add_action( 'wp_ajax_seob_json_ld_get_history',  [ $this, 'get_history' ] );
		add_action( 'wp_ajax_seob_json_ld_get_results',  [ $this, 'get_results' ] );
		add_action( 'wp_ajax_seob_json_ld_scan_url',     [ $this, 'scan_single_url' ] );
	}

	/** Spusti novy scan na pozadi (WP-Cron). */
	public function start_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$limit = min( (int) ( $_POST['limit'] ?? 50 ), 100 );
		wp_send_json_success( SEOB_JsonLd_ScanRunner::start( $limit ) );
	}

	/** Zrusi probihajici scan. */
	public function cancel_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		SEOB_JsonLd_ScanRunner::cancel();
		wp_send_json_success( [ 'message' => 'Scan zrušen.' ] );
	}

	/** Vraci aktualni stav scanu (pro polling). */
	public function scan_status(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$active = SEOB_JsonLd_ScanRunner::get_active();
		if ( empty( $active ) ) {
			wp_send_json_success( [ 'status' => 'none' ] );
			return;
		}

		wp_send_json_success( $active );
	}

	/** Vraci archiv skenů. */
	public function get_history(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		wp_send_json_success( SEOB_JsonLd_ScanRunner::get_history() );
	}

	/** Vraci URL-detaily konkretniho scanu. */
	public function get_results(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$scan_id = (int) ( $_POST['scan_id'] ?? 0 );
		if ( $scan_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Chybí scan_id.' ] );
		}

		wp_send_json_success( SEOB_JsonLd_ScanRunner::get_results( $scan_id ) );
	}

	/** Validuje jednu konkretni URL (panel "Validovat URL"). */
	public function scan_single_url(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( empty( $url ) ) {
			wp_send_json_error( [ 'message' => 'Chybí URL parametr.' ] );
		}

		$error = self::validate_site_url( $url );
		if ( $error ) {
			wp_send_json_error( [ 'message' => $error ], 400 );
		}

		wp_send_json_success( SEOB_JsonLd_PageScanner::scan_url( $url ) );
	}

	/**
	 * Ověří, že URL patří na tento web (ochrana před SSRF).
	 *
	 * @return string|null  Chybová zpráva nebo null při validní URL.
	 */
	public static function validate_site_url( string $url ): ?string {
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
			return 'URL musí začínat http:// nebo https://.';
		}

		$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( empty( $host ) || $host !== $site_host ) {
			return sprintf( 'Lze validovat jen URL na tomto webu (%s).', $site_host );
		}

		return null;
	}
}
