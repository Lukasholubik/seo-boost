<?php
/**
 * AJAX endpointy pro Content Decay Monitor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_ContentDecay_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_decay_run_scan',   [ $this, 'run_scan' ] );
		add_action( 'wp_ajax_seob_decay_get_results', [ $this, 'get_results' ] );
		add_action( 'wp_ajax_seob_decay_get_history', [ $this, 'get_history' ] );
	}

	/**
	 * Spustí synchronní scan a vrátí výsledky.
	 * Synchronní protože jde jen o DB queries (žádné HTTP requesty).
	 */
	public function run_scan(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$scan = SEOB_ContentDecay_Scanner::run();

		wp_send_json_success( $this->format_scan( $scan ) );
	}

	/**
	 * Vrátí výsledky posledního scanu (bez spuštění nového).
	 */
	public function get_results(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		$scan = SEOB_ContentDecay_Scanner::get_last();

		if ( null === $scan ) {
			wp_send_json_success( [ 'has_results' => false ] );
			return;
		}

		wp_send_json_success( array_merge( [ 'has_results' => true ], $this->format_scan( $scan ) ) );
	}

	/**
	 * Vrátí archiv posledních skenů (summary bez výsledků).
	 */
	public function get_history(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění.' ], 403 );
		}

		wp_send_json_success( SEOB_ContentDecay_Scanner::get_history() );
	}

	// ── Private ───────────────────────────────────────────────────────────────

	private function format_scan( array $scan ): array {
		// Výsledky jsou již seřazeny dle decay_score desc ze Scanneru.
		return [
			'scan_id'       => $scan['scan_id'],
			'total'         => $scan['total'],
			'decaying'      => $scan['decaying'],
			'stale'         => $scan['stale'],
			'aging'         => $scan['aging'],
			'fresh'         => $scan['fresh'],
			'gsc_available' => ! empty( $scan['gsc_available'] ),
			'scanned_at'    => $scan['scanned_at'],
			'scanned_date'  => wp_date( 'j. n. Y H:i', $scan['scanned_at'] ),
			'results'       => $scan['results'] ?? [],
		];
	}
}
