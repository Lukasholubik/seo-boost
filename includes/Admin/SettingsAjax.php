<?php
/**
 * AJAX endpoint pro uložení nastavení pluginu (stránka Nastavení).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Settings_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_save_settings', [ $this, 'save_settings' ] );
	}

	public function save_settings(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$general = [
			'debug'               => $this->bool_field( 'debug' ),
			'delete_on_uninstall' => $this->bool_field( 'delete_on_uninstall' ),
			'modules'             => [
				'audit'     => $this->bool_field( 'modules_audit' ),
				'redirects' => $this->bool_field( 'modules_redirects' ),
			],
		];

		$audit = [
			'cron_enabled'       => $this->bool_field( 'cron_enabled' ),
			'batch_size'         => $this->int_field( 'batch_size', 1, 100, 20 ),
			'thin_content_words' => $this->int_field( 'thin_content_words', 50, 2000, 300 ),
		];

		$redirect = [
			'log_404'            => $this->bool_field( 'log_404' ),
			'log_retention_days' => $this->int_field( 'log_retention_days', 1, 365, 30 ),
		];

		SEOB_Settings::update( SEOB_Settings::GENERAL, $general );
		SEOB_Settings::update( SEOB_Settings::AUDIT, $audit );
		SEOB_Settings::update( SEOB_Settings::REDIRECT, $redirect );

		wp_send_json_success( [
			'general'  => $general,
			'audit'    => $audit,
			'redirect' => $redirect,
		] );
	}

	private function bool_field( string $name ): int {
		return isset( $_POST[ $name ] ) && '1' === $_POST[ $name ] ? 1 : 0;
	}

	private function int_field( string $name, int $min, int $max, int $default ): int {
		$value = isset( $_POST[ $name ] ) ? absint( $_POST[ $name ] ) : $default;

		return max( $min, min( $max, $value ) );
	}
}
