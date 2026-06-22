<?php
/**
 * AJAX endpoint pro stránku „Stav systému“ – moduly, health checky, trendy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Status_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_status_data', [ $this, 'status_data' ] );
		add_action( 'wp_ajax_seob_status_toggle_module', [ $this, 'toggle_module' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	public function status_data(): void {
		$this->check_request();

		$modules = SEOB_Module_Manager::get_modules();
		$checks  = [];

		foreach ( $modules as $module_id => $module ) {
			if ( ! empty( $module['active'] ) ) {
				$checks[ $module_id ] = SEOB_Health_Checks::get_checks( $module_id );
			}
		}

		$trends = [
			'audit'     => [
				'score_avg' => SEOB_Metrics::get_trend( 'audit', 'score_avg' ),
			],
			'redirects' => [
				'unresolved_404_count' => SEOB_Metrics::get_trend( 'redirects', 'unresolved_404_count' ),
			],
		];

		wp_send_json_success(
			[
				'modules'        => $modules,
				'checks'         => $checks,
				'general_checks' => SEOB_Health_Checks::get_general_checks(),
				'trends'         => $trends,
			]
		);
	}

	/**
	 * Zapne/vypne modul jedním kliknutím ze stránky „Stav systému“.
	 */
	public function toggle_module(): void {
		$this->check_request();

		$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';

		if ( ! isset( SEOB_Module_Manager::MODULES[ $module_id ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Neznámý modul.', 'seo-boost' ) ], 400 );
		}

		$general = SEOB_Settings::get( SEOB_Settings::GENERAL );
		$enabled = ! empty( $general['modules'][ $module_id ] );

		$general['modules'][ $module_id ] = $enabled ? 0 : 1;

		SEOB_Settings::update( SEOB_Settings::GENERAL, $general );

		wp_send_json_success(
			[
				'modules' => SEOB_Module_Manager::get_modules(),
			]
		);
	}
}
