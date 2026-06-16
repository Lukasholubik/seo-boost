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

		// Oprava WP-Cron spawn v prostredich se self-signed SSL (napr. Local by Flywheel).
		// spawn_cron() pouziva wp_remote_post na wp-cron.php – bez tohoto filtru selze
		// kvuli neoveritelnym certifikatom a vsechny background scany se zaseknou.
		add_filter( 'cron_request', static function ( array $req ): array {
			$req['args']['sslverify'] = false;
			return $req;
		} );

		new SEOB_Admin();
		new SEOB_Settings_Ajax();
		new SEOB_Schema_Helper();
		new SEOB_Status_Ajax();

		SEOB_Module_Manager::init_active();
		SEOB_Health_Checks::register();
	}
}
