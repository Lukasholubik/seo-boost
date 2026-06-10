<?php
/**
 * Orchestrátor – inicializuje všechny moduly pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Plugin {

	private static ?SEOB_Plugin $instance = null;

	public static function instance(): SEOB_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		load_plugin_textdomain( 'seo-boost', false, dirname( plugin_basename( SEOB_PLUGIN_FILE ) ) . '/languages' );

		new SEOB_Admin();
		new SEOB_Settings_Ajax();

		$modules = SEOB_Settings::get( SEOB_Settings::GENERAL )['modules'];

		if ( ! empty( $modules['audit'] ) ) {
			new SEOB_Audit_Ajax();
		}

		if ( ! empty( $modules['redirects'] ) ) {
			new SEOB_Redirects_Ajax();
			new SEOB_Redirect_Manager();
		}
	}
}
