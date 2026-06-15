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
		new SEOB_Schema_Helper();
		new SEOB_Status_Ajax();

		SEOB_Module_Manager::init_active();
		SEOB_Health_Checks::register();
	}
}
