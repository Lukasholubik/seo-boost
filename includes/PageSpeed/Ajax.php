<?php
/**
 * AJAX endpointy pro modul PageSpeed Insights.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_PageSpeed_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_psi_start', [ $this, 'start' ] );
		add_action( 'wp_ajax_seob_psi_batch', [ $this, 'batch' ] );
		add_action( 'wp_ajax_seob_psi_results', [ $this, 'results' ] );
		add_action( 'wp_ajax_seob_psi_history', [ $this, 'history' ] );
		add_action( 'wp_ajax_seob_psi_active', [ $this, 'active' ] );
		add_action( 'wp_ajax_seob_psi_delete', [ $this, 'delete' ] );

		// WP-Cron callback, který dokončí běh na pozadí i bez otevřené stránky administrace.
		add_action( SEOB_PageSpeed_ScanRunner::CRON_HOOK, [ new SEOB_PageSpeed_ScanRunner(), 'process_batch_cron' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	/**
	 * Založí nový běh analýzy a vrátí jeho ID + celkový počet položek.
	 */
	public function start(): void {
		$this->check_request();

		$error = $this->check_configured();

		if ( is_wp_error( $error ) ) {
			wp_send_json_error( [ 'message' => $error->get_error_message() ], 400 );
		}

		$runner = new SEOB_PageSpeed_ScanRunner();
		$result = $runner->start_scan();

		wp_send_json_success( $result );
	}

	/**
	 * Zpracuje jednu dávku (1 položku) běhu.
	 */
	public function batch(): void {
		$this->check_request();

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

		if ( $run_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$runner = new SEOB_PageSpeed_ScanRunner();
		$result = $runner->process_batch( $run_id, 1 );

		wp_send_json_success( $result );
	}

	/**
	 * Vrátí výsledky daného (nebo posledního dokončeného) běhu.
	 */
	public function results(): void {
		$this->check_request();

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

		$runner = new SEOB_PageSpeed_ScanRunner();
		$result = $runner->get_results( $run_id ?: null );

		wp_send_json_success( [ 'result' => $result ] );
	}

	/**
	 * Vrátí historii dokončených běhů pro výběr v dashboardu.
	 */
	public function history(): void {
		$this->check_request();

		$runner = new SEOB_PageSpeed_ScanRunner();

		wp_send_json_success( [ 'runs' => $runner->get_run_history() ] );
	}

	/**
	 * Vrátí aktuálně běžící scan (pokud existuje), aby šel obnovit progress bar po znovunačtení stránky.
	 */
	public function active(): void {
		$this->check_request();

		$runner = new SEOB_PageSpeed_ScanRunner();

		wp_send_json_success( [ 'run' => $runner->get_active_run() ] );
	}

	/**
	 * Smaže běh analýzy.
	 */
	public function delete(): void {
		$this->check_request();

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

		if ( $run_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$runner = new SEOB_PageSpeed_ScanRunner();
		$runner->delete_run( $run_id );

		wp_send_json_success();
	}

	/**
	 * Ověří, že je modul zapnutý a má vyplněný API klíč.
	 *
	 * @return true|WP_Error
	 */
	private function check_configured() {
		$settings = SEOB_Settings::get( SEOB_Settings::PAGESPEED );

		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'seob_psi_disabled', __( 'Modul PageSpeed Insights je vypnutý. Zapněte ho v Nastavení.', 'seo-boost' ) );
		}

		$api_key = SEOB_AiQueue_Crypt::decrypt( (string) $settings['api_key_enc'] );

		if ( '' === $api_key ) {
			return new WP_Error( 'seob_psi_not_configured', __( 'Chybí API klíč pro PageSpeed Insights – doplňte ho v Nastavení.', 'seo-boost' ) );
		}

		return true;
	}
}
